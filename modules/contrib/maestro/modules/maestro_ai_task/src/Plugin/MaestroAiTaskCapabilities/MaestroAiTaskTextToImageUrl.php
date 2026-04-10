<?php

namespace Drupal\maestro_ai_task\Plugin\MaestroAiTaskCapabilities;

use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;
use Drupal\core\File\FilesystemInterface;

/**
 * Provides a 'MaestroAiTaskTextToImageUrl' Maestro AI Task Capability.
 * This capability uses the AI module's text to image provider.
 * TODO: 
 *    Basic Sanitization of the return image done via PHP's getimagesizefromstring. 
 *    GD/ImageMagick could be used to further sanitize the return.
 * 
 * 
 * @MaestroAiTaskCapabilities(
 *   id = "MaestroAiTaskTextToImageUrl",
 *   ai_provider = "text_to_image",
 *   capability_description = @Translation("Text to Image operation type, saving the image as a physical file and returning the URL."),
 * )
 */
class MaestroAiTaskTextToImageUrl extends MaestroAiTaskCapabilitiesPluginBase implements MaestroAiTaskCapabilitiesInterface {

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
  public function getMaestroAiTaskConfigFormElements() : array {
    // We can save this image in either public or private file storage.
    $form = [];
    // Provide a select box to choose public:: or private::

    $default_value = $this->task['data']['ai']['ai_text_to_image_file_storage'] ?? '';
    $form['ai_text_to_image_file_storage'] = [
      '#type' => 'select',
      '#title' => $this->t('File Storage'),
      '#options' => [
        'public://' => $this->t('Public'),
        'private://' => $this->t('Private'),
      ],
      '#description' => $this->t('The public or private filesystem to save the image to.'),
      '#description_display' => 'after',
      '#default_value' => 'private://',
      '#required' => FALSE,
    ];
   
    // Now a simple textfield so they can enter a filename.
    $default_value = $this->task['data']['ai']['ai_text_to_image_filename'] ?? '';
    $form['ai_text_to_image_filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filename without extension'),
      '#description' => 
        $this->t('The filename to save the image as without an extension.<br>') . 
        $this->t('<strong>Do not include a path as you will specify a path below.</strong><br>') .
        $this->t('The save routine will attempt to create the appropriate image extension.<br>') .  
        $this->t('<strong>Please determine if your AI model can only support specific output image types (example: only PNG)</strong>.<br>') . 
        $this->t('Tokens are allowed.'),
      '#description_display' => 'after',
      '#default_value' => $default_value,
      '#required' => FALSE,
    ];

    // Now a simple textfield so they can enter a path
    $default_value = $this->task['data']['ai']['ai_text_to_image_path'] ?? '';
    $form['ai_text_to_image_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('The path to save the image to (It will be created if it does not exist). Tokens are allowed.'),
      '#description_display' => 'after',
      '#default_value' => $default_value,
      '#required' => FALSE,
    ];

     // We have a few tokens we can use here
     if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['maestro'],
      ];
    }
    else {
      $form['token_tree'] = [
        '#plain_text' => $this->t('Enabling the Token module will reveal the replacable tokens.'),
      ];
    }

    $default_value = $this->task['data']['ai']['ai_text_to_image_url_or_path'] ?? 0;
    $form['ai_text_to_image_url_or_path'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Return as browser accessible image?'),
      '#description' => 
        $this->t('When checked, return the image URL as a browser accessible link if possible. Otherwise returns an internal image path.<br>') . 
        $this->t('<strong>You will ALWAYS get an internal image path (not a browser accessble URL) when your File Storage option is set to Private.</strong>'),
      '#description_display' => 'after',
      '#default_value' => $default_value,
      '#required' => FALSE,
    ];

   return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateMaestroAiTaskEditForm(array &$form, FormStateInterface $form_state) : void {
    // We need to validate that our fields for filename, path and file storage are plausible.

    $file_storage = $form_state->getValue('ai_text_to_image_file_storage');
    $file_name = $form_state->getValue('ai_text_to_image_filename');
    $file_path = $form_state->getValue('ai_text_to_image_path');

    if(!$file_storage) {
      $form_state->setErrorByName('ai_text_to_image_file_storage', $this->t('File storage is required.'));
    } 
    if(!$file_name) {
      $form_state->setErrorByName('ai_text_to_image_filename', $this->t('Filename is required.'));
    } 
    if(!$file_path) {
      $form_state->setErrorByName('ai_text_to_image_path', $this->t('Path is required.'));
    }

    // In our execution routine, the folder path will be created for the user.

  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    $task['data']['ai']['ai_text_to_image_file_storage'] = $form_state->getValue('ai_text_to_image_file_storage');
    $task['data']['ai']['ai_text_to_image_filename'] = $form_state->getValue('ai_text_to_image_filename');
    $task['data']['ai']['ai_text_to_image_path'] = $form_state->getValue('ai_text_to_image_path');
    $task['data']['ai']['ai_text_to_image_url_or_path'] = $form_state->getValue('ai_text_to_image_url_or_path');
    $task['data']['ai']['ai_return_format'] = ''; // Clear out the ai return format 
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : ?string {
    /** @var \Drupal\ai\AiProviderPluginManager $service */
    $service = \Drupal::service('ai.provider');
    $sets = $service->getDefaultProviderForOperationType('text_to_image');
    $provider = $service->createInstance($sets['provider_id']);
    $input = new TextToImageInput($this->prompt);

    // How are we returning a successful image?
    $return_as_public_url = $this->task['data']['ai']['ai_text_to_image_url_or_path'] ?? 0; // 1 for browser url, 0 for path

    // Use the task configuration to set the file URL.
    $file_storage = $this->task['data']['ai']['ai_text_to_image_file_storage']; // private:// or public://
    // Make sure $file_path ends in a slash and doesn't begin with one.
    $file_path = trim(trim($this->task['data']['ai']['ai_text_to_image_path'], '/'), '\\') . '/';
    $filename = $this->task['data']['ai']['ai_text_to_image_filename'];

    /** @var \Drupal\Core\Utility\Token $tokenService */
    $tokenService = \Drupal::token();
    $file_path = $tokenService->replacePlain(
      $file_path,
      [
        'maestro' => [
          'task' => $this->task, 
          'queueID' => $this->queueID,
          'processID' => $this->processID,
          ]
      ]);

    $filename = $tokenService->replacePlain(
      $filename,
      [
        'maestro' => [
          'task' => $this->task, 
          'queueID' => $this->queueID,
          'processID' => $this->processID,
          ]
      ]);

    $file_url = $file_storage . $file_path . $filename;
    $file_system = \Drupal::service('file_system');
    $file_path_exists = $file_system->realpath($file_url);
    if(!$file_path_exists) {
      // Create the folder
      $folder_path = $file_storage . $file_path;
      $file_system->prepareDirectory($folder_path, FileSystemInterface::CREATE_DIRECTORY);
    }
    /** @var \Drupal\ai\OperationType\TextToImage\TextToImageOutput $return_images */
    $return_images = $provider->textToImage($input, $sets['model_id'], ['maestro-ai-task-text-to-image']);
    /** @var \Drupal\ai\OperationType\GenericType\ImageFile */
    $image = $return_images->getNormalized()[0] ?? NULL;
    if($image) {
      $image_binary = $image->getAsBinary();
      // Some rudimentary sanitization of the return image
      if(getimagesizefromstring($image_binary) === FALSE) {
        return 'invalid';
      }
      $extension = $image->getFileType();
      $file_url = $file_url . '.' . $extension;
      $save_operation = @file_put_contents($file_url, $image_binary);
      if($save_operation === FALSE) {
        $file_url = NULL;
      }
      elseif($file_storage == 'public://' && $return_as_public_url) {
        // We can create an actual visible URL but only if PUBLIC and only if we've said so in the task config.
        $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_url);
      }
    }
    else {
      $file_url = NULL;
    }
    return $file_url;
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
