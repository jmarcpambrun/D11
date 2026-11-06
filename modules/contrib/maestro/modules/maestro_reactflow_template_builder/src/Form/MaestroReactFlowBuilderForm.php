<?php

namespace Drupal\maestro_reactflow_template_builder\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_reactflow_template_builder\Ajax\FireJavascriptCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ReactFlow-based template builder form.
 *
 * The ReactFlow app (js/MaestroReactFlow.js) draws the canvas and drives this
 * form through its hidden fields and buttons: it fills in fields such as
 * task_clicked and triggers buttons such as #edit-move-task-complete. Their
 * keys, and so their names and IDs, must not change.
 */
class MaestroReactFlowBuilderForm extends FormBase {

  /**
   * Tempstore name shared with MaestroReactFlowEditTaskForm.
   */
  const TEMPSTORE = 'maestro_reactflow_template_builder';

  /**
   * Holds the ID of the task being edited, between the click and the modal.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * Constructs a MaestroReactFlowBuilderForm.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory->get(self::TEMPSTORE);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_reactflow_template_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Ajax callback: remembers the clicked task, then opens the edit task modal.
   */
  public function editTask(array &$form, FormStateInterface $form_state) {
    $this->tempStore->set('maestro_editing_task', $form_state->getValue('task_clicked'));
    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    $response->addCommand(new FireJavascriptCommand('maestroEditTask', []));
    return $response;
  }

  /**
   * Ajax callback: saves the canvas pan and zoom.
   */
  public function panZoomComplete(array &$form, FormStateInterface $form_state) {
    $template = MaestroEngine::getTemplate($form_state->getValue('template_machine_name'));
    $template->zoom = $form_state->getValue('zoom');
    $template->pan_top = $form_state->getValue('pan_top');
    $template->pan_left = $form_state->getValue('pan_left');
    $template->save();

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroNoOp', []));
    return $response;
  }

  /**
   * Ajax callback: saves a task's position when it is dropped.
   */
  public function moveTaskComplete(array &$form, FormStateInterface $form_state) {
    $taskMoved = $form_state->getValue('task_clicked');
    $template = MaestroEngine::getTemplate($form_state->getValue('template_machine_name'));

    // Only move a task that exists. Writing coordinates for an unknown ID (e.g. a task whose
    // creation failed) would add a broken task holding nothing but top/left.
    if (isset($template->tasks[$taskMoved]['tasktype'])) {
      $template->tasks[$taskMoved]['top'] = $form_state->getValue('task_top');
      $template->tasks[$taskMoved]['left'] = $form_state->getValue('task_left');
      $template->save();
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroNoOp', []));
    return $response;
  }

  /**
   * Ajax callback: adds a task dropped from the palette to the template.
   */
  public function createNewTaskFromPalette(array &$form, FormStateInterface $form_state) {
    $template = MaestroEngine::getTemplate($form_state->getValue('template_machine_name'));
    $new_task_id = $form_state->getValue('palette_task_id');

    $template->tasks[$new_task_id] = [
      'id' => $new_task_id,
      'label' => $form_state->getValue('palette_task_label'),
      'tasktype' => $form_state->getValue('palette_task_type'),
      'nextstep' => '',
      'nextfalsestep' => '',
      'top' => $form_state->getValue('task_top'),
      'left' => $form_state->getValue('task_left'),
      'assignby' => 'fixed',
      'assignto' => '',
      'raphael' => '',
      'to' => '',
      'pointedfrom' => '',
      'falsebranch' => '',
      'lines' => [],
    ];
    // We need to have this template validated now.
    $template->validated = FALSE;
    $template->save();

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
    return $response;
  }

  /**
   * Ajax callback: saves a line once its second task has been chosen.
   */
  public function drawLineComplete(array &$form, FormStateInterface $form_state) {
    return $this->addLine($form_state, 'nextstep');
  }

  /**
   * Ajax callback: saves a false line once its second task has been chosen.
   */
  public function drawFalseLineComplete(array &$form, FormStateInterface $form_state) {
    return $this->addLine($form_state, 'nextfalsestep');
  }

  /**
   * Adds task_line_to to task_line_from's nextstep or nextfalsestep list.
   */
  protected function addLine(FormStateInterface $form_state, $property) {
    $taskFrom = $form_state->getValue('task_line_from');
    $taskTo = $form_state->getValue('task_line_to');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);
    $templateTaskFrom = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);

    $steps = $templateTaskFrom[$property] ?? '';
    if (!in_array($taskTo, explode(',', $steps))) {
      $template->tasks[$taskFrom][$property] = $steps == '' ? $taskTo : $steps . ',' . $taskTo;
      // We want to force the designer to validate the template to be sure.
      $template->validated = FALSE;
      $template->save();
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback: puts the canvas into line drawing mode.
   */
  public function drawLineTo(array &$form, FormStateInterface $form_state) {
    return $this->startLine($form_state, 'maestroDrawLineTo');
  }

  /**
   * Ajax callback: puts the canvas into false line drawing mode.
   */
  public function drawFalseLineTo(array &$form, FormStateInterface $form_state) {
    return $this->startLine($form_state, 'maestroDrawFalseLineTo');
  }

  /**
   * Signals the canvas to start drawing a line from the clicked task.
   */
  protected function startLine(FormStateInterface $form_state, $command) {
    $taskFrom = $form_state->getValue('task_clicked');
    $task = MaestroEngine::getTemplateTaskByID($form_state->getValue('template_machine_name'), $taskFrom);

    $response = new AjaxResponse();
    if ($task['tasktype'] == 'MaestroEnd') {
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => $this->t('You are not able to draw a line FROM an end task!')]));
      return $response;
    }
    $response->addCommand(new FireJavascriptCommand($command, ['taskid' => $taskFrom]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback: removes a task from the template.
   */
  public function removeTaskComplete(array &$form, FormStateInterface $form_state) {
    $taskToRemove = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $response = new AjaxResponse();
    if ($taskToRemove == 'start') {
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => $this->t('You are not able to delete a Start task')]));
      return $response;
    }

    if (MaestroEngine::removeTemplateTask($templateMachineName, $taskToRemove) === FALSE) {
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => $this->t('There was an error removing the Task. Unable to Complete the removal')]));
      return $response;
    }

    MaestroEngine::setTemplateToUnvalidated($templateMachineName);
    $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
    $response->addCommand(new FireJavascriptCommand('maestroRemoveTask', ['task' => $taskToRemove]));
    return $response;
  }

  /**
   * Ajax callback: removes the lines pointing to and from a task.
   */
  public function removeLines(array &$form, FormStateInterface $form_state) {
    $taskToRemoveLines = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');

    $template = MaestroEngine::getTemplate($templateMachineName);
    $pointers = MaestroEngine::getTaskPointersFromTemplate($templateMachineName, $taskToRemoveLines);
    $tasks = $template->tasks;

    $tasks[$taskToRemoveLines]['nextstep'] = '';
    $tasks[$taskToRemoveLines]['nextfalsestep'] = '';
    // Now remove this task from the tasks that point to it, on both branches.
    foreach ($pointers as $pointer) {
      foreach (['nextstep', 'nextfalsestep'] as $property) {
        $steps = explode(',', $tasks[$pointer][$property] ?? '');
        $tasks[$pointer][$property] = implode(',', array_diff($steps, [$taskToRemoveLines]));
      }
    }
    $template->tasks = $tasks;
    $template->save();

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
    $response->addCommand(new FireJavascriptCommand('maestroRemoveTaskLines', ['task' => $taskToRemoveLines]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $templateMachineName = '') {
    $template = MaestroEngine::getTemplate($templateMachineName);
    // If this template we're editing doesn't actually exist, bail.
    if ($template == NULL) {
      return [
        '#title' => $this->t('Error!'),
        '#markup' => $this->t("The template you are attempting to add a task to doesn't exist"),
      ];
    }

    $validated_css = 'maestro-template-validation-div-hide';
    if (!property_exists($template, 'validated') || !$template->validated) {
      $validated_css = '';
    }

    $form = [
      '#markup' => '<div id="maestro-template-error" class="messages messages--error"></div>',
    ];
    $form['#title'] = $this->t('Editing Template') . ': ' . $template->label;

    // Changes to a template with open processes take effect immediately, so warn about them.
    $openProcesses = \Drupal::entityQuery('maestro_process')
      ->accessCheck(FALSE)
      ->condition('template_id', $templateMachineName)
      ->condition('complete', 0)
      ->count()
      ->execute();
    if ($openProcesses > 0) {
      $form['open_processes'] = [
        '#markup' => '<div id="maestro-template-open-processes" class="messages messages--error fade-out">'
          . $this->t('WARNING! This template has open processes. If you alter this template it could cause execution problems or errors with the open processes.')
          . '</div>',
      ];
    }

    $manager = \Drupal::service('plugin.manager.maestro_tasks');
    $plugins = $manager->getDefinitions();

    // The template's tasks, passed to the canvas in drupalSettings.
    $tasks = [];
    foreach ($template->tasks as $taskID => $task) {
      // A task without a type can't be drawn or edited; report it instead of crashing the builder.
      if (empty($task['tasktype'])) {
        $this->messenger()->addError($this->t('Task %id has no task type, so it is not shown and cannot be edited here. Remove it from the template configuration, e.g. with: drush config:edit maestro.maestro_template.@template', [
          '%id' => $taskID,
          '@template' => $templateMachineName,
        ]));
        continue;
      }
      $capabilities = [];
      $this_task = MaestroEngine::getPluginTask($task['tasktype']);
      if (!$this_task) {
        $this->messenger()->addError($this->t('Task type %tasktype is not available!', ['%tasktype' => $task['tasktype']]));
      }
      else {
        $capabilities = $this->prefixCapabilities($this_task->getTemplateBuilderCapabilities());
      }

      $tasks[] = [
        'taskname' => $task['label'],
        'type' => $task['tasktype'],
        'uilabel' => $this->t(str_replace('Maestro', '', $task['tasktype'])),
        'id' => $task['id'],
        'left' => $task['left'],
        'top' => $task['top'],
        'raphael' => '',
        'to' => explode(',', $task['nextstep']),
        'pointedfrom' => '',
        'falsebranch' => explode(',', $task['nextfalsestep']),
        'lines' => [],
        'capabilities' => $capabilities,
        'participate_in_workflow_status_stage' => $task['participate_in_workflow_status_stage'] ?? '',
        'workflow_status_stage_number' => $task['workflow_status_stage_number'] ?? '',
        'workflow_status_stage_message' => isset($task['workflow_status_stage_message']) ? $this->t('Status Message') . ': ' . $task['workflow_status_stage_message'] : '',
      ];
    }

    // Colours of every task type, and the palette's task types with their labels and
    // builder capabilities.
    $taskColours = [];
    $taskTypes = [];
    $taskCapabilities = [];
    foreach ($plugins as $plugin_id => $plugin) {
      $taskPlugin = $manager->createInstance($plugin_id, [0, 0]);
      $taskColours[$plugin_id] = $taskPlugin->getTaskColours();
      // The Start task is never added from the palette.
      if ($plugin_id != 'MaestroStart') {
        $taskTypes[$plugin_id] = $taskPlugin->shortDescription();
        $taskCapabilities[$plugin_id] = $this->prefixCapabilities($taskPlugin->getTemplateBuilderCapabilities());
      }
    }
    // List the palette alphabetically.
    asort($taskTypes);

    $form['back_to_templates'] = [
      '#type' => 'link',
      '#title' => $this->t('templates'),
      '#url' => Url::fromRoute('entity.maestro_template.list'),
      '#attributes' => [
        'title' => $this->t('Back to the Maestro Templates list'),
        'class' => ['maestro-action-button'],
      ],
    ];

    $form['run_validity_check'] = [
      '#type' => 'link',
      '#title' => $this->t('validity check'),
      '#url' => Url::fromRoute('maestro_reactflow_template_builder.validity_check', ['templateMachineName' => $templateMachineName]),
      '#attributes' => [
        'title' => $this->t('Perform Validity Check'),
        'class' => ['use-ajax', 'maestro-action-button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => '90%',
        ]),
      ],
    ];

    // Modal to edit the template. The canvas is placed right after it; it fills the
    // container's width (css/template.css) and the window's height (js/template_bridge.js),
    // so the template's diagram-js canvas_width and canvas_height are not used.
    $form['edit_template'] = [
      '#type' => 'link',
      '#title' => $this->t('edit template'),
      '#suffix' => '<div id="maestro-template-validation" class="fade-out maestro-template-validation-div messages messages--error ' . $validated_css . '">'
        . $this->t('This template requires validation before it can be used.') . '</div>'
        . '<div id="template_canvas"></div>',
      '#url' => Url::fromRoute('entity.maestro_template.edit_form', ['maestro_template' => $templateMachineName, 'is_modal' => 'modal']),
      '#attributes' => [
        'title' => $this->t('Edit Template'),
        'resizable' => TRUE,
        'class' => ['use-ajax', 'maestro-action-button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'dialogClass' => 'modal-task-edit',
          'position' => [
            'my' => 'center',
            'at' => 'center',
            'of' => 'window',
          ],
        ]),
      ],
    ];

    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#default_value' => $templateMachineName,
    ];

    // Hidden task menu. Its buttons are triggered by the canvas, which finds them by their
    // maestro_capabilities_id attribute, and its hidden fields carry the canvas's values.
    $form['menu'] = [
      '#type' => 'fieldset',
      '#title' => '',
      '#attributes' => [
        'class' => ['maestro-popup-menu'],
      ],
      '#prefix' => '
        <div id="maestro-task-menu" class="ui-dialog ui-widget ui-widget-content ui-corner-all ui-front">
        <div class="ui-dialog-titlebar ui-widget-header ui-corner-all ui-helper-clearfix">
        <span id="task-menu-title" class="ui-dialog-title">' . $this->t('Task Menu') . '</span>
        <span id="close-task-menu" class="ui-button-icon-primary ui-icon ui-icon-closethick"></span></div>',
      '#suffix' => '</div>',
    ];
    $hiddenFields = [
      'task_clicked',
      'task_line_from',
      'task_line_to',
      'task_top',
      'task_left',
      'pan_top',
      'pan_left',
      'zoom',
      'palette_task_type',
      'palette_task_id',
      'palette_task_label',
    ];
    foreach ($hiddenFields as $field) {
      $form['menu'][$field] = ['#type' => 'hidden'];
    }

    // Hidden ajax buttons, triggered by the canvas by their ID (#edit-<key>).
    $hiddenButtons = [
      'pan_zoom_complete' => ['Pan/Zoom Complete', 'panZoomComplete'],
      'remove_task_complete' => ['Remove', 'removeTaskComplete'],
      'draw_line_complete' => ['Submit Draw Line', 'drawLineComplete'],
      'draw_false_line_complete' => ['Submit False Draw Line', 'drawFalseLineComplete'],
      'move_task_complete' => ['Submit Task Move Coordinates', 'moveTaskComplete'],
      'create_new_task_from_palette_complete' => ['Create New Task', 'createNewTaskFromPalette'],
    ];
    foreach ($hiddenButtons as $key => [$label, $callback]) {
      $form[$key] = [
        '#type' => 'submit',
        '#value' => $label,
        '#ajax' => [
          'callback' => [$this, $callback],
          'wrapper' => '',
        ],
        '#prefix' => '<div class="maestro_hidden_element">',
        '#suffix' => '</div>',
      ];
    }

    // Hidden link to the edit task modal, clicked by the maestroEditTask ajax command
    // once editTask() has stored which task is being edited.
    $form['edit_task_complete'] = [
      '#type' => 'link',
      '#title' => 'Edit Task',
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
      '#url' => Url::fromRoute('maestro_reactflow_template_builder.edit_task', ['templateMachineName' => $templateMachineName]),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => '90%',
          'height' => '75vh',
          'dialogClass' => 'modal-task-edit',
          'position' => [
            'my' => 'center',
            'at' => 'center',
          ],
        ]),
      ],
    ];

    // Task menu buttons, one per builder capability.
    $menuButtons = [
      'edit_this_task' => [$this->t('Edit Task'), 'editTask', 'maestro_template_edit'],
      'draw_line_to' => [$this->t('Draw Line To'), 'drawLineTo', 'maestro_template_drawlineto'],
      'draw_false_line_to' => [$this->t('Draw False Line To'), 'drawFalseLineTo', 'maestro_template_drawfalselineto'],
      'remove_lines' => [$this->t('Remove Lines'), 'removeLines', 'maestro_template_removelines'],
    ];
    foreach ($menuButtons as $key => [$label, $callback, $capability]) {
      $form['menu'][$key] = [
        '#type' => 'button',
        '#value' => $label,
        '#ajax' => [
          'callback' => [$this, $callback],
          'wrapper' => '',
        ],
        '#attributes' => [
          'maestro_capabilities_id' => $capability,
        ],
      ];
    }
    $form['menu']['remove_task_link'] = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => $this->t('Remove Task'),
      '#attributes' => [
        'style' => 'margin-top: 20px;',
        'onclick' => 'Drupal.maestroRemoveTaskMenuSelect(event)',
        'class' => ['button'],
        'maestro_capabilities_id' => 'maestro_template_remove',
        // This form element type does not have an ID by default.
        'id' => 'maestro_template_remove',
      ],
    ];

    $form['#attached'] = [
      'library' => [
        'maestro_reactflow_template_builder/maestro_reactflow_builder',
      ],
      'drupalSettings' => [
        'maestro' => $tasks,
        'maestroTaskColours' => $taskColours,
        'pan_left' => $template->pan_left ?? 0,
        'pan_top' => $template->pan_top ?? 0,
        'zoom' => $template->zoom ?? 1,
        'task_types' => $taskTypes,
        'taskCapabilities' => $taskCapabilities,
      ],
    ];

    $form['#cache'] = [
      'max-age' => 0,
    ];

    $form['#prefix'] = '<div id="template-message-area-one" class="maestro-template-message-area messages messages--status"></div>';

    return $form;
  }

  /**
   * Prefixes task capabilities to match the task menu's maestro_capabilities_id attributes.
   */
  protected function prefixCapabilities(array $capabilities) {
    return array_map(fn($c) => 'maestro_template_' . $c, $capabilities);
  }

}
