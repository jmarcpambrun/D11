<?php

namespace Drupal\maestro_ai_task\Plugin\EngineTasks;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\MaestroTaskTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\maestro\Engine\Exception\MaestroGeneralException;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\maestro_ai_task\Entity\MaestroAIStorage;
use Drupal\maestro_ai_task\MaestroAiTaskAPI\MaestroAiTaskAPI;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;

/**
 * Maestro AI Task Plugin.
 *
 * The plugin annotations below should include:
 * id: The task type ID for this task.  For Maestro tasks, this is Maestro[TaskType].
 *     So for example, the start task shipped by Maestro is MaestroStart.
 *     The Maestro End task has an id of MaestroEnd
 *     Those task IDs are what's used in the engine when a task is injected into the queue.
 *
 * @Plugin(
 *   id = "MaestroAITask",
 *   task_description = @Translation("The Maestro Engine's AI task."),
 * )
 */
class MaestroAITask extends PluginBase implements MaestroEngineTaskInterface {

  use MaestroTaskTrait;

  /**
   * Constructor.
   */
  public function __construct($configuration = NULL) {
    if (is_array($configuration)) {
      $this->processID = $configuration[0];
      $this->queueID = $configuration[1];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isInteractive() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription() {
    return $this->t('AI Task');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('AI Task.');
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\Component\Plugin\PluginBase::getPluginId()
   */
  public function getPluginId() {
    return 'MaestroAITask';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    // AI tasks will be this Dodger Blue colour.
    return '#04BDFF';
  }

  /**
   * Part of the ExecutableInterface.
   *
   * Execution of the Batch Function task will use the handler for this task as the executable function.
   * The handler must return TRUE in order for this function to be completed by the engine.
   * We simply pass the return boolean value back from the called handler to the engine for processing.
   * {@inheritdoc}.
   */
  public function execute() {
    $responseValue = NULL;
    $returnValue = FALSE;
    $returnStatus = FALSE;
    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($this->processID);
    $taskMachineName = MaestroEngine::getTaskIdFromQueueId($this->queueID);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskMachineName);
    $taskData = $task['data']['ai'] ?? [];
    $queueRecord = MaestroEngine::getQueueEntryById($this->queueID);
    if ($queueRecord) {
      // Get the AI config.
      $config = \Drupal::config('maestro_ai_task.settings');

      // Are we just doing some testing?  The Config allows a sample response.
      $is_testing = $config->get('ai_testing') ?? FALSE;

      // But we also allow the specific task to have a testing mode.
      $is_task_testing = $taskData['ai_testing'] ?? FALSE;

      // Now add in the task's configuration.
      $prompt = $taskData['ai_prompt'] ?? '';
      $return_data_as = $taskData['ai_return_format'] ?? '';
      $custom_return_format = $taskData['ai_return_custom_format'] ?? '';
      $return_into = $taskData['ai_return_into'] ?? '';
      $return_into_pv = $taskData['ai_return_into_process_variable'] ?? '';
      $return_into_ai_entity = $taskData['ai_return_into_ai_variable'] ?? '';
      $log_return = $taskData['log_ai_return'] ?? FALSE;
      $hold_task_on_null = $taskData['hold_task_on_null'] ?? FALSE;
      $initiator = MaestroEngine::getProcessVariable('initiator', $this->processID);

      // Run the instructions through the token processor.
      $tokenService = \Drupal::token();
      $prompt = $tokenService->replace(
        $prompt,
        [
          'maestro' => 
            [
              'task' => $task, 
              'queueID' => $this->queueID,
              'processID' => $this->processID,
            ]
        ]
      );
      // Make sure the whitespace trimmed $ai_instructions ends with a period.
      $prompt = trim($prompt);
      $return_data_as_prompt = '';
      switch ($return_data_as) {
        case 'json_yes_no':
          $return_data_as_prompt .= 'Your response will be returned as raw Json without any formatting or code block markers with the output in a key-value pair with the key set to "result" and the value set to  "Yes" for true/success or "No" for false/failure.';
          $return_data_as_prompt .= 'You will not explain the response. Just provide the Yes or No response';
          break;

        case 'json_true_false':
          $return_data_as_prompt .= 'Your response will be returned as raw Json without any formatting or code block markers with the output in a key-value pair with the key set to "result" and the value set to "true" for true/success or "false" for false/failure.';
          $return_data_as_prompt .= 'You will not explain the response. Just provide the ture or false JSON response';
          break;

        case 'custom':
          $return_data_as_prompt .= $custom_return_format;
          break;

        // By default true/false in json.
        default:
          $return_data_as_prompt .= 'Your response will be returned as raw Json without any formatting or code block markers with the output in a key-value pair with the key set to "result" and the value set to  "true" for true/success or "false" for false/failure.';
          $return_data_as_prompt .= 'You will not explain the response. Just provide the true or false response';
          break;
      }

      // If we're trying to process in task, and our config is wrong, don't even execute the AI bot - save tokens.
      // This should be detected in the validation routine, however, in the event this is imported, do the detection here.
      if ($return_into == 'process_in_task' && $return_data_as != 'json_yes_no' && $return_data_as != 'json_true_false') {
        // We can't finish this task.  Drop an error in the log.
        $returnValue = FALSE;
        \Drupal::messenger()->addError($this->t('Error:  Unable to complete the task due to the return not being boolean.'));
        throw new MaestroGeneralException('Unable to complete the task due to the return not being boolean');
      }
      else {
        // Now we execute
        // But are we testing?  If not, execute the AI Call, if so, just return simple true/false type data.
        if (!$is_testing && !$is_task_testing) {
          $responseValue = NULL;
          $configured_provider = $taskData['ai_provider'] ?? NULL;
          
          // Create an instance of this capability, sending the task array, template machine name, queue ID, process ID and the configured prompt to the constructor.
          /** @var MaestroAiTaskCapabilitiesPluginBase $maestro_capability */
          $maestro_capability = MaestroAiTaskAPI::createMaestroAiTaskCapabilityPlugin(
            $configured_provider, 
            [
              'task' => $task, 
              'templateMachineName' => $templateMachineName, 
              'queueID' => $this->queueID, 
              'processID' => $this->processID, 
              'prompt' => $prompt,
            ]
          );

          if($maestro_capability) {
            // We have a valid Maestro AI Task capability.  Let's execute.
            // Does this capability allow for a customizable return data prompt?
            if($maestro_capability->allowConfigurableReturnFormat()) {
              $prompt = $prompt . $return_data_as_prompt;
              $maestro_capability->setPrompt($prompt); // Reset the prompt before execution.
            }
            
            $responseValue = $maestro_capability->execute(); // $responseValue can be a string or NULL.
            // Now set the execution status of this task based on the Maestro AI Task Capability's execution.
            $this->executionStatus = $maestro_capability->getTaskStatus();
          }
          
          if ($log_return) {
            \Drupal::logger('maestro_ai_task')->info($responseValue);
          }
        }
        else {
          // This is the global testing setting.
          if ($is_testing) {
            $responseValue = $config->get('ai_testing_response');
          }
          // This is the task specific setting.
          if ($is_task_testing) {
            $responseValue = $taskData['ai_testing_response'];
          }
        }

        // Now, where to store the data.
        switch ($return_into) {
          case 'process_in_task':
            try {
              $json = Json::decode($responseValue);
              $result = $json['result'] ?? 'false';
              // $returnValue is already set to false, no need to set it again.
              if (strtolower($result) == 'true' || strtolower($result) == 'yes') {
                $returnValue = TRUE;
              }
              else {
                // False/No or something completely different.  Let's set the status to cancelled so that we
                // can use an IF task after this one to branch if required.
                $this->executionStatus = TASK_STATUS_CANCEL;
                $returnValue = TRUE;
              }
            }
            catch (InvalidDataTypeException $e) {
              // We can't finish this task.  Drop an error in the log.  Setting to false to be sure.
              $returnValue = FALSE;
              \Drupal::messenger()->addError($this->t('Error:  Unable to complete the task due to the return not being able to decode the JSON response.'));
              throw new MaestroGeneralException('Unable to complete the task due to the return not being able to decode the JSON response');
            }
            // And that's it!  AI has completed the task for us by returning true/yes.
            break;

          case 'process_variable':
            // We set the output responseValue into the provided process variable $return_into_pv.
            // We will truncate the response value to 255 characters to avoid sql issues
            if(strlen($responseValue) > 255) {
              $responseValue = substr($responseValue, 0, 255);
            }
            MaestroEngine::setProcessVariable($return_into_pv, $responseValue, $this->processID);
            $returnValue = TRUE;
            break;

          case 'ai_task_entity':
            // We set the output responseValue into a new instance of the Maestro AI Storage Entity.
            // We can retrieve the AI task's entity via the api.
            // First, see if this entity already exists for this particular process.
            $existing_entity = MaestroAiTaskAPI::getAiStorageEntityByUniqueID($return_into_ai_entity, $this->processID);
            if ($existing_entity && count($existing_entity)) {
              $existing_entity = current($existing_entity);
              // Update the existing entity.
              /** @var \Drupal\maestro_ai_task\Entity\MaestroAIStorage $existing_entity */
              $existing_entity->set('queue_id', $this->queueID);
              $existing_entity->set('ai_storage', ['response' => $responseValue]);
              $existing_entity->save();
              $returnValue = TRUE;
            }
            else {
              $aiStorageEntity = MaestroAIStorage::create([
                'machine_name' => $return_into_ai_entity,
                'task_id' => $templateMachineName,
                'queue_id' => $this->queueID,
                'process_id' => $this->processID,
                'ai_storage' => ['response' => $responseValue],
              // We don't have any context for who the user is unless we specifically need to set this in the settings.
                'uid' => 0,
                'created' => time(),
              ]);
              if ($aiStorageEntity) {
                $aiStorageEntity->save();
                $returnValue = TRUE;
              }
            }
            
            break;
        }
      }
    }
    // True or false to complete the task.
    // but if $hold_task_on_null specifies that we do not return when the response is NULL/FALSE, then we return FALSE
    if($hold_task_on_null && is_null($responseValue)) {
      $returnValue = FALSE;
    }

    return $returnValue;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {

  }

  /**
   * {@inheritdoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    /** @var FormStateInterface $form_state */
    $form_state = $task['form_state'];

    $task_ai = $task['data']['ai'] ?? [];

    // The $maestro_capability will be a Plugin instantiation if/when we have a capability chosen.
    $maestro_capability = NULL;

    // This variable is used later in this form based on whether our Maestro AI Task Capability allows
    // for custom return formats.  For some AI calls, this is irrelevant.
    $allow_return_format = NULL;

    $ai_config = \Drupal::config('maestro_ai_task.settings');

    // Set up our Maestro AI Task Capabilities plugin manager.
    $ai_task_plugin_manager = \Drupal::service('plugin.manager.maestro_ai_task_capabilities');
    $implemented_maestro_ai_task_capabilities = $ai_task_plugin_manager->getDefinitions(); // Array of available Capability plugins we offer.

    $global_testing_on_text = '';
    $global_testing = $ai_config->get('ai_testing') ?? 0;
    if ($global_testing) {
      $global_testing_on_text = '<div class="messages messages--error">' . $this->t('The Maestro AI Task global setting for Test Mode is on. This will return the testing output values for all AI tasks in this workflow.') . '</div>';
    }
    $form = [
      '#markup' => $this->t('Maestro AI Task Edit') . $global_testing_on_text,
    ];

    $form['hold_task_on_null'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hold this task open if the AI response IS NULL?'),
      '#description' => 
        $this->t('When checked, if the return from AI is NULL, this task will not be completed.  The task will stay resident in the Maestro Queue and will be executed on subsequent Orchestrator runs.<br>') .
        $this->t('<strong>By allowing the task to stay active in the Maestro Queue, Maestro will continue to call your AI provider which may incurr costs.</strong>'),
      '#required' => FALSE,
      '#default_value' => $task_ai['hold_task_on_null'] ?? 0,
    ];

    $form['log_ai_return'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log the response from AI?'),
      '#description' => $this->t('The response will be placed into the Drupal log.'),
      '#required' => FALSE,
      '#default_value' => $task_ai['log_ai_return'] ?? 0,
    ];

    // Let's provide a field for the user to select which provider to use. But only
    // show the ones that have a default provider enabled AND which ones we have AI Task handlers for.
    $maestro_implemented_capabilities = [];
    
    // The AI module stores which providers are configured in it's config settings.
    $ai_module_settings = \Drupal::config('ai.settings');
    // $default_providers holds the providers configured.
    $default_providers = $ai_module_settings->get('default_providers') ?? [];

    // We cycle through our Maestro AI Task capabilities and compare that to which of the AI module's default providers
    // are configured. We present those to the user as options.
    foreach($implemented_maestro_ai_task_capabilities as $key => $capability) {
      if(array_key_exists($capability['ai_provider'], $default_providers)) {
        $maestro_implemented_capabilities[$capability['id']] = $capability['capability_description'];
      }
    }

    // During execution, we use the provider type to fetch the provider for each AI call.
    // The $enabled_providers we use to set up a set of checkboxes for the user to select from.
    $ai_provider = $task_ai['ai_provider'] ?? '';
    $form['ai_provider'] = [
      '#type' => 'radios',
      '#title' => $this->t('Configured AI Providers Available'),
      '#options' => $maestro_implemented_capabilities,
      '#default_value' => $ai_provider,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'aiProviderCallback'],
        'event' => 'change',
        'wrapper' => 'ai-provider-ajax-refresh-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    // The AI module's provider settings are found here: /admin/config/ai/settings
    // Route is: ai.settings_form
    $link_to_ai_module_settings = Link::createFromRoute($this->t('Configure the AI module\'s providers here'),'ai.settings_form')->toString();
    $form['ai_provider']['ai_provider_help_text'] = [
      '#weight' => -5,
      '#markup' => 
        '<div class="messages">' .
        $this->t('The providers listed here are the available providers configured in the AI module which have a corresponding Maestro AI capability created for it.<br>') . 
        $this->t('While you may have some providers configured in the AI module, Maestro requires plugins to "talk" to the configured AI module providers.<br>') . 
        $this->t('If you have an AI module provider configured but do not see it here, a Maestro AI Task Capability plugin is required to be created.<br>') . 
        $link_to_ai_module_settings . 
        '</div>',
    ];

    // We let our Maestro AI Capabilities provide extra fields
    $form['capabilities_wrapper'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="ai-provider-ajax-refresh-wrapper"',
      '#suffix' => '</div>',
    ];

    // Let's get the selected ai operation to show any preconfigured fields, if any.
    if($ai_provider) {
      $maestro_capability = MaestroAiTaskAPI::createMaestroAiTaskCapabilityPlugin(
        $ai_provider, 
        [
          'task' => $task, 
          'templateMachineName' => $templateMachineName,
        ]
      );

      if($maestro_capability) {
        // We have a valid Maestro AI Task capability.  Let's execute.
        $ai_capability_form_elements = $maestro_capability->getMaestroAiTaskConfigFormElements();
        $allow_return_format = $maestro_capability->allowConfigurableReturnFormat();
          if($allow_return_format) {
            $options = _maestroAiTaskReturnFormatOptions();
            $ai_capability_form_elements['ai_return_format'] = [
              '#type' => 'select',
              '#title' => t('Return format from the AI Call'),
              '#description' => t('Default is JSON as a Yes/No string. See documentation for more examples'),
              '#options' => $options,
              '#required' => TRUE,
              '#default_value' => $task_ai['ai_return_format'] ?? 'json_yes_no',
            ];

            $ai_capability_form_elements['ai_return_custom_format'] = [
              '#type' => 'textarea',
              '#title' => t('Custom return format'),
              '#default_value' => $task_ai['ai_return_custom_format'] ?? '',
              '#required' => FALSE,
              '#description' => t('The custom format that you wish to return the information as. <strong>Please note that the return format you specify may not be supported by the AI Configuration you\'ve chosen.</strong>'),
              '#states' => [
                // Show this textfield only if the select box above has 'custom' chosen.
                'visible' => [
                  ':input[name="ai_return_format"]' => ['value' => 'custom'],
                ],
              ],
            ];
          }
          else {
            $ai_capability_form_elements['ai_return_format'] = [
              '#type' => 'hidden',
              '#value' => '',
              '#default_value' => '',
            ];

            $ai_capability_form_elements['ai_return_custom_format'] = [
              '#type' => 'hidden',
              '#value' => '',
              '#default_value' => '',
            ];

          }

        $form['capabilities_wrapper'] += $ai_capability_form_elements;
        
      }
      
    }
    else {
      $form['capabilities_wrapper'] += [
        '#markup' => $this->t('Please choose an AI Provider'),
      ];
    }

    // Let's provide a field for the administrators to configure what they want the LLM to do.
    $prompt = $task_ai['ai_prompt'] ?? '';
    if ($prompt == '') {
      // Since this is a blank prompt, let's provide a default prompt by pulling from the config.
      $prompt = $ai_config->get('base_prompt') ?? 'You are a knowledgeable and helpful office administrator who reviews documents.';
    }

    $form['ai_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $prompt,
      '#required' => TRUE,
      '#description' => $this->t('AI prompt. What do you want AI to do?  Token replacements shown below. <b>There are some operation types that do not use prompts. For those operation types, any non-blank prompt will suffice.</b>'),
      '#attributes' => [
          'rows' => 6,
          'data-mdxeditor' => 'maestro_ai_task_assistant_post_process_prompt',
        ],
    ];

    /*
     * Things like :processVariableName will feed IN PV data to the prompt.
     */
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

    $form['ai_testing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test Mode'),
      '#default_value' => $task_ai['ai_testing'] ?? 0,
      '#description' => $this->t('When checked, the AI Testing Response field will be used as the return from AI calls. <strong>This will override the global setting.</strong>'),
      '#required' => FALSE,
    ];

    // Let's set up a default testing response value, so that people can see what it should look like.
    $ai_testing_response = $task_ai['ai_testing_response'] ?? Json::encode(
      [
        'result' => 'true',
      ]
    );

    $form['ai_testing_response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Testing Response'),
      '#default_value' => $ai_testing_response,
      '#required' => FALSE,
      '#description' => $this->t('Default is a result of "true" in JSON format. Fill in this field with the response you want to return when testing the AI task. <strong>This will override the global setting.</strong>'),
      '#states' => [
        // Show this textfield only if the checkbox above is checked.
        'visible' => [
          ':input[name="ai_testing"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['ai_return_into'] = [
      '#type' => 'select',
      '#title' => $this->t('Return the data into a process variable, AI storage entity or process directly in this task'),
      '#description' => $this->t('Default is to process in task. Some Maestro AI Task Capabilities can force the return format to a yes/no or true false. It is up to you to make sure that if you are processing in task you return a TRUE/FALSE in JSON format. Example: {"result":"true"}'),
      '#options' => [
        'process_in_task' => $this->t('Process in task'),
        'process_variable' => $this->t('Set a process variable with the return data (max 255 characters)'),
        'ai_task_entity' => $this->t('Set a Maestro AI storage entity with the return data (large data)'),
      ],
      '#required' => TRUE,
      '#default_value' => $task_ai['ai_return_into'] ?? 'json_yes_no',
    ];

    // The process variables are a part of the template. Get those.
    $variables = MaestroEngine::getTemplateVariables($templateMachineName);
    $options = [
      '' => $this->t('Choose Process Variable'),
    ];
    foreach ($variables as $variableName => $arr) {
      $options[$variableName] = $variableName;
    }
    $form['ai_return_into_process_variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Return the AI\'s output into the selected process variable'),
      '#default_value' => $task_ai['ai_return_into_process_variable'] ?? '',
      '#required' => FALSE,
      '#options' => $options,
      '#description' => $this->t('Choose which variable will store the value'),
      '#states' => [
        // Show this textfield only if the select box above has 'process_variable' chosen.
        'visible' => [
          ':input[name="ai_return_into"]' => ['value' => 'process_variable'],
        ],
      ],
    ];

    // Cycle through any existing configurations in the template for ai task storage entity references.
    $options = ['' => $this->t('No entity storage chosen')];
    $template = MaestroEngine::getTemplate($templateMachineName);
    foreach ($template->tasks as $templateTask) {
      $tasktype = $templateTask['tasktype'] ?? NULL;
      if ($tasktype == 'MaestroAITask') {
        // See if there's any other entities already set.
        $ai_var = $templateTask['ai_return_into_ai_variable'] ?? NULL;
        if ($ai_var) {
          $options[strval($ai_var)] = $ai_var;
        }
      }
    }
    $form['ai_return_into_ai_variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Return the AI\'s output into a Maestro AI storage entity'),
      '#value' => $task_ai['ai_return_into_ai_variable'] ?? '',
      '#required' => FALSE,
      '#options' => $options,
      '#description' => $this->t('Choose which existing AI storage entity will store the value. Use the label in code to retrieve its contents.'),
      '#states' => [
        // Show this textfield only if the select box above has 'ai_task_entity' chosen.
        'visible' => [
          ':input[name="ai_return_into"]' => ['value' => 'ai_task_entity'],
        ],
      ],
    ];

    $form['ai_return_storage_machine_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Create a new AI Storage Entity Label'),
      '#required' => FALSE,
      '#default_value' => '',
      '#description' => $this->t('Provide a label for this storage entity. Will be added to the list above upon save.'),
      '#states' => [
        // Show this textfield only if the select box above has 'ai_task_entity' chosen.
        'visible' => [
          ':input[name="ai_return_into"]' => ['value' => 'ai_task_entity'],
        ],
      ],
    ];

    return $form;
  }

  public function aiProviderCallback(array &$form, FormStateInterface $form_state) {
    // The form_alter in the maestro_ai_task.module handles the ajax field refresh.
    return $form['capabilities_wrapper']; 
  }

 

  /**
   * {@inheritDoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    $taskID = $form['task_id']['#default_value'] ?? NULL;
    $templateMachineName = $form['template_machine_name']['#default_value'] ?? NULL;
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskID);
    // Make sure they have the right combo of settings
    // Test the right true/false return type.
    // If we chose to have a custom response format, you can't process it in the task.
    $return_format = $form_state->getValue('ai_return_format');
    $hidden_return_format = $form_state->getValue('hidden_ai_return_format');
    if($hidden_return_format) {
      $return_format = $hidden_return_format;
    }
    $process_where = $form_state->getValue('ai_return_into');
    if ($return_format != 'json_yes_no' && $return_format != 'json_true_false' && $process_where == 'process_in_task') {
      $form_state->setErrorByName(
        'ai_return_into',
        $this->t('You cannot have a non-yes/no or non-true/false return format and process in task.  Please choose a different return format.')
        );
    }

    // If $process_where is set to 'process_variable' then we need to make sure they've chosen a process variable.
    if ($process_where == 'process_variable' && $form_state->getValue('ai_return_into_process_variable') == '') {
      $form_state->setErrorByName(
        'ai_return_into_process_variable',
        $this->t('You must choose a process variable to store the response into.')
        );
    }

    // If we are trying to set our return AI Storage machine name to our reserved ID, then error out
    $ai_return_storage_machine_name = $form_state->getValue('ai_return_storage_machine_name');
    if ($ai_return_storage_machine_name && $ai_return_storage_machine_name == 'maestro_ai_task_history') {
      $form_state->setErrorByName(
        'ai_return_storage_machine_name',
        $this->t('The AI Storage ID you\'ve chosen is reserved by Maestro. Please use a different ID.')
        );
    }

    // Offload validation to our capabilities.
    $ai_provider = $form_state->getValue('ai_provider');
    /** @var MaestroAiTaskCapabilitiesPluginBase $maestro_capability */
    $maestro_capability = MaestroAiTaskAPI::createMaestroAiTaskCapabilityPlugin(
      $ai_provider,
      [
        'task' => $task,
        'templateMachineName' => $templateMachineName,
        'form_state' => $form_state, 
        'form' => $form,
      ] 
    );
    if($maestro_capability) {
      // We have a valid Maestro AI Task capability.  Perform validation.
      $maestro_capability->validateMaestroAiTaskEditForm($form, $form_state);
    }

  }

  /**
   * {@inheritDoc}  
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    $task['data']['ai'] = [];

    $task['data']['ai']['hold_task_on_null'] = $form_state->getValue('hold_task_on_null');
    $task['data']['ai']['ai_provider'] = $form_state->getValue('ai_provider');
    $task['data']['ai']['ai_prompt'] = self::unescapeMdxEditorArtifacts($form_state->getValue('ai_prompt') ?? '');
    $task['data']['ai']['ai_return_format'] = $form_state->getValue('ai_return_format');
    $task['data']['ai']['ai_return_custom_format'] = $form_state->getValue('ai_return_custom_format');
    $task['data']['ai']['ai_return_into'] = $form_state->getValue('ai_return_into');
    $task['data']['ai']['ai_return_into_process_variable'] = $form_state->getValue('ai_return_into_process_variable');
    $task['data']['ai']['ai_return_into_ai_variable'] = $form_state->getValue('ai_return_into_ai_variable');
    $task['data']['ai']['log_ai_return'] = $form_state->getValue('log_ai_return');
    $task['data']['ai']['ai_testing'] = $form_state->getValue('ai_testing');
    $task['data']['ai']['ai_testing_response'] = $form_state->getValue('ai_testing_response');

    $ai_return_storage_machine_name = $form_state->getValue('ai_return_storage_machine_name');
    if ($ai_return_storage_machine_name && $ai_return_storage_machine_name != 'maestro_ai_task_history') {
      // Set this as the ai variable. But only if it does not match our reserved ID of maestro_ai_task_history
      $task['data']['ai']['ai_return_into_ai_variable'] = $ai_return_storage_machine_name;
      $form_state->setValue('ai_return_into_ai_variable', $ai_return_storage_machine_name);
      $form_state->setValue('ai_return_storage_machine_name', '');
      $form_state->setRebuild(TRUE);
    }

    // Now offload to our Maestro AI Task Capabilities to save their data.
    $maestro_capability = MaestroAiTaskAPI::createMaestroAiTaskCapabilityPlugin(
      $task['data']['ai']['ai_provider'], 
      [
        'task' => $task, 
        'templateMachineName' => $form['template_machine_name']['#default_value'] ?? NULL,
        'form_state' => $form_state, 
        'form' => $form,
      ]
    );

    if($maestro_capability) {
      // We have a valid Maestro AI Task capability.  Let's execute.
      $maestro_capability->prepareTaskForSave($form, $form_state, $task);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    // So we know that we need a few keys in this $task array to allow the LLM to work.
    if ((array_key_exists('ai_prompt', $task['data']['ai'] ?? []) && $task['data']['ai']['ai_prompt'] == '')  || !array_key_exists('ai_prompt', $task['data']['ai'] ?? [])) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The AI Prompt for the task has not been set. This will only cause usage of your AI API tokens.'),
      ];
    }
    if (array_key_exists('ai_return_into', $task['data']['ai'] ?? []) && $task['data']['ai']['ai_return_into'] == 'process_variable' && $task['data']['ai']['ai_return_into_process_variable'] == '') {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('You specified that you will return the results into a process variable, but didn\'t choose one.'),
      ];
    }

  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

  /**
   * Reverses the MDX/Markdown editor's escaping of literal special characters.
   *
   * The editor round-trips every edit through a Markdown parse/serialize
   * cycle, which unconditionally backslash-escapes '[', '_', '*' and '`'
   * wherever they appear in plain text, including inside a literal Maestro
   * token like [maestro:process-variable-value:node_id], breaking it. This
   * field is never rendered as Markdown downstream (it's concatenated into
   * an AI prompt and token-replaced as plain text).
   *
   * @param string $text
   *   The raw value from the MDX-editor-backed ai_prompt textarea.
   *
   * @return string
   *   The text with the editor's escaping undone.
   */
  protected static function unescapeMdxEditorArtifacts(string $text): string {
    return str_replace(['\\[', '\\_', '\\*', '\\`'], ['[', '_', '*', '`'], $text);
  }

}
