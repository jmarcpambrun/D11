<?php

namespace Drupal\maestro_ai_task\Plugin\MaestroAiTaskCapabilities;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_ai_task\MaestroAiTaskAPI\MaestroAiTaskAPI;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;

/**
 * Provides a 'MaestroAiTaskVision' Maestro AI Task Capability.
 * This capability uses the AI module's chat with image vision provider.
 * @MaestroAiTaskCapabilities(
 *   id = "MaestroAiTaskVision",
 *   ai_provider = "chat_with_image_vision",
 *   capability_description = @Translation("Manages the Chat with Image Vision operation type."),
 * )
 */
class MaestroAiTaskVision extends MaestroAiTaskCapabilitiesPluginBase implements MaestroAiTaskCapabilitiesInterface {

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
    // Allows the user to specify where the image is coming from.
    // This requires a field to enter in a URL, or field to specify which Maestro AI Task Storage Entity unique identifier to use.

    $form = [];

    $default_value = $this->task['data']['ai']['ai_vision_image_source'] ?? '';
    $form['ai_vision_image_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('What is the source of the image?'),
      '#default_value' => $default_value,
      '#options' => [
        'manual_url' => $this->t('Manually entered URL'),
        'maestro_ai_entity' => $this->t('Maestro AI Task Storage Entity'),
        'process_variable' => $this->t('Process Variable'),
        'local_file' => $this->t('Local File. The file will be uploaded as binary.'),
      ],
    ];
    
    $default_value = $this->task['data']['ai']['ai_vision_image_source_url'] ?? '';
    $form['ai_vision_image_source_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Manually entered URL'),
      '#description' => $this->t('A full URL to the image you wish to use Vision on.'),
      '#default_value' => $default_value,
      '#attributes' => [
        'placeholder' => 'https://example.com/the_image.png',
      ],
      '#states' => [
        'visible' => [
          'input[name^="ai_vision_image_source' => ['value' => 'manual_url'],
        ],
      ],
    ];

    
    $storage_entities = MaestroAiTaskAPI::getAiStorageEntityDefinitionsInTemplate($this->templateMachineName);
    if(count($storage_entities)) {
      // We have some storage entities configured.
      $options = ['' => $this->t('No entity storage chosen')];
      $options = array_merge($options, $storage_entities);
      $default_value = $this->task['data']['ai']['ai_vision_image_source_entity'] ?? '';
      $form['ai_vision_image_source_entity'] = [
        '#type' => 'select',
        '#title' => $this->t('Maestro AI Storage Entity'),
        '#description' => $this->t('The Maestro AI Storage entity that houses the image you wish to use Vision on.'),
        '#default_value' => $default_value,
        '#options' => $options,
        '#states' => [
          'visible' => [
            'input[name^="ai_vision_image_source' => ['value' => 'maestro_ai_entity'],
          ],
        ],
      ];
    }
    else {
      // No entites configured yet.  Keep the value blanked out and show our message.
      $form['ai_vision_image_source_entity_markup_container'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            'input[name^="ai_vision_image_source' => ['value' => 'maestro_ai_entity'],
          ],
        ],
      ];
      $form['ai_vision_image_source_entity_markup_container']['ai_vision_image_source_entity_markup'] = [
        '#markup' => 
          $this->t('<em>There are no Maestro AI Storage Entities created in this template yet.</em><br>') .
          $this->t('<em>You may have to configure the AI Storage Entity of this task first and come back to this option to select an entity.</em><br>'),
      ];
      $form['ai_vision_image_source_entity'] = [
        '#type' => 'hidden',
        '#value' => '',
      ];
    }

    $default_value = $this->task['data']['ai']['ai_vision_image_source_entity_manual'] ?? '';
    $form['ai_vision_image_source_entity_manual'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maestro AI Storage Entity not listed'),
      '#description' => $this->t('If you do have a Maestro AI Storage Entity that has been programmatically created, type the unique ID in here. Whatever you type in here takes precedence over any selected entity above.'),
      '#default_value' => $default_value,
      '#states' => [
        'visible' => [
          'input[name^="ai_vision_image_source' => ['value' => 'maestro_ai_entity'],
        ],
      ],
    ];

    $variables = MaestroEngine::getTemplateVariables($this->templateMachineName);
    $options = [
      '' => $this->t('Choose Process Variable'),
    ];
    foreach ($variables as $variableName => $arr) {
      $options[$variableName] = $variableName;
    }

    $default_value = $this->task['data']['ai']['ai_vision_image_source_pv'] ?? '';
    $form['ai_vision_image_source_pv'] = [
      '#type' => 'select',
      '#title' => $this->t('Maestro Process Variable'),
      '#description' => $this->t('The process variable that stores a URL to the image OR a Drupal File ID you wish to send to use Vision on.'),
      '#default_value' => $default_value,
      '#options' => $options,
      '#states' => [
        'visible' => [
          'input[name^="ai_vision_image_source' => ['value' => 'process_variable'],
        ],
      ],
    ];

    $default_value = $this->task['data']['ai']['ai_vision_image_source_file'] ?? '';
    $form['ai_vision_image_source_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('A local file.'),
      '#description' => $this->t('Path to a local file relative to your site\'s root folder.'),
      '#default_value' => $default_value,
      '#attributes' => [
        'placeholder' => 'sites/default/files/somefile.png',
      ],
      '#states' => [
        'visible' => [
          'input[name^="ai_vision_image_source' => ['value' => 'local_file'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    $task['data']['ai']['ai_vision_image_source'] = $form_state->getValue('ai_vision_image_source');
    $task['data']['ai']['ai_vision_image_source_url'] = $form_state->getValue('ai_vision_image_source_url');
    $task['data']['ai']['ai_vision_image_source_entity'] = $form_state->getValue('ai_vision_image_source_entity');
    $task['data']['ai']['ai_vision_image_source_entity_manual'] = $form_state->getValue('ai_vision_image_source_entity_manual');
    $task['data']['ai']['ai_vision_image_source_pv'] = $form_state->getValue('ai_vision_image_source_pv');
    $task['data']['ai']['ai_vision_image_source_file'] = $form_state->getValue('ai_vision_image_source_file');
    $task['data']['ai']['ai_return_format'] = ''; // Clear out the ai return format 
  }

  /**
   * {@inheritDoc}
   */
  public function validateMaestroAiTaskEditForm(array &$form, FormStateInterface $form_state) : void {

    $image_source = $form_state->getValue('ai_vision_image_source');
    if(!$image_source) {
      $form_state->setErrorByName('ai_vision_image_source', $this->t('The URL must not be empty and must start with "https://".'));
    }

    $image_url = $form_state->getValue('ai_vision_image_source_url');
    if ($image_source == 'manual_url' && (empty($image_url) || strpos($image_url, 'https://') !== 0)) {
      $form_state->setErrorByName('ai_vision_image_source_url', $this->t('The URL must not be empty and must start with "https://".'));
    }

    $source_entity = $form_state->getValue('ai_vision_image_source_entity');
    $manual_entity = $form_state->getValue('ai_vision_image_source_entity_manual');
    if ($image_source == 'maestro_ai_entity' && (!$source_entity && !$manual_entity)) {
      $form_state->setErrorByName('ai_vision_image_source', $this->t('You must choose an AI Storage entity. If one does not exist, create it and select it here or use a manually entered entity ID. Otherwise use a URL.'));
    }

    $pv = $form_state->getValue('ai_vision_image_source_pv');
    if ($image_source == 'process_variable' && !$pv) {
      $form_state->setErrorByName('ai_vision_image_source_pv', $this->t('You must choose a process variable.'));
    }

    
    $local_file = $form_state->getValue('ai_vision_image_source_file');
    if ($image_source == 'local_file') {
      if(!$local_file) {
        $form_state->setErrorByName('ai_vision_image_source_file', $this->t('You must fill in a local file.'));
      }
      // We could validate that the local file doesn't exist, however this file could be created by the workflow
      // as it progresses.  Omitting that validation.
    }

  }

  /**
   * {@inheritDoc}
   */
  public function execute() : ?string {
    $responseText = '';
    /** @var \Drupal\ai\AiProviderPluginManager $service */
    $service = \Drupal::service('ai.provider');
    $sets = $service->getDefaultProviderForOperationType('chat_with_image_vision');
    $provider = $service->createInstance($sets['provider_id']);
    $chat_message = new ChatMessage('user', $this->prompt);
    // Based on the settings of the task, we need to be able to either set the image from a URL, binary or from a file.
    $source = $this->task['data']['ai']['ai_vision_image_source'] ?? NULL;
    
    if($source) {
      switch($source) {
        case 'manual_url':
          $url = $this->task['data']['ai']['ai_vision_image_source_url'] ?? NULL;
          if($url) {
            $chat_message->setImageFromUrl($url);
          }
          else {
            $chat_message = NULL;
          }
          break;

        case 'maestro_ai_entity':
          // Store the entity in the private files.
          $source_entity = $this->task['data']['ai']['ai_vision_image_source_entity'];
          $converted_image_array = MaestroAiTaskAPI::convertBase64AiStorageImageToFile($source_entity, $this->processID);
          if(count($converted_image_array) && array_key_exists('file', $converted_image_array)) {
            $chat_message->setImageFromUri($converted_image_array['file']);
          }
          else {
            $chat_message = NULL;
          }
          break;

        case 'process_variable':
          // PV can be a url or perhaps, a file ID.
          $pv = $this->task['data']['ai']['ai_vision_image_source_pv'] ?? NULL;
          if($pv) {
            $pv_value = MaestroEngine::getProcessVariable($pv, $this->processID);
            if(intval($pv_value) == $pv_value) { // File ID?  It's an integer, so must be
              $file = File::load($pv_value);
              if($file) {
                $file_uri = $file->getFileUri();
                $file_path = \Drupal::service('file_system')->realpath($file_uri);
                if (file_exists($file_path)) {
                  $chat_message->setImageFromBinary(file_get_contents($file_path), $file->getMimeType());
                }
                else {
                  $chat_message = NULL;
                  \Drupal::logger('MaestroAiTaskVision')->error($this->t('Unable to load process variable file id: :id.', [':id' => $pv_value]));
                  $responseText .= 'Unable to load process variable file id: '. $pv_value . '.';
                }
              }
              else {
                $chat_message = NULL;
                \Drupal::logger('MaestroAiTaskVision')->error($this->t('Unable to load process variable file id: :id.', [':id' => $pv_value]));
                $responseText .= 'Unable to load process variable file id: '. $pv_value . '.';
              }
            }
            else { // String URL.
              // Just set it to the PV value.
              $chat_message->setImageFromUrl($pv_value);
            }
  
            if($pv_value === FALSE) {
              $chat_message = NULL;
            }
          }
          else {
            $chat_message = NULL;
          }
          break;

        case 'local_file':
          $local_file = $this->task['data']['ai']['ai_vision_image_source_file'] ?? NULL;
          if($local_file) {
            if(file_exists($local_file)) {
              $chat_message->setImageFromBinary(file_get_contents($local_file), mime_content_type($local_file));
            }
            else {
              $chat_message = NULL;
              \Drupal::logger('MaestroAiTaskVision')->error($this->t('No local file'));
              $responseText .= 'Specified local file does not exist.';
            }
          }
          else {
            $chat_message = NULL;
          }
          break;

        default:
          // Something's gone wrong.  Blank out the chat message and hold up the operation.
          $chat_message = NULL;
          break;
      }
      
      if($chat_message) {
        try {
          $messages = new ChatInput([
            $chat_message,  
          ]);
          
          $message = $provider->chat($messages, $sets['model_id'], ['maestro-ai-task-chat'])->getNormalized();
          $responseText .= $message->getText() ?? NULL;
        }
        catch(\Exception $e) {
          // Response text could go into a PV or entity.  So not much use in translating it.
          \Drupal::logger('MaestroAiTaskVision')->error($e->getMessage());
          $responseText .= 'There was an issue in the Maestro AI Task LLM call for Vision.';
          $this->taskStatus = TASK_STATUS_CANCEL;
        }
        
      }
      else {
        // Well, we have a serious issue.  No Chat message, so we set the status of this task.
        $this->taskStatus = TASK_STATUS_CANCEL;
        $responseText .= 'There is an issue with the source image configuration of your Maestro AI Task configuration for Vision.';
      }
    }
    
    return Xss::filter($responseText);
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
