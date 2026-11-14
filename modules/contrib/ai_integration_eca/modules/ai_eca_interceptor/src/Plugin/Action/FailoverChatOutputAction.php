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
 * Action: recover a failed chat call by returning a canned chat output.
 *
 * Operates on the AI exception event (AI response: failed). When a chat call
 * throws, this sets a forced ChatOutput so the AI module returns it to the
 * caller instead of re-throwing — a graceful failover to a canned or cached
 * response without the caller having to handle the exception.
 *
 * Like FailoverToProviderAction, it yields to an already forced output, so it
 * acts as a last resort when ordered after a failover.
 */
#[Action(
  id: 'ai_eca_interceptor_failover_chat_output',
  label: new TranslatableMarkup('AI response failed: return chat output'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Recover from a failed chat call by returning a canned chat output instead of re-throwing the exception.'),
  version_introduced: '2.0.0',
)]
class FailoverChatOutputAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->failedEvent();
    if (!$event || $event->getOperationType() !== 'chat') {
      return;
    }
    // Do not override a forced output another action already supplied.
    if ($event->getForcedOutputObject()) {
      return;
    }
    $text = (string) $this->tokenService->replace($this->configuration['text']);
    $role = $this->configuration['role'] ?: 'assistant';
    $message = new ChatMessage($role, $text);
    $event->setForcedOutputObject(new ChatOutput($message, ['failover' => TRUE, 'text' => $text], []));
  }

  /**
   * {@inheritdoc}
   *
   * Each denial carries its own specific reason so ECA can report it:
   * EcaAction::execute() logs it and the process debugger records the action as
   * access-denied rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $event = $this->failedEvent();
    if ($event === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI response: failed" event.');
    }
    elseif ($event->getOperationType() !== 'chat') {
      $result = AccessResult::forbidden(sprintf('This action can only return a chat output, but the failed call had the operation type "%s".', $event->getOperationType()));
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
      '#description' => $this->t('The chat response to return in place of the failed call. Token replacement is supported.'),
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
