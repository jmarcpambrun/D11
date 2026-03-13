<?php

namespace Drupal\maestro_ai_task\Plugin\MaestroAiTaskCapabilities;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\file\Entity\File;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_ai_task\MaestroAiTaskAPI\MaestroAiTaskAPI;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;

/**
 * Provides a 'MaestroAiSpeechToText' Maestro AI Task Capability.
 * This capability uses the AI module's speech to text provider.
 * 
 * @MaestroAiTaskCapabilities(
 *   id = "MaestroAiSpeechToText",
 *   ai_provider = "speech_to_text",
 *   capability_description = @Translation("Speech to Text operation type, returning text as a string."),
 * )
 */
class MaestroAiSpeechToText extends MaestroAiTaskCapabilitiesPluginBase implements MaestroAiTaskCapabilitiesInterface {

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
    $form = [];
    
    $select_options = ['' => $this->t('No language chosen')];
    $languages = LanguageManager::getStandardLanguageList();
    asort($languages);
    foreach ($languages as $langcode => $language_names) {
      if(strpos($langcode, '-') !== FALSE) {
        continue;
      }
      $select_options[$langcode] = $language_names[0];
    }
    $default_value = $this->task['data']['ai']['ai_speech_to_text_language'] ?? 'en';
    $form['ai_speech_to_text_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $select_options,
      '#description' => $this->t('Defaults to English. The language of the audio file. Supplying the input language in ISO-639-1 format will improve accuracy and latency. '),
      '#default_value' => $default_value,
    ];

    $default_value = $this->task['data']['ai']['ai_speech_to_text_language_missing'] ?? '';
    $form['ai_speech_to_text_language_missing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Can\'t find your language above? Type in the ISO-639-1 code here.'),
      '#description' => $this->t('Leave this blank if you\'ve found your language in the drop down above. Whatever you type in here will be used as the language code for the AI operation.'),
      '#default_value' => $default_value,
    ];

    $default_value = $this->task['data']['ai']['ai_speech_to_text_source'] ?? '';
    $form['ai_speech_to_text_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('What is the source of the speech file?'),
      '#default_value' => $default_value,
      '#options' => [
        'manual_url' => $this->t('Manually entered URL'),
        'maestro_ai_entity' => $this->t('Maestro AI Task Storage Entity'),
        'process_variable' => $this->t('Process Variable'),
        'local_file' => $this->t('Local File. The file will be uploaded as binary.'),
      ],
    ];

    $default_value = $this->task['data']['ai']['ai_speech_to_text_source_url'] ?? '';
    $form['ai_speech_to_text_source_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Manually entered URL'),
      '#description' => $this->t('A full URL to the speech file you wish to use.'),
      '#default_value' => $default_value,
      '#attributes' => [
        'placeholder' => 'https://example.com/the_image.png',
      ],
      '#states' => [
        'visible' => [
          'input[name^="ai_speech_to_text_source' => ['value' => 'manual_url'],
        ],
      ],
    ];
    
    $storage_entities = MaestroAiTaskAPI::getAiStorageEntityDefinitionsInTemplate($this->templateMachineName);
    if(count($storage_entities)) {
      // We have some storage entities configured.
      $options = ['' => $this->t('No entity storage chosen')];
      $options = array_merge($options, $storage_entities);
      $default_value = $this->task['data']['ai']['ai_speech_to_text_source_entity'] ?? '';
      $form['ai_speech_to_text_source_entity'] = [
        '#type' => 'select',
        '#title' => $this->t('Maestro AI Storage Entity'),
        '#description' => $this->t('The Maestro AI Storage entity that houses the speech file you wish to use.'),
        '#default_value' => $default_value,
        '#options' => $options,
        '#states' => [
          'visible' => [
            'input[name^="ai_speech_to_text_source' => ['value' => 'maestro_ai_entity'],
          ],
        ],
      ];
    }
    else {
      // No entites configured yet.  Keep the value blanked out and show our message.
      $form['ai_speech_to_text_source_entity_markup_container'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            'input[name^="ai_speech_to_text_source' => ['value' => 'maestro_ai_entity'],
          ],
        ],
      ];
      $form['ai_speech_to_text_source_entity_markup_container']['ai_speech_to_text_source_entity_markup'] = [
        '#markup' => 
          $this->t('<em>There are no Maestro AI Storage Entities created in this template yet.</em><br>') .
          $this->t('<em>You may have to configure the AI Storage Entity of this task first and come back to this option to select an entity.</em><br>'),
      ];
      $form['ai_speech_to_text_source_entity'] = [
        '#type' => 'hidden',
        '#value' => '',
      ];
    }

    $default_value = $this->task['data']['ai']['ai_speech_to_text_source_entity_manual'] ?? '';
    $form['ai_speech_to_text_source_entity_manual'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maestro AI Storage Entity not listed'),
      '#description' => $this->t('If you do have a Maestro AI Storage Entity that has been programmatically created, type the unique ID in here. Whatever you type in here takes precedence over any selected entity above.'),
      '#default_value' => $default_value,
      '#states' => [
        'visible' => [
          'input[name^="ai_speech_to_text_source' => ['value' => 'maestro_ai_entity'],
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

    $default_value = $this->task['ai_speech_to_text_source_pv'] ?? '';
    $form['ai_speech_to_text_source_pv'] = [
      '#type' => 'select',
      '#title' => $this->t('Maestro Process Variable'),
      '#description' => $this->t('The process variable that stores a URL to the speech file OR a Drupal File ID you wish to use.'),
      '#default_value' => $default_value,
      '#options' => $options,
      '#states' => [
        'visible' => [
          'input[name^="ai_speech_to_text_source' => ['value' => 'process_variable'],
        ],
      ],
    ];

    $default_value = $this->task['ai_speech_to_text_source_file'] ?? '';
    $form['ai_speech_to_text_source_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('A local file.'),
      '#description' => $this->t('Path to a local file relative to your site\'s root folder.'),
      '#default_value' => $default_value,
      '#attributes' => [
        'placeholder' => 'sites/default/files/somefile.png',
      ],
      '#states' => [
        'visible' => [
          'input[name^="ai_speech_to_text_source' => ['value' => 'local_file'],
        ],
      ],
    ];

    return $form;
  }


  /**
   * {@inheritDoc}
   */
  public function validateMaestroAiTaskEditForm(array &$form, FormStateInterface $form_state) : void {
    $language = $form_state->getValue('ai_speech_to_text_language');
    if(!$language) {
      $form_state->setErrorByName('ai_speech_to_text_language', $this->t('Provide the base language of the speech file.'));
    }
    
    $image_source = $form_state->getValue('ai_speech_to_text_source');
    if(!$image_source) {
      $form_state->setErrorByName('ai_speech_to_text_source', $this->t('The URL must not be empty and must start with "https://".'));
    }

    $image_url = $form_state->getValue('ai_speech_to_text_source_url');
    if ($image_source == 'manual_url' && (empty($image_url) || strpos($image_url, 'https://') !== 0)) {
      $form_state->setErrorByName('ai_speech_to_text_source_url', $this->t('The URL must not be empty and must start with "https://".'));
    }

    $source_entity = $form_state->getValue('ai_speech_to_text_source_entity');
    $manual_entity = $form_state->getValue('ai_speech_to_text_source_entity_manual');
    if ($image_source == 'maestro_ai_entity' && (!$source_entity && !$manual_entity)) {
      $form_state->setErrorByName('ai_speech_to_text_source', $this->t('You must choose an AI Storage entity. If one does not exist, create it and select it here or use a manually entered entity ID. Otherwise use a URL.'));
    }

    $pv = $form_state->getValue('ai_speech_to_text_source_pv');
    if ($image_source == 'process_variable' && !$pv) {
      $form_state->setErrorByName('ai_speech_to_text_source_pv', $this->t('You must choose a process variable.'));
    }
    
    $local_file = $form_state->getValue('ai_speech_to_text_source_file');
    if ($image_source == 'local_file') {
      if(!$local_file) {
        $form_state->setErrorByName('ai_speech_to_text_source_file', $this->t('You must fill in a local file.'));
      }
      // We could validate that the local file doesn't exist, however this file could be created by the workflow
      // as it progresses.  Omitting that validation.
    }

  }

    /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    $task['data']['ai']['ai_speech_to_text_language'] = $form_state->getValue('ai_speech_to_text_language');
    $task['data']['ai']['ai_speech_to_text_language_missing'] = $form_state->getValue('ai_speech_to_text_language_missing');
    $task['data']['ai']['ai_speech_to_text_source'] = $form_state->getValue('ai_speech_to_text_source');
    $task['data']['ai']['ai_speech_to_text_source_url'] = $form_state->getValue('ai_speech_to_text_source_url');
    $task['data']['ai']['ai_speech_to_text_source_entity'] = $form_state->getValue('ai_speech_to_text_source_entity');
    $task['data']['ai']['ai_speech_to_text_source_entity_manual'] = $form_state->getValue('ai_speech_to_text_source_entity_manual');
    $task['data']['ai']['ai_speech_to_text_source_pv'] = $form_state->getValue('ai_speech_to_text_source_pv');
    $task['data']['ai']['ai_speech_to_text_source_file'] = $form_state->getValue('ai_speech_to_text_source_file');
    $task['data']['ai']['ai_return_format'] = ''; // Clear out the ai return format 
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : string {
    $responseText = NULL;
    /** @var \Drupal\ai\AiProviderPluginManager $service */
    $service = \Drupal::service('ai.provider');
    $sets = $service->getDefaultProviderForOperationType('speech_to_text');
    /** @var \Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider $provider */
    $provider = $service->createInstance($sets['provider_id']);
    $language = $task['data']['ai']['ai_speech_to_text_language'] ?? 'en';
    $missing_language = $task['data']['ai']['ai_speech_to_text_language_missing'] ?? NULL;
    if($missing_language) {
      $language = $missing_language;
    }
    $source = $this->task['ai_speech_to_text_source'] ?? NULL;
    $audio = NULL;
    $mime = '';
    if($source) {
      switch($source) {
        case 'manual_url':
          $url = $this->task['ai_speech_to_text_source_url'] ?? NULL;
          if($url) {
            $audio = file_get_contents($url);
            // Not setting the mime type as we'd have to set it locally.  Let's see if AI can figure it out.
          }
          break;

        case 'maestro_ai_entity':
          // Store the entity in the private files.
          $source_entity = $this->task['ai_speech_to_text_source_entity'];
          $manual_entity = $this->task['ai_speech_to_text_source_entity_manual'] ?? NULL;
          // The manual entity takes precedence over the selected entity.
          // This is useful for programmatically created entities that are not in the template.
          if($manual_entity) {
            $source_entity = $manual_entity;
          }
          $audio = MaestroAiTaskAPI::getAiStorageValueByUniqueId($source_entity, $this->processID);
          break;

        case 'process_variable':
          // PV can be a url or perhaps, a file ID.
          $pv = $this->task['ai_speech_to_text_source_pv'] ?? NULL;
          if($pv) {
            $pv_value = MaestroEngine::getProcessVariable($pv, $this->processID);
            if(intval($pv_value) == $pv_value) { // File ID?  It's an integer, so must be
              $file = File::load($pv_value);
              if($file) {
                $file_uri = $file->getFileUri();
                $file_path = \Drupal::service('file_system')->realpath($file_uri);
                if (file_exists($file_path)) {
                  $audio = file_get_contents($file_path);
                  $mime = mime_content_type($file_path);
                }
                else {
                  \Drupal::logger('MaestroAiTaskSpeechToText')->error($this->t('Unable to load process variable file id: :id.', [':id' => $pv_value]));
                  $responseText .= 'Unable to load process variable file id: '. $pv_value . '.';
                }
              }
              else {
                \Drupal::logger('MaestroAiTaskSpeechToText')->error($this->t('Unable to load process variable file id: :id.', [':id' => $pv_value]));
                $responseText .= 'Unable to load process variable file id: '. $pv_value . '.';
              }
            }
            else { // String URL.
              // Just set it to the PV value.
              $audio = file_get_contents($pv_value);
              $mime = mime_content_type($pv_value);
            }
          }
          break;

        case 'local_file':
          $local_file = $this->task['ai_speech_to_text_source_file'] ?? NULL;
          if($local_file) {
            if(file_exists($local_file)) {
              $audio = file_get_contents($local_file);
              $mime = mime_content_type($local_file);
            }
            else {
              \Drupal::logger('MaestroAiTaskSpeechToText')->error($this->t('No local file'));
              $responseText .= 'Specified local file does not exist.';
            }
          }
          
          break;

        default:
          // Something's gone wrong.  Blank out the chat message and hold up the operation.
          $audio = NULL;
          break;
      }
    }

    if($audio) {
      // For this provider, the prompt is set in the config array.
      $config = [
        'language' => $language, // Default to english
        'prompt' => $this->prompt,
        'response_format' => 'text', // We preselect text for this as we're not returning JSON as per our capability's name
        'temperature' => 0,
      ];
      $provider->setConfiguration($config);
      $normalized_file = new AudioFile($audio, $mime);
      $audio = new SpeechToTextInput($normalized_file);
      /** @var \Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput $message */
      $message =  $provider->speechToText($audio, $sets['model_id'], ['maestro-ai-task-speech-to-text']);
      // Don't trust the response from the AI provider.  We need to sanitize it.
      $normalized_message = $message->getNormalized() ?? NULL;
      if($normalized_message) {
        $responseText = Xss::filter($normalized_message) ?? NULL;
      }
    }
    else {
      \Drupal::logger('MaestroAiTaskSpeechToText')->error($this->t('Return from AI call does not have any value'));
      $responseText = NULL;
    }
    
    return $responseText;
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
