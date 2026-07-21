<?php

namespace Drupal\maestro_tool_task\Plugin\EngineTasks;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;

/**
 * Maestro Tool Task Plugin.
 *
 * Invokes a Tool API tool directly and deterministically the template
 * builder picks which tool, and maps each of its inputs to a literal value
 * or a Maestro token (e.g. [maestro:process_variable:node_id]), and each of
 * its outputs to a process variable. No AI/LLM decides whether or how to
 * call the tool; Maestro always calls it the same way, every time.
 *
 * @Plugin(
 *   id = "MaestroToolTask",
 *   task_description = @Translation("The Maestro Engine's Tool task."),
 * )
 */
class MaestroToolTask extends PluginBase implements MaestroEngineTaskInterface {

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
    return $this->t('Tool Task');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('Invokes a Tool API tool directly, mapping Maestro tokens/process variables to its inputs and outputs.');
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\Component\Plugin\PluginBase::getPluginId()
   */
  public function getPluginId() {
    return 'MaestroToolTask';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#2E8B57';
  }

  /**
   * {@inheritDoc}
   */
  public function execute() {
    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($this->processID);
    $taskMachineName = MaestroEngine::getTaskIdFromQueueId($this->queueID);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskMachineName);
    $task_data = $task['data']['tool'] ?? [];

    $tool_id = $task_data['tool_id'] ?? '';
    if (!$tool_id) {
      $this->executionStatus = TASK_STATUS_CANCEL;
      \Drupal::logger('maestro_tool_task')->error('No tool configured for this task.');
      return TRUE;
    }

    /** @var \Drupal\tool\Tool\ToolManager $tool_manager */
    $tool_manager = \Drupal::service('plugin.manager.tool');
    if (!$tool_manager->hasDefinition($tool_id)) {
      $this->executionStatus = TASK_STATUS_CANCEL;
      \Drupal::logger('maestro_tool_task')->error('Tool "@id" no longer exists.', ['@id' => $tool_id]);
      return TRUE;
    }

    /** @var \Drupal\tool\Tool\ToolInterface $tool */
    $tool = $tool_manager->createInstance($tool_id);

    $token_service = \Drupal::token();
    $token_data = [
      'maestro' => [
        'task' => $task,
        'queueID' => $this->queueID,
        'processID' => $this->processID,
      ],
    ];

    $definition = $tool_manager->getDefinition($tool_id);
    $inputs = $task_data['inputs'] ?? [];
    foreach ($definition->getInputDefinitions() as $name => $input_definition) {
      if ($input_definition->isLocked() || !array_key_exists($name, $inputs)) {
        continue;
      }
      $resolved_value = trim($token_service->replace((string) $inputs[$name], $token_data));
      if ($resolved_value === '' && !$input_definition->isRequired()) {
        continue;
      }
      $tool->setInputValue($name, $resolved_value);
    }

    if (!$tool->access()) {
      $this->executionStatus = TASK_STATUS_CANCEL;
      \Drupal::logger('maestro_tool_task')->error('Access denied executing tool "@id".', ['@id' => $tool_id]);
      return TRUE;
    }

    $tool->execute();

    if (!$tool->getResultStatus()) {
      $this->executionStatus = TASK_STATUS_CANCEL;
      \Drupal::logger('maestro_tool_task')->error('Tool "@id" execution failed: @message', [
        '@id' => $tool_id,
        '@message' => (string) $tool->getResultMessage(),
      ]);
      return TRUE;
    }

    $outputs = $task_data['outputs'] ?? [];
    foreach ($outputs as $output_name => $variable_name) {
      if ($variable_name === '') {
        continue;
      }
      try {
        $value = $tool->getOutputValue($output_name);
      }
      catch (\Exception $e) {
        continue;
      }
      $value_string = is_scalar($value) ? (string) $value : json_encode($value);
      // Process variables are limited to 255 characters, matching
      // MaestroAITask's own truncation for its process_variable mode.
      if (strlen($value_string) > 255) {
        $value_string = substr($value_string, 0, 255);
      }
      MaestroEngine::setProcessVariable($variable_name, $value_string, $this->processID);
    }

    return TRUE;
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
    /** @var \Drupal\Core\Form\FormStateInterface $form_state */
    $form_state = $task['form_state'];
    $task_data = $task['data']['tool'] ?? [];

    /** @var \Drupal\tool\Tool\ToolManager $tool_manager */
    $tool_manager = \Drupal::service('plugin.manager.tool');
    $tool_options = [];
    foreach ($tool_manager->getDefinitions() as $id => $definition) {
      $tool_options[$id] = (string) $definition->getLabel();
    }

    $selected_tool_id = $form_state->getValue('tool_id') ?? ($task_data['tool_id'] ?? '');

    $form['tool_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Tool'),
      '#description' => $this->t('The Tool API tool this task will invoke every time it runs.'),
      '#options' => $tool_options,
      '#empty_option' => $this->t('- Select a tool -'),
      '#default_value' => $selected_tool_id,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'toolSelectCallback'],
        'event' => 'change',
        'wrapper' => 'maestro-tool-task-io-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $form['io_wrapper'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="maestro-tool-task-io-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($selected_tool_id && $tool_manager->hasDefinition($selected_tool_id)) {
      $definition = $tool_manager->getDefinition($selected_tool_id);
      $saved_inputs = $task_data['inputs'] ?? [];
      $saved_outputs = $task_data['outputs'] ?? [];

      $variables = MaestroEngine::getTemplateVariables($templateMachineName);
      $variable_options = ['' => $this->t("- Don't store -")];
      foreach ($variables as $variableName => $arr) {
        $variable_options[$variableName] = $variableName;
      }
      $variable_select_options = $variable_options;
      $variable_select_options[''] = $this->t('- Select a variable -');

      $form['io_wrapper']['inputs'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Tool inputs'),
        '#tree' => TRUE,
      ];
      foreach ($definition->getInputDefinitions() as $name => $input_definition) {
        if ($input_definition->isLocked()) {
          continue;
        }

        $saved_value = $saved_inputs[$name] ?? '';
        $mode = 'literal';
        $variable_name = '';
        $literal_value = $saved_value;
        if (preg_match('/^\[maestro:process-variable-value:([a-zA-Z0-9_]+)\]$/', trim($saved_value), $matches)) {
          $mode = 'variable';
          $variable_name = $matches[1];
          $literal_value = '';
        }

        $form['io_wrapper']['inputs'][$name] = [
          '#type' => 'fieldset',
          '#title' => (string) $input_definition->getLabel() . ' (' . $name . ')' . ($input_definition->isRequired() ? ' *' : ''),
          '#description' => (string) $input_definition->getDescription(),
          '#tree' => TRUE,
        ];
        $form['io_wrapper']['inputs'][$name]['mode'] = [
          '#type' => 'radios',
          '#title' => $this->t('Value comes from'),
          '#options' => [
            'literal' => $this->t('Free text (Maestro tokens allowed)'),
            'variable' => $this->t('An existing process variable'),
          ],
          '#default_value' => $mode,
        ];
        $form['io_wrapper']['inputs'][$name]['literal'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Text'),
          '#rows' => 3,
          '#default_value' => $literal_value,
          '#states' => [
            'visible' => [
              ':input[name="inputs[' . $name . '][mode]"]' => ['value' => 'literal'],
            ],
          ],
        ];
        $form['io_wrapper']['inputs'][$name]['variable'] = [
          '#type' => 'select',
          '#title' => $this->t('Process variable'),
          '#options' => $variable_select_options,
          '#default_value' => $variable_name,
          '#states' => [
            'visible' => [
              ':input[name="inputs[' . $name . '][mode]"]' => ['value' => 'variable'],
            ],
          ],
        ];
      }

      $form['io_wrapper']['outputs'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Store tool outputs into process variables'),
        '#tree' => TRUE,
      ];
      foreach ($definition->getOutputDefinitions() as $name => $output_definition) {
        $form['io_wrapper']['outputs'][$name] = [
          '#type' => 'select',
          '#title' => (string) $output_definition->getLabel() . ' (' . $name . ')',
          '#description' => (string) $output_definition->getDescription(),
          '#options' => $variable_options,
          '#default_value' => $saved_outputs[$name] ?? '',
        ];
      }
    }
    else {
      $form['io_wrapper']['message'] = [
        '#markup' => $this->t('Select a tool above to configure its inputs and outputs.'),
      ];
    }

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['maestro'],
      ];
    }
    else {
      $form['token_tree'] = [
        '#plain_text' => $this->t('Enabling the Token module will reveal the replaceable tokens.'),
      ];
    }

    return $form;
  }

  /**
   * AJAX callback for the tool selection field.
   */
  public function toolSelectCallback(array &$form, FormStateInterface $form_state) {
    return $form['io_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    $tool_id = $form_state->getValue('tool_id');
    if (!$tool_id) {
      $form_state->setErrorByName('tool_id', $this->t('You must select a tool.'));
      return;
    }

    /** @var \Drupal\tool\Tool\ToolManager $tool_manager */
    $tool_manager = \Drupal::service('plugin.manager.tool');
    if (!$tool_manager->hasDefinition($tool_id)) {
      $form_state->setErrorByName('tool_id', $this->t('The selected tool no longer exists.'));
      return;
    }

    $definition = $tool_manager->getDefinition($tool_id);
    $inputs = $form_state->getValue('inputs') ?? [];
    foreach ($definition->getInputDefinitions() as $name => $input_definition) {
      if ($input_definition->isLocked() || !$input_definition->isRequired()) {
        continue;
      }
      $data = $inputs[$name] ?? [];
      $mode = $data['mode'] ?? 'literal';
      $has_value = $mode === 'variable'
        ? !empty($data['variable'])
        : trim($data['literal'] ?? '') !== '';
      if (!$has_value) {
        $error_child = $mode === 'variable' ? 'variable' : 'literal';
        $form_state->setErrorByName("inputs][$name][$error_child", $this->t('@label is required.', ['@label' => (string) $input_definition->getLabel()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    $task['data']['tool']['tool_id'] = $form_state->getValue('tool_id');

    $raw_inputs = $form_state->getValue('inputs') ?? [];
    $inputs = [];
    foreach ($raw_inputs as $name => $data) {
      if (($data['mode'] ?? 'literal') === 'variable' && !empty($data['variable'])) {
        $inputs[$name] = '[maestro:process-variable-value:' . $data['variable'] . ']';
      }
      else {
        $inputs[$name] = $data['literal'] ?? '';
      }
    }
    $task['data']['tool']['inputs'] = $inputs;

    $task['data']['tool']['outputs'] = $form_state->getValue('outputs') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    $tool_id = $task['data']['tool']['tool_id'] ?? '';
    if (!$tool_id) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('No tool has been selected for this task.'),
      ];
      return;
    }

    /** @var \Drupal\tool\Tool\ToolManager $tool_manager */
    $tool_manager = \Drupal::service('plugin.manager.tool');
    if (!$tool_manager->hasDefinition($tool_id)) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The tool "@id" selected for this task no longer exists.', ['@id' => $tool_id]),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
