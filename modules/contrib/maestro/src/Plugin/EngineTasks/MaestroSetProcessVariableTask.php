<?php

namespace Drupal\maestro\Plugin\EngineTasks;

use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\MaestroTaskTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\maestro\MaestroSetProcessVariablePluginBase;

/**
 * Maestro Set Process Variable Task Plugin.
 *
 * The plugin annotations below should include:
 * id: The task type ID for this task.  For Maestro tasks, this is Maestro[TaskType].
 *     So for example, the start task shipped by Maestro is MaestroStart.
 *     The Maestro End task has an id of MaestroEnd
 *     Those task IDs are what's used in the engine when a task is injected into the queue.
 *
 * @Plugin(
 *   id = "MaestroSetProcessVariable",
 *   task_description = @Translation("The Maestro Engine's Set Process Variable task."),
 * )
 */
class MaestroSetProcessVariableTask extends PluginBase implements MaestroEngineTaskInterface {

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
    return $this->t('Set Process Variable');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('Sets a process variable to the desired value.');
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\Component\Plugin\PluginBase::getPluginId()
   */
  public function getPluginId() {
    return 'MaestroSetProcessVariable';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#daa520';
  }

  /**
   * Part of the ExecutableInterface
   * Execution of the set process variable task.  We will read the data in the template for what we should do with the process variable
   * {@inheritdoc}.
   */
  public function execute() {
    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($this->processID);
    $taskMachineName = MaestroEngine::getTaskIdFromQueueId($this->queueID);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskMachineName);

    $spv = $task['data']['spv'];
    $variable = $spv['variable'];
    $variableValue = $spv['variable_value'];

    switch ($spv['method']) {
      case 'byplugin':
        // We fire off the selected plugin
        $variableValue = '';
        $spv_plugin_option = $spv['spv_plugin'];
        $spv_plugin_manager = \Drupal::service('plugin.manager.set_process_variable_plugins');
        if($spv_plugin_option && $templateMachineName && $task) {
          $spv_plugin = $spv_plugin_manager->createInstance(
            $spv_plugin_option, 
            [
              'templateMachineName' => $templateMachineName,
              'task' => $task,
              'processID' => $this->processID,
              'queueID' => $this->queueID,
            ]
          );
          if($spv_plugin) {
            $variableValue = $spv_plugin->execute();
          }
          MaestroEngine::setProcessVariable($variable, $variableValue, $this->processID);
        }

        return TRUE;
        // Completes the task here
      break;

      case 'addsubtract':
        // Simple here.. our value is a floatval.  This can be negative or positive.  Just add it to the current process variable.
        $processVar = MaestroEngine::getProcessVariable($variable, $this->processID);
        $processVar = floatval($processVar) + $variableValue;
        MaestroEngine::setProcessVariable($variable, $processVar, $this->processID);
        return TRUE;

      // Completes the task here.
      break;

      case 'hardcoded':
        // As easy as just taking the variable's value and setting it to what the template tells us to do.
        MaestroEngine::setProcessVariable($variable, $variableValue, $this->processID);
        return TRUE;

      // Completes the task here.
      break;

      case 'bycontentfunction':
        // [0] is our function, the rest are arguments
        $arr = explode(':', $variableValue);
        $arguments = explode(',', $arr[1]);
        $arguments[] = $this->queueID;
        $arguments[] = $this->processID;

        $newValue = call_user_func_array($arr[0], $arguments);
        MaestroEngine::setProcessVariable($variable, $newValue, $this->processID);
        return TRUE;

      // Completes the task here.
      break;
    }
    // We are relying on the base trait's default values to set the execution and completion status.
  }

  /**
   * {@inheritDoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {} //not interactive.. we do nothing

  /**
   * {@inheritDoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {}  //not interactive.. we do nothing.

  /**
   * {@inheritDoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    /** @var FormStateInterface $form_state */
    $form_state = $task['form_state'];

    $spv = $task['data']['spv'] ?? [];

    $form = [
      '#markup' => $this->t('Editing the Process Variable Task'),
    ];

    $form['spv'] = [
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => $this->t('Choose which process variable you wish to set and how'),
      '#collapsed' => FALSE,
    ];

    $variables = MaestroEngine::getTemplateVariables($templateMachineName);
    $options = [];
    foreach ($variables as $variableName => $arr) {
      $options[$variableName] = $variableName;
    }

    $form['spv']['variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the variable'),
      '#required' => TRUE,
      '#default_value' => isset($spv['variable']) ? $spv['variable'] : '',
      '#options' => $options,
    ];

    // TODO: add other options here such as the content field result
    // however, the content field result needs to be rethought on how we're leveraging content.
    $form['spv']['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Method to set variable'),
      '#options' => [
        'hardcoded' => $this->t('Hardcoded Value'),
        'addsubtract' => $this->t('Add or Subtract a Value'),
        'bycontentfunction' => $this->t('By Function. Pass "function_name:parameter1,parameter2,..." in variable 
                                          as comma separated list. e.g. maestro_spv_content_value_fetch:unique_identifier,field_your_field'),
        'byplugin' => $this->t('By a Set Process Variable Helper Plugin'),
      ],
      '#default_value' => isset($spv['method']) ? $spv['method'] : '',
      '#required' => TRUE,
    ];

    // For the non plugin variable setting mechanisms
    $form['spv']['variable_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Variable value'),
      '#description' => $this->t('The value you wish to set the variable to based on your METHOD selection above'),
      '#default_value' => isset($spv['variable_value']) ? $spv['variable_value'] : '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="spv[method]"]' => ['!value' => 'byplugin'],
        ],
      ],
    ];

    // Set up the Set Process Variable Task's plugins
    $spv_plugin_manager = \Drupal::service('plugin.manager.set_process_variable_plugins');
    $plugins = $spv_plugin_manager->getDefinitions(); 

    $options = ['' => $this->t('Choose a Set Process Variable Plugin')];
    foreach($plugins as $key => $arr) {
      $options[$key] = $arr['description'];
    }
    
    // First, choose the plugin
    $default_plugin_value = $spv['spv_plugin'] ?? NULL;
    $form['spv']['spv_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the Set Process Variable Plugin'),
      '#options' => $options,
      '#default_value' => $default_plugin_value,
      '#ajax' => [
        'callback' => [$this, 'spvPluginCallback'],
        'event' => 'change',
        'wrapper' => 'spv-plugin-ajax-refresh-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
      '#states' => [
        'visible' => [
          ':input[name="spv[method]"]' => ['value' => 'byplugin'],
        ],
      ],
    ];

    // For the SPV Plugin plugin option selected
    $form['spv']['plugin_wrapper'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="spv-plugin-ajax-refresh-wrapper"',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="spv[method]"]' => ['value' => 'byplugin'],
        ],
      ],
    ];

    $triggering_element = $form_state->getTriggeringElement();
    if(isset($triggering_element) && $triggering_element['#name'] == 'spv[spv_plugin]') {
      // Use the triggering element to set the fields
      $default_plugin_value = $triggering_element['#value'] ?? NULL;
    }

    if($default_plugin_value) {
      // Create an instance of this plugin
      $config = [
        'task' => $task,
        'templateMachineName' => $templateMachineName,
        'form' => $form,
        'form_state' => $form_state,
      ];
      
      /** @var MaestroSetProcessVariablePluginBase $spv_plugin */
      $spv_plugin = $spv_plugin_manager->createInstance(
        $default_plugin_value, 
        $config
      );
      if($spv_plugin) {
        $form_elements = $spv_plugin->getSPVTaskConfigFormElements();
        $form['spv']['plugin_wrapper'] += $form_elements;
      }
      else {
        $form['spv']['plugin_wrapper'] += [
          '#markup' => $this->t('There\'s been an error trying to retrieve your Set Process Variable Plugin.'),
        ];
      }
    }
    else {
      $form['spv']['plugin_wrapper'] += [
        '#markup' => $this->t('Choose a Set Process Value Plugin'),
      ];
    }

    $x=1;

    return $form;
  }

  public function spvPluginCallback(array &$form, FormStateInterface $form_state) {
    return $form['spv']['plugin_wrapper']; 
  }

  /**
   * {@inheritDoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    // These are the set process variable values.
    $spv = $form_state->getValue('spv');

    switch ($spv['method']) {
      case 'byplugin':
        $triggering_element = $form_state->getTriggeringElement();
        if(strstr($triggering_element['#name'], 'spv[') === FALSE) {
          $spv_plugin_option = $spv['spv_plugin'] ?? NULL;
          if(!$spv_plugin_option) {
            // They've chosen the plugin option, but haven't chosen one.
            $form_state->setErrorByName('spv][spv_plugin', $this->t('You\'ve chosen to set via Plugin, however, you\'ve not chosen one.'));
          }
          else {
            $spv_plugin_manager = \Drupal::service('plugin.manager.set_process_variable_plugins');
            $spv_plugin = $spv_plugin_manager->createInstance(
              $spv_plugin_option, 
              [
                'form' => $form,
                'form_state' => $form_state,
              ]
            );
            if($spv_plugin) {
              $spv_plugin->validateSPVTaskEditForm($form, $form_state);
            }
            else {
              $form_state->setErrorByName('spv][plugin_wrapper', $this->t('The plugin chosen was not able to be validated.'));
            }
          }
        }

        break;

      case 'addsubtract':
        $addSubValue = $spv['variable_value'];
        $float = floatval($addSubValue);
        if ($addSubValue == '') {
          $form_state->setErrorByName('spv][variable_value', $this->t('Must provide a variable value.'));
        }
        if (strcmp($float, $addSubValue) != 0) {
          $form_state->setErrorByName('spv][variable_value', $this->t('The add or subtract mechanism requires numerical values only.'));
        }
        break;

      case 'hardcoded':
        // We don't care what they hard code a variable to quite frankly.  But we at least trap the case here
        // in the event we need to do some preprocessing on it in the future.  Hook?
        if ($spv['variable_value'] == '') {
          $form_state->setErrorByName('spv][variable_value', $this->t('Must provide a variable value.'));
        }
        break;

      case 'bycontentfunction':
        // In here we use the variable value and parse out the function, content type and field we wish to fetch.
        $variable = $spv['variable_value'];
        // Remove spaces.
        $variable = str_replace(' ', '', $variable);
        $arr = explode(':', $variable);
        if (!function_exists($arr[0])) {
          // Bad function!
          $form_state->setErrorByName('spv][variable_value', $this->t("The function name you provided doesn't exist."));
        }

        break;

      default:
        $form_state->setErrorByName('spv][method', $this->t('The method by which you\'re setting the variable is blank.'));
        break;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    $templateMachineName = $form_state->getValue('template_machine_name');
    $spv = $form_state->getValue('spv');
    $task['data']['spv'] = [
      'variable' => $spv['variable'] ?? '',
      'method' => $spv['method'] ?? '',
      'variable_value' => $spv['variable_value'] ?? '',
      'spv_plugin' => $spv['spv_plugin'] ?? '',
    ];
    // Now if we're using a plugin, offload to it.
    if($spv['method'] == 'byplugin') {
      // Fetch the plugin
      $spv_chosen_plugin = $spv['spv_plugin'];
      $spv_plugin_manager = \Drupal::service('plugin.manager.set_process_variable_plugins');
      $spv_plugin = $spv_plugin_manager->createInstance(
        $spv_chosen_plugin, 
        [
          'form' => $form,
          'form_state' => $form_state,
          'templateMachineName' => $templateMachineName,
          'task' => $task            
        ]
      );
      if($spv_plugin) {
        $spv_plugin->prepareTaskForSave($form, $form_state, $task);
      }
    }

  }

  /**
   * {@inheritDoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    $data = $task['data']['spv'];
    if (is_array($data) && ( (array_key_exists('variable', $data) && $data['variable'] == '') || !array_key_exists('variable', $data))) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => t('The SPV Task has not been set up properly.  The variable you wish to set is missing and thus the engine will be unable to execute this task.'),
      ];
    }
    elseif(!is_array($data)) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => t('The SPV Task has not been set up.  Seems the task does not have any settings whatsoever.'),
      ];
    }
    if (!is_array($data) || (array_key_exists('method', $data) && $data['method'] == '')  || !array_key_exists('method', $data)) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => t('The SPV Task has not been set up properly.  The method you wish to set the variable with is missing and thus the engine will be unable to execute this task.'),
      ];
    }
    // We can have a blank value.... perhaps not in the form, but certainly in code.
    if (!is_array($data) || !array_key_exists('variable_value', $data)) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => t('The SPV Task has not been set up properly.  The variable value you wish to set the variable to is missing and thus the engine will be unable to execute this task.'),
      ];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
