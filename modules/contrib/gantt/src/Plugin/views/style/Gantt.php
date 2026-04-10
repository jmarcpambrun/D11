<?php

namespace Drupal\gantt\Plugin\views\style;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\Plugin\views\wizard\WizardInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render each item in a gantt chart.
 *
 * @ingroup views_style_plugins
 */
#[ViewsStyle(
  id: "gantt",
  title: new TranslatableMarkup("Gantt"),
  help: new TranslatableMarkup("Displays rows in a gantt chart."),
  theme: "views_view_gantt",
  display_types: ["normal"],
)]
class Gantt extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to its output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * Contains the current active sort column.
   *
   * @var string
   */
  public $active;

  /**
   * Contains the current active sort order, either desc or asc.
   *
   * @var string
   */
  public $order;

  /**
   * Constructs a gantt view plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityFieldManagerInterface $entityFieldManager, protected ModuleHandlerInterface $moduleHandler, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Define Options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['text'] = ['default' => ''];
    $options['progress'] = ['default' => ''];
    $options['show_progress'] = ['default' => FALSE];
    $options['start_date'] = ['default' => ''];
    $options['end_date'] = ['default' => ''];
    $options['duration'] = ['default' => ''];
    $options['work_time'] = ['default' => FALSE];
    $options['work_day'] = ['default' => []];
    $options['baseline'] = [
      'default' => '',
      'planned_date' => ['default' => ''],
      'planned_end_date' => ['default' => ''],
      'planned_duration' => ['default' => ''],
      'auto_schedule_planned' => ['default' => FALSE],
    ];
    $options['type'] = ['default' => ''];
    $options['group_field'] = ['default' => ''];
    $options['add_task'] = ['default' => TRUE];
    $options['edit_task'] = ['default' => TRUE];
    $options['native_dialog'] = ['default' => TRUE];
    $options['open'] = ['default' => ''];
    $options['cdn'] = ['default' => TRUE];
    $options['send_email'] = ['default' => FALSE];
    $options['send_notification'] = ['default' => FALSE];
    $options['links'] = ['default' => ''];
    $options['parent'] = ['default' => ''];
    $options['owner'] = ['default' => ''];
    $options['custom_field'] = ['default' => ''];
    $options['order'] = ['default' => ''];
    $options['creator'] = ['default' => ''];
    $options['permission_edit'] = ['default' => FALSE];
    $options['show_button_detail'] = ['default' => FALSE];
    $options['holidays'] = ['default' => '01-01,01-05'];
    $options['auto_schedule'] = ['default' => FALSE];
    $options['custom_resource'] = ['default' => []];
    $options['show_column_resource'] = ['default' => []];
    $options['show_lightbox_resource'] = ['default' => []];
    $options['resource_has_edit'] = ['default' => []];
    $options['select_parent'] = ['default' => FALSE];
    $options['group_resource'] = ['default' => []];
    $options['last_of_the_day'] = ['default' => TRUE];
    $options['priority'] = ['default' => ''];
    $options['hide_add_task_level'] = ['default' => FALSE];
    $options['hide_add_task_level_value'] = ['default' => 0];
    $options['column_buttons'] = ['default' => FALSE];
    $options['time_input_mode'] = ['default' => 'duration'];
    $options['calendar_ical'] = ['default' => ''];
    $options['format_date'] = ['default' => ''];
    $options['custom_format_date'] = ['default' => 'd/m/Y'];
    $options['action_queue'] = ['default' => FALSE];
    return $options;
  }

  /**
   * Get list of fields.
   */
  protected function getConfigurableFields($type = FALSE, $listFields = []) {
    $resultField = ['' => $this->t('- None -')];
    foreach ($listFields as $field_name => $fields) {
      if (in_array($field_name, $type)) {
        $resultField += $fields;
      }
    }
    return $resultField;
  }

  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $listOption = $this->getListOptions();
    $form['text'] = [
      '#type' => 'select',
      '#title' => $this->t('Name field'),
      '#options' => $this->getConfigurableFields([
        'list_integer',
        'text',
        'text_long',
        'text_with_summary',
        'string',
        'string_long',
      ], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['text'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Select the field that contains name of each record.'),
    ];
    $form['parent'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent ID field'),
      '#options' => $this->getConfigurableFields(['integer', 'list_integer'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['parent'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t("Select the field that contains nid of the record's parent node."),
    ];
    $form['links'] = [
      '#type' => 'select',
      '#title' => $this->t('Link field'),
      '#options' => $this->getConfigurableFields([
        'double_field',
        'triples_field',
      ], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['links'] ?? '',
      '#description' => $this->t("Select the field that contains of links dependencies."),
    ];
    $form['start_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Date'),
      '#options' => $this->getConfigurableFields(['datetime', 'daterange'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['start_date'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('This configuration is required and a duration or end date configuration must exist. If you select the date range field for this configuration, you can omit the duration and end date configurations.'),
    ];
    $form['format_date'] = [
      '#title' => $this->t('Format date'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $this->getListDateFormat(),
      '#default_value' => $this->options['format_date'] ?? '',
    ];
    $form['custom_format_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom format date'),
      '#default_value' => $this->options['custom_format_date'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="style_options[format_date]"]' => ['value' => 'custom'],
        ],
      ],
      '#description' => $this->t("Allowed characters: 'Y', 'm', 'd', 'H', 'i', 's', 'j', 'l', 'M', 'a', 'A', 'F', 'n', 'W', 'w', 'D', 'g', 'G', 'y', 'h'. If empty, the value 'd/m/Y' will be used"),
    ];
    $form['end_date'] = [
      '#type' => 'select',
      '#title' => $this->t('End date'),
      '#options' => $this->getConfigurableFields(['datetime', 'daterange'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['end_date'] ?? '',
      '#description' => $this->t('Link to Field actual end date'),
    ];
    $form['show_end'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show end day'),
      '#default_value' => $this->options['show_end'],
      '#description' => $this->t("If check it will add column end date."),
    ];
    $form['duration'] = [
      '#type' => 'select',
      '#title' => $this->t('Duration'),
      '#options' => $this->getConfigurableFields(['integer'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['duration'] ?? '',
      '#description' => $this->t('Duration Field'),
    ];
    $form['time_input_mode'] = [
      '#title' => $this->t('Time input mode'),
      '#type' => 'select',
      '#empty_option' => $this->t('- None -'),
      '#options' => [
        'duration' => $this->t('Duration'),
        'end_time' => $this->t('End time'),
        'responsive' => $this->t('Responsive'),
      ],
      '#default_value' => $this->options['time_input_mode'],
    ];
    $form['work_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Work day'),
      '#default_value' => $this->options['work_time'] ?? FALSE,
      '#description' => $this->t("Enables calculating the duration of tasks in working time instead of calendar time."),
    ];
    $form['work_day'] = [
      '#type' => 'select',
      '#title' => $this->t('Work day in week'),
      '#options' => [
        $this->t('Sunday'),
        $this->t('Monday'),
        $this->t('Tuesday'),
        $this->t('Wednesday'),
        $this->t('Thursday'),
        $this->t('Friday'),
        $this->t('Saturday'),
      ],
      '#default_value' => $this->options['work_day'] ?? [],
      '#description' => $this->t('Pick out non-working days of the week'),
      '#multiple' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="style_options[work_time]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['holidays'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Holidays'),
      '#default_value' => $this->options['holidays'],
      '#description' => $this->t('Enter holidays separated by, Example "01-01,01-05,09-04-2023,... Format d-m or d-m-Y"'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[work_time]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['baseline'] = [
      '#type' => 'details',
      '#title' => $this->t('Base line (version PRO)'),
      '#description' => $this->t('If you want show double gantt to compare actual vs planned date. This method is available only in Pro versions'),
    ];
    $form['baseline']['planned_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Planned Date field'),
      '#options' => $this->getConfigurableFields(['datetime', 'daterange'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['baseline']['planned_date'] ?? '',
      '#description' => $this->t("This configuration is required and a duration or end date configuration must exist. If you select the date range field for this configuration, you can omit the duration and end date configurations."),
    ];
    $form['baseline']['planned_end_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Planned end date field'),
      '#options' => $this->getConfigurableFields(['datetime', 'daterange'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['baseline']['planned_end_date'] ?? '',
      '#description' => $this->t('Select the field that contains the planned date range in the selected row.'),
    ];
    $form['baseline']['planned_duration'] = [
      '#type' => 'select',
      '#title' => $this->t('Duration'),
      '#options' => $this->getConfigurableFields(['integer'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['baseline']['planned_duration'] ?? '',
      '#description' => $this->t('Planned duration field'),
    ];
    $form['baseline']['constraint'] = [
      '#title' => $this->t('Constraint'),
      '#description' => $this->t('Link to Field constraint.'),
      '#type' => 'select',
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields([
        'double_field',
        'triples_field',
      ], $listOption),
      '#default_value' => $this->options['baseline']['constraint'] ?? '',
    ];
    $form['baseline']['auto_schedule_planned'] = [
      '#title' => $this->t('Auto schedule planned'),
      '#description' => $this->t('Automatic scheduling for scheduled dates'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['baseline']['auto_schedule_planned'] ?? '',
    ];
    $form['open'] = [
      '#title' => $this->t('Open'),
      '#type' => 'select',
      '#options' => $this->getConfigurableFields(['boolean'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['open'] ?? '',
      '#description' => $this->t('Field open'),
    ];
    $form['progress'] = [
      '#type' => 'select',
      '#title' => $this->t('Progress field'),
      '#options' => $this->getConfigurableFields([
        'float',
        'list_float',
        'decimal',
      ], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['progress'] ?? '',
      '#description' => $this->t('Select the field that contains the progress of the node in percents.'),
    ];
    $form['show_progress'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show progress'),
      '#default_value' => $this->options['show_progress'],
      '#description' => $this->t("If check it will add column progress."),
    ];
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Task type'),
      '#options' => $this->getConfigurableFields(['list_string'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['type'] ?? '',
      '#description' => $this->t("Select the task node type."),
    ];
    $form['group_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Group field'),
      '#options' => $this->getConfigurableFields(['string', 'entity_reference'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['group_field'] ?? '',
      '#description' => $this->t("Select field to group."),
    ];
    $form['creator'] = [
      '#type' => 'select',
      '#title' => $this->t('Author'),
      '#options' => $this->getConfigurableFields(['entity_reference'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['creator'],
      '#description' => $this->t('Link to Field author.'),
    ];
    $form['custom_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Custom field'),
      '#options' => $this->getConfigurableFields(['integer'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['custom_field'] ?? '',
      '#description' => $this->t('Additional column'),
    ];
    $form['priority'] = [
      '#title' => $this->t('Priority'),
      '#description' => $this->t('Link to Field priority'),
      '#type' => 'select',
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields([
        'entity_reference',
        'list_string',
      ], $listOption),
      '#default_value' => $this->options['priority'],
    ];
    $form['order'] = [
      '#type' => 'select',
      '#title' => $this->t('Order field'),
      '#options' => $this->getConfigurableFields([
        'integer',
        'float',
        'decimal',
      ], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['order'] ?? '',
    ];
    $form['add_task'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add Task'),
      '#default_value' => !empty($this->options['add_task']) ? $this->options['add_task'] : '',
      '#description' => $this->t("Enable posibility 'Add Task' from Gantt chart."),
    ];
    $form['hide_add_task_level'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide add task level'),
      '#default_value' => $this->options['hide_add_task_level'],
      '#description' => $this->t("Hide the add tasks button for tasks whose level is greater than or equal to the input value."),
      '#states' => [
        'visible' => [
          ':input[name="style_options[add_task]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['hide_add_task_level_value'] = [
      '#type' => 'number',
      '#min' => '0',
      '#title' => $this->t('Hide add task level value'),
      '#default_value' => $this->options['hide_add_task_level_value'],
      '#states' => [
        'visible' => [
          [
            ':input[name="style_options[add_task]"]' => ['checked' => TRUE],
            ':input[name="style_options[hide_add_task_level]"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ];
    $form['edit_task'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Edit Task'),
      '#default_value' => !empty($this->options['edit_task']) ? $this->options['edit_task'] : '',
      '#description' => $this->t("Enable posibility 'Edit Task' from Gantt chart."),
    ];
    $form['show_button_detail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show button details'),
      '#default_value' => $this->options['show_button_detail'],
      '#description' => $this->t("Show details task with drupal form"),
    ];
    $form['last_of_the_day'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Last of the day'),
      '#default_value' => $this->options['last_of_the_day'],
    ];
    $form['native_dialog'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Custom form'),
      '#default_value' => !empty($this->options['native_dialog']) ? $this->options['native_dialog'] : '',
      '#description' => $this->t("Use native Drupal form to add/edit."),
    ];
    $form['action_queue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Action queue'),
      '#default_value' => !empty($this->options['action_queue']) ? $this->options['action_queue'] : '',
      '#description' => $this->t("Task update action added to queue for processing."),
    ];

    $form['cdn'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use CDN'),
      '#default_value' => $this->options['cdn'] ?? '',
      '#description' => $this->t('If not you can use in /libraries/gantt/codebase/dhtmlxgantt.js'),
    ];
    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send an email after the change of status'),
      '#default_value' => $this->options['send_email'] ?? '',
    ];
    if ($this->moduleHandler->moduleExists('pwa_firebase')) {
      $form['send_notification'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Send notification to assign user'),
        '#default_value' => $this->options['send_notification'] ?? '',
      ];
    }
    $form['select_parent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Selection parent'),
      '#description' => $this->t("This selection allows to select the parent task in the lightbox."),
      '#default_value' => $this->options['select_parent'] ?? FALSE,
    ];
    $form['custom_resource'] = [
      '#type' => 'select',
      '#title' => $this->t('Resource custom'),
      '#description' => $this->t('Link to Field Resource custom'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields(['entity_reference'], $listOption),
      '#default_value' => $this->options['custom_resource'],
      '#multiple' => TRUE,
    ];
    $form['show_column_resource'] = [
      '#type' => 'select',
      '#title' => $this->t('Column show resource'),
      '#description' => $this->t('Select the columns to be displayed'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields(['entity_reference'], $listOption),
      '#default_value' => $this->options['show_column_resource'],
      '#multiple' => TRUE,
    ];
    $form['show_lightbox_resource'] = [
      '#type' => 'select',
      '#title' => $this->t('Light box show resource'),
      '#description' => $this->t('Select the field to be displayed'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields(['entity_reference'], $listOption),
      '#default_value' => $this->options['show_lightbox_resource'],
      '#multiple' => TRUE,
    ];
    $form['permission_edit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permission to edit on a task'),
      '#default_value' => $this->options['permission_edit'],
      '#description' => $this->t("Only author can edit their task."),
    ];
    $form['resource_has_edit'] = [
      '#type' => 'select',
      '#title' => $this->t('Resource has edit'),
      '#description' => $this->t('Resource with editing rights'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields(['entity_reference'], $listOption),
      '#default_value' => $this->options['resource_has_edit'],
      '#multiple' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="style_options[permission_edit]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['group_resource'] = [
      '#type' => 'select',
      '#title' => $this->t('Group resource'),
      '#description' => $this->t('This function works on pro version'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields(['entity_reference'], $listOption),
      '#default_value' => $this->options['group_resource'],
      '#multiple' => TRUE,
    ];
    $form['hide_show_column'] = [
      '#title' => $this->t('Hide show column - Pro version'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['hide_show_column'],
    ];
    $form['column_buttons'] = [
      '#title' => $this->t('Column buttons'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['column_buttons'],
      '#description' => $this->t('Display buttons as the same column'),
    ];
    $form['gantt_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Gantt theme'),
      '#default_value' => $this->options['gantt_theme'],
      '#empty_option' => $this->t('- None -'),
      '#options' => [
        'broadway' => $this->t('Broadway'),
        'contrast_black' => $this->t('Contrast black'),
        'contrast_white' => $this->t('Contrast white'),
        'material' => $this->t('Material'),
        'meadow' => $this->t('Meadow'),
        'skyblue' => $this->t('Skyblue'),
        'terrace' => $this->t('Terrace'),
      ],
    ];

    $text_context_variable = $this->t('The variable is not available');
    $contextual_filter_definitions = $this->view->argument;
    $contextual_arg = [];
    if (!empty($contextual_filter_definitions)) {
      foreach ($contextual_filter_definitions as $key => $contextual_filter_definition) {
        $contextual_arg[] = "{{ $key }}";
      }
      if (count($contextual_arg)) {
        $text_context_variable = $this->t('Variable available @variable', [
          '@variable' => implode(', ', $contextual_arg),
        ]);
      }
    }
    $form['calendar_ical'] = [
      '#title' => $this->t('Custom text at the modal'),
      '#description' => $this->t("@description_header. These values are extracted from the context filter.", ['@description_header' => $text_context_variable]),
      '#type' => 'textarea',
      '#default_value' => $this->options['calendar_ical'],
    ];

    $form['control_bar'] = [
      '#title' => $this->t('Control bar'),
      '#description' => $this->t('The following options will be displayed to customize the gantt chart'),
      '#type' => 'checkboxes',
      '#options' => $this->controlBar(),
      '#default_value' => $this->options['control_bar'],
      '#multiple' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function wizardSubmit(&$form, FormStateInterface $form_state, WizardInterface $wizard, &$display_options, $display_type) {
    // If any of the displays use the table style, make sure that the fields
    // always have a labels by unsetting the override.
    foreach ($display_options['default']['fields'] as &$field) {
      unset($field['label']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * Control bar options.
   *
   * {@inheritDoc}
   */
  private function controlBar() {
    return [
      'round_dnd_dates' => $this->t('Allows task start and end dates to be rounded to the smallest unit'),
      'show_column_wbs' => $this->t('Show column WBS'),
      'lock_completed_task' => $this->t('Limit editing of completed tasks'),
      'dynamic_progress' => $this->t('Dynamic progress summary'),
      'progress_text' => $this->t('Text progress'),
      'auto_type' => $this->t('Auto type - Pro version'),
      'auto_schedule' => $this->t('Auto schedule - Pro version'),
      'click_drag' => $this->t('Enables advanced drag-n-drop - Pro version'),
      'critical_path' => $this->t('Shows the critical path in the chart - Pro version'),
      'drag_project' => $this->t('Drag and drop of line - Pro version'),
      'hide_weekend_scale' => $this->t('Hide weekend scale - Pro version'),
      'highlight_drag_task' => $this->t('Highlights drag task - Pro version'),
      'show_slack' => $this->t('Show slack - Pro version'),
    ];

  }

  /**
   * List options.
   *
   * {@inheritDoc}
   */
  protected function getListOptions() {
    $entity_type = $this->view->getBaseEntityType()->id();
    $field_storage_definition = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
    $fields = $this->displayHandler->getHandlers('field');
    $labels = $this->displayHandler->getFieldLabels();
    $listOption = [];
    foreach ($fields as $field_name => $field) {
      $field_type = $field_storage_definition[$field_name]?->getType();
      if (empty($field_type)) {
        continue;
      }
      $listOption[$field_type][$field_name] = $labels[$field_name];
    }
    return $listOption;
  }

  /**
   * Return date format system options.
   *
   * {@inheritDoc}
   */
  private function getListDateFormat() {
    $result = [];
    $dateFormatStorage = $this->entityTypeManager->getStorage('date_format');
    $drupalDateFormat = $dateFormatStorage->loadMultiple();
    foreach ($drupalDateFormat as $format) {
      $result[$format->getPattern()] = $format->label();
    }
    $result += ['custom' => $this->t('Custom')];
    return $result;
  }

}
