<?php

namespace Drupal\maestro_ai_task\Plugin\MaestroAiTaskCapabilities;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;

use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;

/**
 * Provides a 'MaestroAiTaskChatCapability' Maestro AI Task Capability.
 * This capability uses the AI module's chat provider to chat with the AI.
 * @MaestroAiTaskCapabilities(
 *   id = "MaestroAiTaskChatCapability",
 *   ai_provider = "chat",
 *   capability_description = @Translation("Chatbot. Manages the Chat operation type."),
 * )
 */
class MaestroAiTaskChatCapability extends MaestroAiTaskCapabilitiesPluginBase implements MaestroAiTaskCapabilitiesInterface {

  /**
   * task  
   *   The task array. This will be the running instance of the MaestroAITask object.  
   *   Contains the configuration of the task itself.
   *
   * @var array
   */
  protected $task = NULL;
  
  /**
   * templateMachineName  
   *   The string machine name of the Maestro template.  
   *
   * @var string
   */
  protected $templateMachineName = '';
  
  /**
   * prompt  
   *   The string prompt from the task.
   *
   * @var string
   */
  protected $prompt = '';

  /**
   * {@inheritDoc}
   */
  public function validateMaestroAiTaskEditForm(array &$form, FormStateInterface $form_state) : void {
    // Nothing to implement.
  }

  /**
   * {@inheritDoc}
   */
  public function getMaestroAiTaskConfigFormElements() : array {
    return [
      '#markup' => $this->t('Return Format is configurable for this option.'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : string {
    /** @var \Drupal\ai\AiProviderPluginManager $service */
    $service = \Drupal::service('ai.provider');
    $sets = $service->getDefaultProviderForOperationType('chat');
    $provider = $service->createInstance($sets['provider_id']);
    $messages = new ChatInput([
      new ChatMessage('user', $this->prompt),
    ]);
    $message = $provider->chat($messages, $sets['model_id'], ['maestro-ai-task-chat'])->getNormalized();
    $responseText = Xss::filter($message->getText()) ?? NULL;
    return $responseText;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    // Nothing to implement.
  }

  /**
   * {@inheritDoc}
   */
  public function performMaestroAiTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) : void {
    // Nothing to implement.
  }

  /**
   * {@inheritDoc}
   */
  public function allowConfigurableReturnFormat() : bool {
    // For pure chat, we do allow for a customizable return format.
    return TRUE;
  }
}
