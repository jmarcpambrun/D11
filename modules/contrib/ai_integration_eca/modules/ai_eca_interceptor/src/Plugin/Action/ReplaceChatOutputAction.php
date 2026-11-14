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
 * Action: replace a chat response output fully.
 */
#[Action(
  id: 'ai_eca_interceptor_replace_chat_output',
  label: new TranslatableMarkup('AI response: replace chat output'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Replace a chat response with a fresh ChatOutput built from text + role.'),
  version_introduced: '2.0.0',
)]
class ReplaceChatOutputAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->responseEvent();
    if (!$event) {
      return;
    }
    if ($event->getOperationType() !== 'chat') {
      return;
    }
    $text = (string) $this->tokenService->replace($this->configuration['text']);
    $role = $this->configuration['role'] ?: 'assistant';
    $event->setOutput(new ChatOutput(
      new ChatMessage($role, $text),
      ['replaced' => TRUE],
      []
    ));
  }

  /**
   * {@inheritdoc}
   *
   * Each denial carries its own specific reason so ECA can report it:
   * EcaAction::execute() logs it and the process debugger records the action as
   * access-denied rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $event = $this->responseEvent();
    if ($event === NULL) {
      $result = AccessResult::forbidden('This action is only available on an AI response event ("AI response: after provider call" or "AI response: stream finished").');
    }
    elseif ($event->getOperationType() !== 'chat') {
      $result = AccessResult::forbidden(sprintf('This action can only replace a chat output, but the AI response has the operation type "%s".', $event->getOperationType()));
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
