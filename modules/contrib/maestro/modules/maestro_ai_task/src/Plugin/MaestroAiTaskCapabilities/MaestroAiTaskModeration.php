<?php

namespace Drupal\maestro_ai_task\Plugin\MaestroAiTaskCapabilities;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;

use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;

/**
 * Provides a 'MaestroAiTaskModeration' Maestro AI Task Capability.
 * This capability uses the AI module's moderation provider to flag inputs.
 * @MaestroAiTaskCapabilities(
 *   id = "MaestroAiTaskModeration",
 *   ai_provider = "moderation",
 *   capability_description = @Translation("Moderation operation type. Only has output when the input is flagged. Output will be the highest flagging result."),
 * )
 */
class MaestroAiTaskModeration extends MaestroAiTaskCapabilitiesPluginBase implements MaestroAiTaskCapabilitiesInterface {

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
    // We jode a return format so that validation can pass from the base Maestro AI Task.
    $form['hidden_ai_return_format'] = [
      '#type' => 'hidden',
      '#value' => 'json_true_false',
    ];

    $form['return_format_message'] = [
      '#markup' => 
        $this->t('Return Format is not configurable for this option.') .
        '<br>' . 
        $this->t('If you\'ve set the return data to <b>"Process in task"</b>, this operation will successfully complete ') .
        $this->t('the task if there is no found moderation issue. If a moderation issue is found, the task will be cancelled.') 
        
        ,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : string {
    /** @var \Drupal\ai\AiProviderPluginManager $service */
    $responseText = '';

    // We use the ai_return_into to know whether we just return a true/false
    $return_into = $this->task['data']['ai']['ai_return_into'] ?? '';

    $service = \Drupal::service('ai.provider');
    $sets = $service->getDefaultProviderForOperationType('moderation');
    $provider = $service->createInstance($sets['provider_id']);
    /** @var \Drupal\ai\OperationType\Moderation\ModerationResponse $message */
    $message = $provider->moderation($this->prompt, $sets['model_id'], ['maestro-ai-task-moderation'])->getNormalized();
    $flagged = boolval($message->isFlagged());
    $information = $message->getInformation();
    $highest_probability = 0;
    if($flagged && !empty($information) && is_array($information)) {
      foreach($information as $key => $probability) {
        if(floatval($probability) > $highest_probability) {
          $responseText = Xss::filter($key);
        }
      }
    }
    
    // If we're processing this in the task, we need to return a true/faluse in a result key.
    // That means we'll just return whether this is flagged or not.
    // Otherwise, we just bypass this and return
    if($return_into == 'process_in_task') {
      // Set this to the ! value as we want the opposite for task success (true) and task cancelled (false)
      $response_flag = 'true'; // Default is true, set to false if it is flagged
      if($flagged) {
        $response_flag = 'false';
      }
      $responseText = Json::encode([
        'result' => $response_flag,
      ]);
      
    }
    return $responseText;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    // We force the ai return format as we can process this in task directly.
    $task['data']['ai']['ai_return_format'] = 'json_true_false';
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
    // We force the output to either be blank or filled with the highest moderation probability ranking.
    return FALSE;
  }
}
