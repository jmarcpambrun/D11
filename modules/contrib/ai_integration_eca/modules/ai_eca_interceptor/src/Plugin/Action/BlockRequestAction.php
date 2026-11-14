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
use Drupal\ai_eca_interceptor\Exception\RequestBlockedException;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: block the AI request before it reaches the provider.
 */
#[Action(
  id: 'ai_eca_interceptor_block_request',
  label: new TranslatableMarkup('AI request: block'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Stop the current AI request from being sent to the provider.'),
  version_introduced: '2.0.0',
)]
class BlockRequestAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->preEvent();
    if (!$event) {
      return;
    }
    $mode = $this->configuration['mode'];
    $message = (string) $this->tokenService->replace($this->configuration['message']);
    if ($mode === 'exception') {
      throw new RequestBlockedException($message !== '' ? $message : 'AI request blocked by ECA rule.');
    }
    // Fallback output mode (chat-focused for MVP).
    if ($event->getOperationType() === 'chat') {
      $chat_message = new ChatMessage('assistant', $message !== '' ? $message : 'Request blocked.');
      $event->setForcedOutputObject(new ChatOutput($chat_message, ['blocked' => TRUE], []));
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
    if ($this->preEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI request: before provider call" event.');
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
      'mode' => 'fallback',
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
        'fallback' => $this->t('Return fallback output'),
        'exception' => $this->t('Throw exception'),
      ],
      '#default_value' => $this->configuration['mode'],
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fallback message / exception text'),
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
