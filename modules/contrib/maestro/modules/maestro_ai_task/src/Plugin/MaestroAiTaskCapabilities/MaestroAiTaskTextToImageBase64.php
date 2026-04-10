<?php

namespace Drupal\maestro_ai_task\Plugin\MaestroAiTaskCapabilities;

use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;

/**
 * Provides a 'MaestroAiTaskTextToImageBase64' Maestro AI Task Capability.
 * This capability uses the AI module's text to image provider.
 * TODO: 
 *    Basic Sanitization of the return image done via PHP's getimagesizefromstring. 
 *    GD/ImageMagick could be used to further sanitize the return.
 * 
 * @MaestroAiTaskCapabilities(
 *   id = "MaestroAiTaskTextToImageBase64",
 *   ai_provider = "text_to_image",
 *   capability_description = @Translation("Text to Image operation type, returning the image as a base64 encoded string."),
 * )
 */
class MaestroAiTaskTextToImageBase64 extends MaestroAiTaskCapabilitiesPluginBase implements MaestroAiTaskCapabilitiesInterface {

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
    // Need to validate that we are saving the image to an entity, not a process variable as 
    // PVs only store 256 characters MAX.

    $process_where = $form_state->getValue('ai_return_into');
    if($process_where != 'ai_task_entity') {
      $form_state->setErrorByName(
        'ai_return_into',
        $this->t('You cannot return an entire image into a process variable or process in a task. Please choose a Maestro AI storage entity instead.')
        );
    }

  }

  /**
   * {@inheritDoc}
   */
  public function execute() : string {
    /** @var \Drupal\ai\AiProviderPluginManager $service */
    $service = \Drupal::service('ai.provider');
    $sets = $service->getDefaultProviderForOperationType('text_to_image');
    $provider = $service->createInstance($sets['provider_id']);
    $input = new TextToImageInput($this->prompt);
    /** @var \Drupal\ai\OperationType\TextToImage\TextToImageOutput $return_images */
    $return_images = $provider->textToImage($input, $sets['model_id'], ['maestro-ai-task-text-to-image']);
    /** @var \Drupal\ai\OperationType\GenericType\ImageFile */
    $image = $return_images->getNormalized()[0];
    $binary = $image->getAsBinary();
    // Some rudimentary sanitization of the return image
    if(getimagesizefromstring($binary) === FALSE) {
      return 'invalid';
    }
    else {
      return $image->getAsBase64EncodedString();
    }
    
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    $task['data']['ai']['ai_return_format'] = ''; // Clear out the ai return format 
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
    return FALSE;
  }
}
