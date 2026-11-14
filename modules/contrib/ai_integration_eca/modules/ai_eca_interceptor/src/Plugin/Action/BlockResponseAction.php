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
use Drupal\ai_eca_interceptor\Exception\ResponseBlockedException;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: block the AI response before it is returned to the caller.
 */
#[Action(
  id: 'ai_eca_interceptor_block_response',
  label: new TranslatableMarkup('AI response: block'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Stop an AI response from being returned, optionally replacing it with a fallback output.'),
  version_introduced: '2.0.0',
)]
class BlockResponseAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->responseEvent();
    if (!$event) {
      return;
    }
    $mode = $this->configuration['mode'];
    $message = (string) $this->tokenService->replace($this->configuration['message']);
    if ($mode === 'exception') {
      throw new ResponseBlockedException($message !== '' ? $message : 'AI response blocked by ECA rule.');
    }
    // Chat fallback replacement.
    $output = $event->getOutput();
    if ($output instanceof ChatOutput) {
      $event->setOutput(new ChatOutput(
        new ChatMessage('assistant', $message !== '' ? $message : 'Response blocked.'),
        ['blocked' => TRUE],
        []
      ));
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
    return [
      'mode' => 'exception',
      'message' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => [
        'exception' => $this->t('Throw exception'),
        'fallback' => $this->t('Replace with fallback output (chat only)'),
      ],
      '#default_value' => $this->configuration['mode'],
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['mode'] = $form_state->getValue('mode');
    $this->configuration['message'] = $form_state->getValue('message');
    parent::submitConfigurationForm($form, $form_state);
  }

}
