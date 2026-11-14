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
 * Action: add a tag to the AI event.
 */
#[Action(
  id: 'ai_eca_interceptor_set_tag',
  label: new TranslatableMarkup('AI event: set tag'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Append a tag to the current AI event.'),
  version_introduced: '2.0.0',
)]
class SetTagAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->aiEvent();
    if (!$event) {
      return;
    }
    // replaceClear(), matching ::access(). See the note there.
    $tag = trim((string) $this->tokenService->replaceClear($this->configuration['tag'] ?? ''));
    if ($tag === '') {
      return;
    }
    $event->setTag($tag, TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * The tag is resolved with replaceClear(), not replace(): the plain replace()
   * leaves a token LITERAL when no replacement value can be generated, so a tag
   * that resolves to nothing survives as a non-empty string, passes the check
   * below, and then attaches the literal "[event:missing]" to the request as a
   * tag the provider and every HasTagCondition will see. replaceClear() removes
   * such tokens, which is what makes the emptiness check meaningful.
   * ::execute() uses the same method — the two disagreeing is the exact bug
   * this check exists to prevent.
   *
   * Each denial carries its own specific reason so ECA can report it:
   * EcaAction::execute() logs it and the process debugger records the action as
   * access-denied rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->aiEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on an AI request, response or failure event.');
    }
    elseif (trim((string) $this->tokenService->replaceClear($this->configuration['tag'] ?? '')) === '') {
      $result = AccessResult::forbidden('The tag is empty after token replacement.');
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
    return ['tag' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['tag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tag'),
      '#default_value' => $this->configuration['tag'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['tag'] = $form_state->getValue('tag');
    parent::submitConfigurationForm($form, $form_state);
  }

}
