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
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\Summarization\SummarizationOutput;
use Drupal\ai\OperationType\TranslateText\TranslateTextOutput;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: replace the text content of an AI response.
 */
#[Action(
  id: 'ai_eca_interceptor_set_response_text',
  label: new TranslatableMarkup('AI response: set text'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Replace the textual output of an AI response (chat, summarization, translation, speech-to-text).'),
  version_introduced: '2.0.0',
)]
class SetResponseTextAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->responseEvent();
    if (!$event) {
      return;
    }
    $output = $event->getOutput();
    $text = (string) $this->tokenService->replace($this->configuration['text']);

    if ($output instanceof ChatOutput) {
      $normalized = $output->getNormalized();
      if ($normalized instanceof ChatMessage) {
        $normalized->setText($text);
      }
      else {
        // Streamed or other — replace with a fresh ChatMessage.
        $new = new ChatOutput(new ChatMessage('assistant', $text), $output->getRawOutput(), $output->getMetadata());
        $event->setOutput($new);
      }
      return;
    }
    if ($output instanceof SummarizationOutput) {
      $event->setOutput(new SummarizationOutput($text, $output->getRawOutput(), $output->getMetadata()));
      return;
    }
    if ($output instanceof TranslateTextOutput) {
      $event->setOutput(new TranslateTextOutput($text, $output->getRawOutput(), $output->getMetadata()));
      return;
    }
    if ($output instanceof SpeechToTextOutput) {
      $event->setOutput(new SpeechToTextOutput($text, $output->getRawOutput(), $output->getMetadata()));
      return;
    }
  }

  /**
   * {@inheritdoc}
   *
   * The denial carries a reason so ECA can report it: EcaAction::execute() logs
   * it and the process debugger records the action as access-denied rather than
   * executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->responseEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on an AI response event ("AI response: after provider call" or "AI response: stream finished").');
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
    return ['text' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Replacement text'),
      '#default_value' => $this->configuration['text'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['text'] = $form_state->getValue('text');
    parent::submitConfigurationForm($form, $form_state);
  }

}
