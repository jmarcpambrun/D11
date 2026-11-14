<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: rewrite the message of a failed AI call before it is re-thrown.
 *
 * Operates on the AI exception event (AI response: failed). The original
 * exception class is preserved by AI core, so existing catch blocks keep
 * working; only the message the caller sees is replaced. Useful to turn a raw
 * provider error into a user-facing message.
 */
#[Action(
  id: 'ai_eca_interceptor_rewrite_exception_message',
  label: new TranslatableMarkup('AI response failed: rewrite message'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Replace the exception message before it is re-thrown to the caller. The original exception class is preserved.'),
  version_introduced: '2.0.0',
)]
class RewriteExceptionMessageAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->failedEvent();
    if (!$event) {
      return;
    }
    $event->setMessage((string) $this->tokenService->replace($this->configuration['message']));
  }

  /**
   * {@inheritdoc}
   *
   * The denial carries a reason so ECA can report it: EcaAction::execute() logs
   * it and the process debugger records the action as access-denied rather than
   * executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->failedEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI response: failed" event.');
    }
    else {
      $result = AccessResult::allowed();
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['message' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exception message'),
      '#default_value' => $this->configuration['message'],
      '#description' => $this->t('The replacement message. Token replacement is supported, so you can reference e.g. [event:exception_message].'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['message'] = $form_state->getValue('message');
    parent::submitConfigurationForm($form, $form_state);
  }

}
