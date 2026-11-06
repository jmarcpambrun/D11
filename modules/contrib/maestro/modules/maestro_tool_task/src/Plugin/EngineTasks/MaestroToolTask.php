<?php

namespace Drupal\maestro_tool_task\Plugin\EngineTasks;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Utility\Token;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;
use Drupal\maestro_tool_task\MaestroInvoker;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\Tool\ToolRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class MaestroToolTask extends PluginBase implements MaestroEngineTaskInterface, ContainerFactoryPluginInterface {

  use MaestroTaskTrait;

  /**
   * Output data types that hold a single value, with nothing inside to pick out.
   */
  protected const SCALAR_DATA_TYPES = [
    'boolean',
    'integer',
    'float',
    'decimal',
    'timestamp',
    'datetime_iso8601',
    'duration_iso8601',
  ];

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The process ID and queue ID, as passed by MaestroEngine::getPluginTask().
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user, which is the orchestrator's run-as account.
   * @param \Drupal\tool\Tool\ToolManager $toolManager
   *   The Tool API plugin manager.
   * @param \Drupal\tool\Tool\ToolRunner|null $toolRunner
   *   The Tool API runner, or NULL on Tool API releases before 1.0.0-beta12.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $logger_factory,
    protected Token $token,
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountProxyInterface $currentUser,
    protected ToolManager $toolManager,
    protected ?ToolRunner $toolRunner,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->processID = $configuration[0] ?? 0;
    $this->queueID = $configuration[1] ?? 0;
    $this->logger = $logger_factory->get('maestro_tool_task');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('token'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('plugin.manager.tool'),
      // Optional so that sites still on Tool API beta11 get a clear log
      // message when the task runs, rather than a broken plugin.
      $container->has('tool.runner') ? $container->get('tool.runner') : NULL,
    );
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
      $this->logger->error('No tool configured for this task.');
      return TRUE;
    }

    if (!$this->toolManager->hasDefinition($tool_id)) {
      $this->executionStatus = TASK_STATUS_CANCEL;
      $this->logger->error('Tool "@id" no longer exists.', ['@id' => $tool_id]);
      return TRUE;
    }

    $token_data = [
      'maestro' => [
        'task' => $task,
        'queueID' => $this->queueID,
        'processID' => $this->processID,
      ],
    ];

    $definition = $this->toolManager->getDefinition($tool_id);
    $inputs = $task_data['inputs'] ?? [];
    $values = [];
    foreach ($definition->getInputDefinitions() as $name => $input_definition) {
      if ($input_definition->isLocked() || !array_key_exists($name, $inputs)) {
        continue;
      }
      // Tool inputs are data, not HTML: use plain replacement so values such as "Dries' view"
      // reach the tool as typed instead of HTML-escaped ("Dries&#039; view").
      $resolved_value = trim($this->token->replacePlain((string) $inputs[$name], $token_data));
      if ($resolved_value === '' && !$input_definition->isRequired()) {
        continue;
      }
      $values[$name] = $resolved_value;
    }

    if (!$this->toolRunner) {
      $this->executionStatus = TASK_STATUS_CANCEL;
      $this->logger->error('Tool "@id" was not run: the Tool task requires Tool API 1.0.0-beta12 or later.', ['@id' => $tool_id]);
      return TRUE;
    }
    // The Tool API runner owns the call: the declared permission, input
    // transforms, validation, access and execution, all as the current user.
    // The orchestration runner has already made that the run-as account.
    try {
      $result = $this->toolRunner->run($tool_id, $values, invoker: MaestroInvoker::create())->getResult();
    }
    catch (\Throwable $e) {
      // The runner reports input, validation and access problems as a failed
      // result. What still throws is a tool that cannot be instantiated or a
      // result that cannot be processed; cancel the task rather than let it
      // break the orchestrator run.
      $this->executionStatus = TASK_STATUS_CANCEL;
      $this->logger->error('Tool "@id" could not be run (Runtime failure) as user "@user": @message', [
        '@id' => $tool_id,
        '@user' => $this->currentUser->getAccountName() ?: 'anonymous',
        '@message' => $e->getMessage(),
      ]);
      return TRUE;
    }

    if (!$result->isSuccess()) {
      $this->executionStatus = TASK_STATUS_CANCEL;
      $this->logger->error('Tool "@id" did not run (@category failure) as user "@user": @message', [
        '@id' => $tool_id,
        '@category' => $result->getFailureCategory()?->name ?? 'Unknown',
        '@user' => $this->currentUser->getAccountName() ?: 'anonymous',
        // Tool API escapes the plain-text reasons it puts in a message exactly
        // once; decode them so the logger's own placeholder escaping is the
        // only one applied.
        '@message' => Html::decodeEntities((string) $result->getMessage()),
      ]);
      return TRUE;
    }
    $output_values = $result->getContextValues();

    $outputs = $task_data['outputs'] ?? [];
    $output_storage = $task_data['output_storage'] ?? [];
    $output_extracts = $task_data['output_extracts'] ?? [];
    $output_names = array_unique(array_merge(array_keys($outputs), array_keys($output_storage), array_keys($output_extracts)));
    foreach ($output_names as $output_name) {
      if (!array_key_exists($output_name, $output_values)) {
        continue;
      }
      $value = $output_values[$output_name];

      // The whole output into a process variable.
      $variable_name = $outputs[$output_name] ?? '';
      if ($variable_name !== '') {
        $this->storeInProcessVariable($variable_name, $value);
      }

      // The whole output into a Maestro AI storage entity, for data too large for a process variable.
      // The entity is only created if it doesn't already exist for this process; otherwise it's updated.
      $storage_id = $output_storage[$output_name] ?? '';
      if ($storage_id !== '') {
        if ($this->moduleHandler->moduleExists('maestro_ai_task')) {
          \Drupal\maestro_ai_task\MaestroAiTaskAPI\MaestroAiTaskAPI::saveAiStorageValue(
            $storage_id,
            $this->processID,
            $this->queueID,
            $taskMachineName,
            self::valueToString($value)
          );
        }
        else {
          $this->logger->warning('Output "@output" of tool "@id" was not stored into AI storage entity "@storage": the Maestro AI Task module is not enabled.', [
            '@output' => $output_name,
            '@id' => $tool_id,
            '@storage' => $storage_id,
          ]);
        }
      }

      // Individual values picked out of the output (e.g. a URL from a JSON response) into process variables.
      foreach (($output_extracts[$output_name] ?? []) as $extract) {
        $path = $extract['path'] ?? '';
        $extract_variable = $extract['variable'] ?? '';
        if ($path === '' || $extract_variable === '') {
          continue;
        }
        $extracted = self::extractPath($value, $path);
        if ($extracted === NULL) {
          $this->logger->warning('Path "@path" was not found in output "@output" of tool "@id". Process variable "@variable" was set to an empty value.', [
            '@path' => $path,
            '@output' => $output_name,
            '@id' => $tool_id,
            '@variable' => $extract_variable,
          ]);
          $extracted = '';
        }
        $this->storeInProcessVariable($extract_variable, $extracted);
      }
    }

    return TRUE;
  }

  /**
   * Stores a value into a process variable, truncated to fit.
   *
   * @param string $variable_name
   *   The process variable.
   * @param mixed $value
   *   The value. Non scalar values are stored as JSON.
   */
  protected function storeInProcessVariable(string $variable_name, $value): void {
    $value_string = self::valueToString($value);
      // Process variables are limited to 10000 characters, matching
      // MaestroAITask's own truncation for its process_variable mode.
      if (strlen($value_string) > 10000) {
      $this->logger->warning('The value for process variable "@variable" was @length characters and has been truncated to 10000. Store large outputs in a Maestro AI storage entity instead.', [
        '@variable' => $variable_name,
        '@length' => strlen($value_string),
      ]);
        $value_string = substr($value_string, 0, 10000);
      }
      MaestroEngine::setProcessVariable($variable_name, $value_string, $this->processID);
    }

  /**
   * Converts a tool output value to a string.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   Strings and numbers as-is, booleans as "true"/"false", everything else as JSON.
   */
  protected static function valueToString($value): string {
    // Tools can return Drupal data objects, e.g. an ECA tool whose output is the token
    // [node:nid] returns the field item list. Use their string form rather than JSON ("{}").
    if ($value instanceof EntityInterface) {
      return (string) $value->id();
    }
    if ($value instanceof TypedDataInterface) {
      return $value->getString();
    }
    if ($value instanceof \Stringable) {
      return (string) $value;
    }
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_scalar($value) || $value === NULL) {
      return (string) $value;
    }
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Picks a value out of a tool output using a dot separated path.
   *
   * @param mixed $value
   *   The tool output. A JSON string is decoded first.
   * @param string $path
   *   The path, for example "images.0.thumbnail". Numbers index into lists.
   *
   * @return mixed
   *   The value found, or NULL when the path doesn't exist.
   */
  protected static function extractPath($value, string $path) {
    // Drupal data objects are turned into plain arrays first.
    if ($value instanceof EntityInterface) {
      $value = $value->toArray();
    }
    elseif ($value instanceof TypedDataInterface) {
      $value = $value->getValue();
    }
    if (is_string($value)) {
      $decoded = json_decode($value, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return NULL;
      }
      $value = $decoded;
    }
    elseif (is_object($value)) {
      $value = json_decode(json_encode($value), TRUE);
    }
    foreach (explode('.', trim($path)) as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
        return NULL;
      }
      $value = $value[$segment];
    }
    return $value;
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

    $tool_options = [];
    foreach ($this->toolManager->getDefinitions() as $id => $definition) {
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

    if ($selected_tool_id && $this->toolManager->hasDefinition($selected_tool_id)) {
      $definition = $this->toolManager->getDefinition($selected_tool_id);
      $saved_inputs = $task_data['inputs'] ?? [];
      $saved_outputs = $task_data['outputs'] ?? [];

      $variables = MaestroEngine::getTemplateVariables($templateMachineName);
      $variable_select_options = ['' => $this->t('- Select a variable -')];
      foreach ($variables as $variableName => $arr) {
        $variable_select_options[$variableName] = $variableName;
      }

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

      $saved_storage = $task_data['output_storage'] ?? [];
      $saved_extracts = $task_data['output_extracts'] ?? [];
      $ai_storage_enabled = $this->moduleHandler->moduleExists('maestro_ai_task');
      $existing_storage_ids = $ai_storage_enabled
        ? \Drupal\maestro_ai_task\MaestroAiTaskAPI\MaestroAiTaskAPI::getAiStorageEntityDefinitionsInTemplate($templateMachineName)
        : [];

      $form['io_wrapper']['outputs'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('What to do with the tool outputs'),
        '#description' => $this->t('For each output, you can keep the whole output, pick out individual values, or both. Outputs you leave alone are discarded.'),
        '#tree' => TRUE,
      ];
      $user_input = $form_state->getUserInput() ?? [];
      foreach ($definition->getOutputDefinitions() as $name => $output_definition) {
        $keep_options = [
          'none' => $this->t("Don't keep it"),
          'variable' => $this->t('In a process variable (at most 10000 characters)'),
        ];
        if ($ai_storage_enabled) {
          $keep_options['storage'] = $this->t('In a Maestro AI storage entity (no size limit; use this for large results)');
        }
        $keep = 'none';
        if ($ai_storage_enabled && !empty($saved_storage[$name])) {
          $keep = 'storage';
        }
        elseif (!empty($saved_outputs[$name])) {
          $keep = 'variable';
        }

        $output = &$form['io_wrapper']['outputs'][$name];
        $output = [
          '#type' => 'details',
          '#title' => (string) $output_definition->getLabel() . ' (' . $name . ')',
          '#description' => $this->t('Data type: @type.', ['@type' => $output_definition->getDataType() . ($output_definition->isMultiple() ? ' (list)' : '')]) . ' ' . (string) $output_definition->getDescription(),
          '#open' => $keep !== 'none' || !empty($saved_extracts[$name]),
        ];

        // 1. Keep the whole output: one place at most.
        $output['keep'] = [
          '#type' => 'radios',
          '#title' => $this->t('1. Keep the whole output?'),
          '#options' => $keep_options,
          '#default_value' => $keep,
        ];
        $output['variable'] = [
          '#type' => 'select',
          '#title' => $this->t('Process variable'),
          '#options' => $variable_select_options,
          '#default_value' => $saved_outputs[$name] ?? '',
          '#states' => [
            'visible' => [':input[name="outputs[' . $name . '][keep]"]' => ['value' => 'variable']],
          ],
        ];
        if ($ai_storage_enabled) {
          $output['storage'] = [
            '#type' => 'textfield',
            '#title' => $this->t('AI storage entity ID'),
            '#description' => $this->t('Lowercase letters, numbers and underscores. The entity is created the first time this task runs in a process and updated on later runs. Use it in an AI prompt with [maestro:render_ai_task_entity:ID:text].') .
              ($existing_storage_ids ? ' ' . $this->t('IDs already used in this template: @ids.', ['@ids' => implode(', ', $existing_storage_ids)]) : ''),
            '#default_value' => $saved_storage[$name] ?? '',
            '#maxlength' => 128,
            '#states' => [
              'visible' => [':input[name="outputs[' . $name . '][keep]"]' => ['value' => 'storage']],
            ],
        ];
      }

        // 2. Pick out individual values, each into its own process variable.
        $wrapper_id = 'maestro-tool-task-extracts-' . Html::cleanCssIdentifier($name);
        $saved_rows = $saved_extracts[$name] ?? [];
        $row_count = $form_state->get(['maestro_tool_extract_rows', $name]) ?? count($saved_rows) + 1;
        // After "Add another row", show what was typed rather than the saved values.
        $input_rows = NestedArray::getValue($user_input, ['outputs', $name, 'extracts', 'rows']);
        $rows = is_array($input_rows) ? array_values($input_rows) : $saved_rows;

        // Outputs that can never hold JSON don't offer picking at all, unless the template already
        // has rows saved there, which stay visible (with the reason) so they can be removed.
        $pick_warning = $this->getPickWarning($definition, $name);
        if ($pick_warning && empty($saved_rows)) {
          $output['pick_unavailable'] = [
            '#type' => 'item',
            '#title' => $this->t('2. Picking out individual values is not available for this output'),
            '#description' => $pick_warning,
          ];
          unset($output);
          continue;
        }

        $pick_description = $this->t('Copy chosen values, such as a URL, each into its own process variable. This works alongside whatever you chose above. <strong>Only works when this output is JSON.</strong> Any other format (XML, YAML, plain text) sets the chosen process variables to an empty value.');
        if ($pick_warning) {
          $pick_description = $this->t('<strong>Warning:</strong> @warning', ['@warning' => $pick_warning]) . ' ' . $pick_description;
        }
        elseif ($pick_hint = $this->getPickHint($definition, $name)) {
          $pick_description = $pick_hint . ' ' . $pick_description;
        }

        // Opt-in, so the rows only appear when wanted. Can be combined with any "keep" choice above.
        $output['pick'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('2. Also pick out individual values into process variables (JSON output only)'),
          '#description' => $pick_description,
          '#default_value' => !empty($saved_rows),
        ];
        $output['extracts'] = [
          '#type' => 'container',
          '#prefix' => '<div id="' . $wrapper_id . '">',
          '#suffix' => '</div>',
          '#states' => [
            'visible' => [':input[name="outputs[' . $name . '][pick]"]' => ['checked' => TRUE]],
          ],
        ];
        $output['extracts']['heading'] = [
          '#type' => 'item',
          '#description' => $this->t('Each row copies one value from the output into a process variable. Paths use dots, and numbers pick items from a list: <em>images.0.thumbnail</em> is the "thumbnail" of the first item in "images". A path that is not found sets the variable to an empty value.'),
        ];
        $output['extracts']['rows'] = [
          '#type' => 'table',
          '#header' => [$this->t('Path in the output'), $this->t('Save into process variable'), ''],
        ];
        for ($i = 0; $i < $row_count; $i++) {
          $output['extracts']['rows'][$i]['path'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Path'),
            '#title_display' => 'invisible',
            '#default_value' => $rows[$i]['path'] ?? '',
            '#size' => 30,
            '#attributes' => ['placeholder' => 'images.0.thumbnail'],
          ];
          $output['extracts']['rows'][$i]['variable'] = [
            '#type' => 'select',
            '#title' => $this->t('Process variable'),
            '#title_display' => 'invisible',
            '#options' => $variable_select_options,
            '#default_value' => $rows[$i]['variable'] ?? '',
          ];
          $output['extracts']['rows'][$i]['remove'] = [
            '#type' => 'submit',
            '#value' => $this->t('Remove'),
            '#name' => 'maestro_tool_remove_extract_row_' . $name . '_' . $i,
            '#output_name' => $name,
            '#row_index' => $i,
            '#row_count' => $row_count,
            '#submit' => [[static::class, 'removeExtractRowSubmit']],
            '#limit_validation_errors' => [],
            '#ajax' => [
              'callback' => [static::class, 'extractRowsAjax'],
              'wrapper' => $wrapper_id,
            ],
          ];
        }
        $output['extracts']['add_row'] = [
          '#type' => 'submit',
          '#value' => $this->t('Add another row'),
          '#name' => 'maestro_tool_add_extract_row_' . $name,
          '#output_name' => $name,
          '#row_count' => $row_count,
          '#submit' => [[static::class, 'addExtractRowSubmit']],
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [static::class, 'extractRowsAjax'],
            'wrapper' => $wrapper_id,
          ],
        ];
        unset($output);
      }
    }
    else {
      $form['io_wrapper']['message'] = [
        '#markup' => $this->t('Select a tool above to configure its inputs and outputs.'),
      ];
    }

    if ($this->moduleHandler->moduleExists('token')) {
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
   * Submit handler for an output's "Add another row" button.
   */
  public static function addExtractRowSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $form_state->set(['maestro_tool_extract_rows', $trigger['#output_name']], $trigger['#row_count'] + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for a row's "Remove" button.
   */
  public static function removeExtractRowSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $name = $trigger['#output_name'];

    // The rebuilt form takes its rows from the submitted input, so drop the row there.
    $input = $form_state->getUserInput() ?? [];
    $rows = NestedArray::getValue($input, ['outputs', $name, 'extracts', 'rows']);
    $rows = is_array($rows) ? array_values($rows) : [];
    unset($rows[$trigger['#row_index']]);
    $rows = array_values($rows);
    // Always leave at least one (blank) row to type into.
    if (!$rows) {
      $rows = [['path' => '', 'variable' => '']];
    }
    NestedArray::setValue($input, ['outputs', $name, 'extracts', 'rows'], $rows);
    $form_state->setUserInput($input);

    $form_state->set(['maestro_tool_extract_rows', $name], count($rows));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another row" and "Remove" buttons.
   */
  public static function extractRowsAjax(array &$form, FormStateInterface $form_state) {
    // Return the container holding the rows: ...['outputs'][output name]['extracts'].
    // Anchored on 'outputs' (which comes first), as a tool output could itself be named 'extracts'.
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $parents = array_slice($parents, 0, array_search('outputs', $parents, TRUE) + 3);
    return NestedArray::getValue($form, $parents);
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

    if (!$this->toolManager->hasDefinition($tool_id)) {
      $form_state->setErrorByName('tool_id', $this->t('The selected tool no longer exists.'));
      return;
    }

    $definition = $this->toolManager->getDefinition($tool_id);
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

    foreach (($form_state->getValue('outputs') ?? []) as $name => $data) {
      $keep = $data['keep'] ?? 'none';
      if ($keep === 'variable' && empty($data['variable'])) {
        $form_state->setErrorByName("outputs][$name][variable", $this->t('Choose the process variable that keeps the whole "@output" output.', ['@output' => $name]));
      }
      if ($keep === 'storage') {
        $storage_id = trim($data['storage'] ?? '');
        if ($storage_id === '') {
          $form_state->setErrorByName("outputs][$name][storage", $this->t('Enter the AI storage entity ID that keeps the whole "@output" output.', ['@output' => $name]));
        }
        elseif (!preg_match('/^[a-z0-9_]+$/', $storage_id)) {
          $form_state->setErrorByName("outputs][$name][storage", $this->t('The storage entity ID may only contain lowercase letters, numbers and underscores.'));
        }
        elseif ($storage_id === 'maestro_ai_task_history') {
          $form_state->setErrorByName("outputs][$name][storage", $this->t('The AI Storage ID you\'ve chosen is reserved by Maestro. Please use a different ID.'));
        }
      }
      // Rows only count when "pick out individual values" is ticked.
      $pick_rows = !empty($data['pick']) ? ($data['extracts']['rows'] ?? []) : [];
      foreach ($pick_rows as $i => $row) {
        $path = trim($row['path'] ?? '');
        $variable = $row['variable'] ?? '';
        if ($path !== '' && $variable === '') {
          $form_state->setErrorByName("outputs][$name][extracts][rows][$i][variable", $this->t('Choose the process variable for path "@path".', ['@path' => $path]));
        }
        elseif ($path === '' && $variable !== '') {
          $form_state->setErrorByName("outputs][$name][extracts][rows][$i][path", $this->t('Enter the path to save into process variable "@variable".', ['@variable' => $variable]));
        }
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

    // 'outputs' keeps its original shape (output name => process variable) so existing templates still work.
    $outputs = [];
    $output_storage = [];
    $output_extracts = [];
    foreach (($form_state->getValue('outputs') ?? []) as $name => $data) {
      $keep = $data['keep'] ?? 'none';
      $outputs[$name] = $keep === 'variable' ? ($data['variable'] ?? '') : '';
      if ($keep === 'storage' && trim($data['storage'] ?? '') !== '') {
        $output_storage[$name] = trim($data['storage']);
      }
      // Rows are only saved when "pick out individual values" is ticked.
      $pick_rows = !empty($data['pick']) ? ($data['extracts']['rows'] ?? []) : [];
      foreach ($pick_rows as $row) {
        $path = trim($row['path'] ?? '');
        if ($path !== '' && !empty($row['variable'])) {
          $output_extracts[$name][] = ['path' => $path, 'variable' => $row['variable']];
        }
      }
    }
    $task['data']['tool']['outputs'] = $outputs;
    $task['data']['tool']['output_storage'] = $output_storage;
    $task['data']['tool']['output_extracts'] = $output_extracts;
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

    if (!$this->toolManager->hasDefinition($tool_id)) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The tool "@id" selected for this task no longer exists.', ['@id' => $tool_id]),
      ];
      return;
    }

    // Picked values saved on an output they can never be found in always come out empty.
    $definition = $this->toolManager->getDefinition($tool_id);
    foreach (array_keys(array_filter($task['data']['tool']['output_extracts'] ?? [])) as $output_name) {
      if ($warning = $this->getPickWarning($definition, $output_name)) {
        $validation_information_tasks[] = [
          'taskID' => $task['id'],
          'taskLabel' => $task['label'],
          'reason' => $this->t('Values are picked out of output "@output", so their process variables will be empty. @warning', [
            '@output' => $output_name,
            '@warning' => $warning,
          ]),
        ];
      }
    }
  }

  /**
   * Explains why individual values can't be picked out of a tool output.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool definition.
   * @param string $output_name
   *   The output.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The reason, or NULL when the output may hold JSON.
   */
  protected function getPickWarning(ToolDefinition $definition, string $output_name): ?TranslatableMarkup {
    $output_definitions = $definition->getOutputDefinitions();
    $output_definition = $output_definitions[$output_name] ?? NULL;
    if (!$output_definition) {
      return NULL;
    }
    // MCP tools return the same response twice: "result" with JSON converted to YAML, and "raw" as sent.
    if ($definition->getProvider() === 'mcp_client' && $output_name === 'result' && isset($output_definitions['raw'])) {
      return $this->t('This output converts JSON to YAML. Pick values out of the "@raw (raw)" output instead, which keeps the JSON as the MCP server sent it.', [
        '@raw' => (string) $output_definitions['raw']->getLabel(),
      ]);
    }
    if (!$output_definition->isMultiple() && in_array($output_definition->getDataType(), self::SCALAR_DATA_TYPES, TRUE)) {
      return $this->t('A single @type value has no individual values inside it. Keep the whole output instead.', [
        '@type' => $output_definition->getDataType(),
      ]);
    }
    return NULL;
  }

  /**
   * Points out the output to pick values from, when a tool has a preferred one.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool definition.
   * @param string $output_name
   *   The output.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The hint, or NULL.
   */
  protected function getPickHint(ToolDefinition $definition, string $output_name): ?TranslatableMarkup {
    if ($definition->getProvider() === 'mcp_client' && $output_name === 'raw') {
      return $this->t('This output keeps the JSON as the MCP server sent it, so this is the output to pick values from.');
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
