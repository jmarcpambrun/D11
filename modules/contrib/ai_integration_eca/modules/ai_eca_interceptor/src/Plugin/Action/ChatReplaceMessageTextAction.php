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
 * Action: replace the text of a chat message by index.
 */
#[Action(
  id: 'ai_eca_interceptor_chat_replace_message_text',
  label: new TranslatableMarkup('AI chat request: replace message text'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Overwrite the text of an existing chat message.'),
  version_introduced: '2.0.0',
)]
class ChatReplaceMessageTextAction extends AiEventActionBase {

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
    $index = (int) $this->configuration['index'];
    $text = (string) $this->tokenService->replace($this->configuration['text']);
    $messages = $input->getMessages();
    if (!isset($messages[$index]) || !$messages[$index] instanceof ChatMessage) {
      return;
    }
    $messages[$index]->setText($text);
    $input->setMessages($messages);
    $event->setInput($input);
  }

  /**
   * {@inheritdoc}
   *
   * ECA sets the event before calling access(), so the message list is
   * available here. Checking the index in access() means an out-of-bounds
   * index — or an entry that is not a chat message — is reported as access
   * denied and logged by ECA, instead of being silently skipped in execute().
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
    else {
      $input = $event->getInput();
      if (!$input instanceof ChatInput) {
        $result = AccessResult::forbidden('This action only applies to a chat request, but the AI request input is not a ChatInput.');
      }
      else {
        $index = (int) ($this->configuration['index'] ?? 0);
        $messages = $input->getMessages();
        if (!isset($messages[$index])) {
          $result = AccessResult::forbidden(sprintf('The chat request has no message at index %d; it carries %d message(s).', $index, count($messages)));
        }
        elseif (!$messages[$index] instanceof ChatMessage) {
          $result = AccessResult::forbidden(sprintf('The chat request entry at index %d is not a chat message.', $index));
        }
        else {
          $result = AccessResult::allowed();
        }
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['index' => 0, 'text' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message index'),
      '#default_value' => $this->configuration['index'],
      '#description' => $this->t('Zero-based index of the chat message to mutate.'),
      '#required' => TRUE,
    ];
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('New text'),
      '#default_value' => $this->configuration['text'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['index'] = (int) $form_state->getValue('index');
    $this->configuration['text'] = $form_state->getValue('text');
    parent::submitConfigurationForm($form, $form_state);
  }

}
