<?php

namespace Drupal\maestro_ai_task;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Maestro AI Task Capabilities Plugin Base.
 *
 * Provides a base class for Maestro AI Task Capabilities plugins.  
 * The Maestro AI Task Capability is responsible for interfacing with the AI module to perform
 * the desired AI operation.  Since not all AI operations perform the same function, the Maestro AI 
 * Task Capability plugin provides configuration and execution specific to the desired operation.
 * We align the Maestro AI Task Capability to the AI module's configured providers.  
 * 
 * 
 * It is your responsibility to extend this class and implement the methods.  
 * You must declare a MaestroAiTaskCapabilities annotation.  
 * The 'ai_provider' key value will directly match that of the AI module's providers such as
 *    chat,
 *    text_to_image,
 *    audio_to_audio,
 *    embeddings,
 *    image_and_audio_to_video,
 *    image_classification,
 *    image_to_video,
 *    moderation,
 *    speech_to_speech,
 *    speech_to_text,
 *    text_to_image,
 *    text_to_speech,
 *    translate_text
 * 
 * But also we have pseudo AI module providers that are configured, such as 
 *    chat_with_image_vision,
 *    chat_with_complex_json
 * 
 * @MaestroAiTaskCapabilities(
 *   id = "a unique ID for your Maestro AI Task Capability",
 *   ai_provider = "the AI module's capability you are implementing",
 *   description = @Translation("Description"),
 * )
 */
abstract class MaestroAiTaskCapabilitiesPluginBase extends PluginBase implements MaestroAiTaskCapabilitiesInterface {
  use StringTranslationTrait; // Allow the abilty to use $this->t() for translations.
  
  /**
   * task  
   *   The task object. This will be the running instance of the MaestroAITask object.  
   *
   * @var Drupal\maestro_ai_task\Plugin\EngineTasks\MaestroAITask
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
   * queueID  
   *   The queueID of the executing task.
   *
   * @var string
   */
  protected $queueID = '';

  /**
   * processID  
   *   The processID of the executing task.
   *
   * @var string
   */
  protected $processID = '';
    
  /**
   * form_state  
   *   In the event we are editing a task, the form state is made available
   *
   * @var FormStateInterface
   */
  protected $form_state = NULL;
  
  /**
   * form  
   *   In the event we are editing a task, the form array is made available
   *
   * @var array
   */
  protected $form = NULL;
      
  /**
   * taskStatus  
   *   The capability can return a task status that we detect in the MaestroAiTask engine task plugin.  
   *   The Task status values can be found in maestro.module.  
   *   An example of a task status:  TASK_STATUS_CANCEL  
   * 
   *   This is set to TASK_STATUS_SUCCESS by default.
   * @var int
   */
  protected $taskStatus = TASK_STATUS_SUCCESS;
  
  /**
   * Constructor.
   */
  public function __construct($configuration = NULL) {
    

    if (is_array($configuration)) {
      $this->task = $configuration['task'] ?? NULL;
      $this->templateMachineName = $configuration['templateMachineName'] ?? NULL;
      $this->queueID = $configuration['queueID'] ?? NULL;
      $this->processID = $configuration['processID'] ?? NULL;
      $this->prompt = $configuration['prompt'] ?? NULL;
      $this->form_state = $configuration['form_state'] ?? NULL;
      $this->form = $configuration['form'] ?? NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginId() {
    return parent::getPluginId();
  }
  
  /**
   * setPrompt  
   *  Allows developers to post-constructor execution to reset the prompt.  
   * @param  string $prompt  
   * 
   * @return void
   */
  public function setPrompt($prompt) {
    $this->prompt = $prompt;
  }
  
  /**
   * getTaskStatus  
   *   Returns the taskStatus protected variable denoting anything the capability would like to set the  
   *   Task's status to.  
   *
   * @return NULL|int
   */
  public function getTaskStatus() {
    return $this->taskStatus;
  }
  
  /**
   * {@inheritDoc}
   */
  public function getMaestroAiTaskConfigFormElements() : array {
    return [
      '#markup' => $this->t('This provider does not have any further configuration options.'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function validateMaestroAiTaskEditForm(array &$form, FormStateInterface $form_state) : void {
    // Implement validateMaestroAiTaskEditForm() method.
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : ?string {
    // Implement your specific AI Task call.  You must return a string.
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    // Implement prepareTaskForSave() method.
  }

  /**
   * {@inheritDoc}
   */
  public function performMaestroAiTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) : void {
    // Implement performMaestroAiTaskValidityCheck() method.
  }

  
}