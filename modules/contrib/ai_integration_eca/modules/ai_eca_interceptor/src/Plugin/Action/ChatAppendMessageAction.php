<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: append a new chat message to a chat request.
 */
#[Action(
  id: 'ai_eca_interceptor_chat_append_message',
  label: new TranslatableMarkup('AI chat request: append message'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Append a new message to a chat request.'),
  version_introduced: '2.0.0',
)]
class ChatAppendMessageAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->preEvent();
    if (!$event) {
      return;
    }
    $input = $event->getInput();
    if (!$input instanceof ChatInput) {
      return;
    }
    $role = (string) ($this->configuration['role'] ?: 'user');
    $text = (string) $this->tokenService->replace($this->configuration['text']);
    $messages = $input->getMessages();
    $messages[] = new ChatMessage($role, $text);
    $input->setMessages($messages);
    $event->setInput($input);
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
    elseif (!$event->getInput() instanceof ChatInput) {
      $result = AccessResult::forbidden('This action only applies to a chat request, but the AI request input is not a ChatInput.');
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
    return ['role' => 'user', 'text' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['role'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Role'),
      '#default_value' => $this->configuration['role'],
    ];
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $this->configuration['text'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['role'] = $form_state->getValue('role');
    $this->configuration['text'] = $form_state->getValue('text');
    parent::submitConfigurationForm($form, $form_state);
  }

}
