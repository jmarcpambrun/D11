<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: force a chat output on the current AI request.
 */
#[Action(
  id: 'ai_eca_interceptor_force_chat_output',
  label: new TranslatableMarkup('AI request: force chat output'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Return a forced chat output without calling the provider.'),
  version_introduced: '2.0.0',
)]
class ForceChatOutputAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->preEvent();
    if (!$event || $event->getOperationType() !== 'chat') {
      return;
    }
    $text = (string) $this->tokenService->replace($this->configuration['text']);
    $role = $this->configuration['role'] ?: 'assistant';
    $message = new ChatMessage($role, $text);
    $event->setForcedOutputObject(new ChatOutput($message, ['forced' => TRUE, 'text' => $text], []));
  }

  /**
   * {@inheritdoc}
   *
   * Each denial carries its own specific reason so ECA can report it:
   * EcaAction::execute() logs it and the process debugger records the action as
   * access-denied rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $event = $this->preEvent();
    if ($event === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI request: before provider call" event.');
    }
    elseif ($event->getOperationType() !== 'chat') {
      $result = AccessResult::forbidden(sprintf('This action can only force a chat output, but the AI request has the operation type "%s".', $event->getOperationType()));
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
    return ['text' => '', 'role' => 'assistant'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Output text'),
      '#default_value' => $this->configuration['text'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['role'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Role'),
      '#default_value' => $this->configuration['role'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['text'] = $form_state->getValue('text');
    $this->configuration['role'] = $form_state->getValue('role');
    parent::submitConfigurationForm($form, $form_state);
  }

}
