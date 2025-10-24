<?php

namespace Drupal\maestro_template_builder\Form;

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\maestro_template_builder\Ajax\FireJavascriptCommand;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class MaestroTemplateBuilderForm extends FormBase {

  protected $tempStore;

  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory->get('maestro_template_builder');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_template_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Ajax callback to set a session variable that we use to then signal the modal dialog for task editing to appear.
   */
  public function editTask(array &$form, FormStateInterface $form_state) {
    // Set a task editing session variable here.  We use the tempstore to retain the edited task's ID.
    $this->tempStore->set('maestro_editing_task', $form_state->getValue('task_clicked'));
    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    $response->addCommand(new FireJavascriptCommand('maestroEditTask', []));
    return $response;
  }

  public function panZoomComplete(array &$form, FormStateInterface $form_state) {
    $top = $form_state->getValue('pan_top');
    $left = $form_state->getValue('pan_left');
    $zoom = $form_state->getValue('zoom');
    
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);
    $template->zoom = $zoom;
    $template->pan_top = $top;
    $template->pan_left = $left;
    $template->save();

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroNoOp', []));
    return $response;
  }

  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function moveTaskComplete(array &$form, FormStateInterface $form_state) {
    $taskMoved = $form_state->getValue('task_clicked');
    $top = $form_state->getValue('task_top');
    $left = $form_state->getValue('task_left');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $template->tasks[$taskMoved]['top'] = $top;
    $template->tasks[$taskMoved]['left'] = $left;
    $template->save();

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroNoOp', []));
    return $response;
  }

  public function createNewTaskFromPalette(array &$form, FormStateInterface $form_state) {
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $new_task_type = $form_state->getValue('palette_task_type');
    $new_task_id = $form_state->getValue('palette_task_id');
    $new_task_label = $form_state->getValue('palette_task_label');
    $top = $form_state->getValue('task_top');
    $left = $form_state->getValue('task_left');

    $template->tasks[$new_task_id] = [
      'id' => $new_task_id,
      'label' => $new_task_label,
      'tasktype' => $new_task_type,
      'nextstep' => '',
      'nextfalsestep' => '',
      'top' => $top,
      'left' => $left,
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
   * Ajax callback to complete the line drawing routine when the second task has been selected.
   */
  public function drawLineComplete(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_line_from');
    $taskTo = $form_state->getValue('task_line_to');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $templateTaskFrom = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    $templateTaskTo = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskTo);

    $nextsteps = explode(',', $templateTaskFrom['nextstep']);
    if (!array_search($taskTo, $nextsteps)) {
      // Add it to the task.
      if ($templateTaskFrom['nextstep'] != '') {
        $templateTaskFrom['nextstep'] .= ',';
      }
      $templateTaskFrom['nextstep'] .= $taskTo;
      $template->tasks[$taskFrom]['nextstep'] = $templateTaskFrom['nextstep'];
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
   * Ajax callback to signal the UI to go into line drawing mode.
   */
  public function drawLineTo(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    if ($task['tasktype'] == 'MaestroEnd') {
      $response = new AjaxResponse();
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => $this->t('You are not able to draw a line FROM an end task!')]));
      return $response;
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroDrawLineTo', ['taskid' => $taskFrom]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback to signal the UI to go into line drawing mode.
   */
  public function drawFalseLineTo(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    if ($task['tasktype'] == 'MaestroEnd') {
      $response = new AjaxResponse();
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => $this->t('You are not able to draw a line FROM an end task!')]));
      return $response;
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroDrawFalseLineTo', ['taskid' => $taskFrom]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback to complete the false line drawing routine when the second task has been selected.
   */
  public function drawFalseLineComplete(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_line_from');
    $taskTo = $form_state->getValue('task_line_to');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $templateTaskFrom = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    $templateTaskTo = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskTo);

    $nextsteps = explode(',', $templateTaskFrom['nextfalsestep']);
    if (!array_search($taskTo, $nextsteps)) {
      // Add it to the task.
      if ($templateTaskFrom['nextfalsestep'] != '') {
        $templateTaskFrom['nextfalsestep'] .= ',';
      }
      $templateTaskFrom['nextfalsestep'] .= $taskTo;
      $template->tasks[$taskFrom]['nextfalsestep'] = $templateTaskFrom['nextfalsestep'];
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
   * Ajax callback to remove a task from the template.
   */
  public function removeTaskComplete(array &$form, FormStateInterface $form_state) {
    $taskToRemove = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $response = new AjaxResponse();
    if ($taskToRemove == 'start') {
      // can't delete the start task!
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => $this->t('You are not able to delete a Start task')]));
      return $response;
    }

    $returnValue = MaestroEngine::removeTemplateTask($templateMachineName, $taskToRemove);
    if ($returnValue === FALSE) {
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => $this->t('There was an error removing the Task. Unable to Complete the removal')]));
      return $response;
    }
    else {
      MaestroEngine::setTemplateToUnvalidated($templateMachineName);
      $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
      $response->addCommand(new FireJavascriptCommand('maestroRemoveTask', ['task' => $taskToRemove]));
      return $response;
    }
  }

  /**
   * Ajax callback to remove the lines pointing to and from a task.
   */
  public function removeLines(array &$form, FormStateInterface $form_state) {
    $taskToRemoveLines = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');

    $template = MaestroEngine::getTemplate($templateMachineName);
    $templateTask = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskToRemoveLines);
    $pointers = MaestroEngine::getTaskPointersFromTemplate($templateMachineName, $taskToRemoveLines);
    $tasks = $template->tasks;

    // We now have $templateTask that needs the 'nextstep' parameter cleared.
    $tasks[$taskToRemoveLines]['nextstep'] = '';
    $tasks[$taskToRemoveLines]['nextfalsestep'] = '';
    // Now to remove this task from the tasks that point to it.
    foreach ($pointers as $pointer) {
      $nextsteps = explode(',', $tasks[$pointer]['nextstep']);
      $key = array_search($taskToRemoveLines, $nextsteps);
      if ($key !== FALSE) {
        unset($nextsteps[$key]);
      }
      $tasks[$pointer]['nextstep'] = implode(',', $nextsteps);
      // How about false branches now.
      $nextfalsesteps = explode(',', $tasks[$pointer]['nextfalsestep']);
      $key = array_search($taskToRemoveLines, $nextfalsesteps);
      if ($key !== FALSE) {
        unset($nextfalsesteps[$key]);
      }
      $tasks[$pointer]['nextfalsestep'] = implode(',', $nextfalsesteps);
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
    $config = \Drupal::config('maestro_template_builder.settings');
    $template = MaestroEngine::getTemplate($templateMachineName);
    // If this template we're editing doesn't actually exist, bail.
    if ($template == NULL) {
      $form = [
        '#title' => $this->t('Error!'),
        '#markup' => $this->t("The template you are attempting to add a task to doesn't exist"),
      ];
      return $form;
    }

    $validated_css = 'maestro-template-validation-div-hide';
    if ((property_exists($template, 'validated') && !$template->validated) || !property_exists($template, 'validated')) {
      $validated_css = '';
    }

    $form = [
      '#markup' => '<div id="maestro-template-error" class="messages messages--error"></div>',
    ];
    $form['#title'] = $this->t('Editing Template') . ': ' . $template->label;
    // If this template has open processes, show a warning stating that any changes made to this template will happen 
    // immediately and could cause issues.
    $query = \Drupal::entityQuery('maestro_process')
      ->accessCheck(FALSE)
      ->condition('template_id', $templateMachineName)
      ->condition('complete', 0);
    $res = $query->execute();
    $count = count($res);
    if($count > 0) {
      $form['open_processes'] = [
        '#markup' => 
          '<div id="maestro-template-open-processes" class="messages messages--error fade-out">' .
          $this->t('WARNING! This template has open processes. If you alter this template it could cause execution problems or errors with the open processes.') . 
          '</div>',
      ]; // end 
    }


    $height = $template->canvas_height;
    $width = $template->canvas_width;
    $zoom = $template->zoom ?? 1;
    $pan_left = $template->pan_left ?? 0;
    $pan_top = $template->pan_top ?? 0;
    
    // Allow the task to define its own colours
    // these are here for now.
    $taskColours = [
      'MaestroStart' => '#00ff00',
      'MaestroEnd'   => '#ff0000',
      'MaestroSetProcessVariable' => '#a0a0a0',
      'MaestroTaskTypeIf' => 'orange',
      'MaestroInteractive' => '#0000ff',
    ];

    /*
     * We build our task array here
     * This array is passed to DrupalSettings and used in the template UI
     */
    $tasks = [];
    foreach ($template->tasks as $taskID => $task) {
      // Fetch this task's template builder capabilities.
      $this_task = MaestroEngine::getPluginTask($task['tasktype']);
      if(!$this_task) {
        \Drupal::messenger()->addError($this->t('Task type %tasktype is not available!', ['%tasktype' => $task['tasktype']]));
      } else {
        $capabilities = $this_task->getTemplateBuilderCapabilities();
        // For our template builder, we'll prefix each capability with "maestro_template_".
        foreach ($capabilities as $key => $c) {
          $capabilities[$key] = 'maestro_template_' . $c;
        }
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
        'participate_in_workflow_status_stage' => isset($task['participate_in_workflow_status_stage']) ? $task['participate_in_workflow_status_stage'] : '',
        'workflow_status_stage_number' => isset($task['workflow_status_stage_number']) ? $task['workflow_status_stage_number'] : '',
        'workflow_status_stage_message' => isset($task['workflow_status_stage_message']) ? $this->t('Status Message') . ': ' . $task['workflow_status_stage_message'] : '',
      ];
    }

    $taskColours = [];
    $manager = \Drupal::service('plugin.manager.maestro_tasks');
    $plugins = $manager->getDefinitions();
    foreach ($plugins as $key => $taskPlugin) {
      $task = $manager->createInstance($taskPlugin['id'], [0, 0]);
      $taskColours[$key] = $task->getTaskColours();
    }

    /*
     * Run the validity checker
     */
    $form['run_validity_check'] = [
      '#type' => 'link',
      '#title' => $this->t('validity check'),
      // '#suffix' =>'<div id="maestro_div_template" style="width:' . $width . 'px; height: ' . $height . 'px;"></div>',  //right after this button is where we attach our Raphael template builder
      '#url' => Url::fromRoute('maestro_template_builder.maestro_run_validity_check', ['templateMachineName' => $templateMachineName]),
      '#attributes' => [
        'title' => $this->t('Perform Validity Check'),
        'class' => ['use-ajax', 'maestro-action-button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => '90%',
        ]),
      ],
    ];

    /*
     * Modal to edit the template
     */
 
    $form['edit_template'] = [
      '#type' => 'link',
      '#title' => $this->t('edit template'),
      '#suffix' => '<div id="maestro-template-validation" class="fade-out maestro-template-validation-div messages messages--error ' . $validated_css . '">'
          . $this->t('This template requires validation before it can be used.') . '</div>'. 
          '<div id="template_canvas" style="width:' . $width . 'px; height: ' . $height . 'px;"></div>',
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

    /*
     * Need to know which template we're editing.
     */
    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#default_value' => $templateMachineName,
    ];

    /*
     * This is our fieldset menu.  We make this pop up dynamically wherever we want based on css and some simple javascript.
     *
     */
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
        <span id="close-task-menu" class="ui-button-icon-primary ui-icon ui-icon-closethick"></span></div>'
      ,
      '#suffix' => '</div>',
    ];

    // Our field to store which task the edit button was clicked on.
    $form['menu']['task_clicked'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_line_from'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_line_to'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_top'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_left'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['pan_top'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['pan_left'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['zoom'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['palette_task_type'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['palette_task_id'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['palette_task_label'] = [
      '#type' => 'hidden',
    ];

    // Hidden submit ajax button that is called by the JS UI when we pan/zoom the canvas viewbox.
    $form['pan_zoom_complete'] = [
      '#type' => 'submit',
      '#value' => 'Pan/Zoom Complete',
      '#ajax' => [
        'callback' => [$this, 'panZoomComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // This is our built-in task remove button
    // this is hidden as we use the remove_task_link to fire the submit as we just want to make sure
    // that you really do want to delete this task.
    // Hidden submit ajax button that is called by the JS UI when we have acknowledged.
    $form['remove_task_complete'] = [
    // That we really do want to remove the task from the template.
      '#type' => 'submit',
      '#value' => 'Remove',
      '#ajax' => [
        'callback' => [$this, 'removeTaskComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden submit ajax button that is called by the JS UI when we are in line drawing mode.
    $form['draw_line_complete'] = [
    // And the JS UI has detected that we've clicked on the task to draw the line TO.
      '#type' => 'submit',
      '#value' => 'Submit Draw Line',
      '#ajax' => [
        'callback' => [$this, 'drawLineComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden submit ajax button that is called by the JS UI when we are in false line drawing mode.
    $form['draw_false_line_complete'] = [
    // And the JS UI has detected that we've clicked on the task to draw the false line TO.
      '#type' => 'submit',
      '#value' => 'Submit False Draw Line',
      '#ajax' => [
        'callback' => [$this, 'drawFalseLineComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden submit ajax button that is called by the JS UI when we have released a task.
    $form['move_task_complete'] = [
    // During the task's move operation.  This updates the template with task position info.
      '#type' => 'submit',
      '#value' => 'Submit Task Move Coordinates',
      '#ajax' => [
        'callback' => [$this, 'moveTaskComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden link to modal that is called in the JS UI when we have set the appropriate task.
    $form['edit_task_complete'] = [
    // In the UI to be editing.
      '#type' => 'link',
      '#title' => 'Edit Task',
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
      '#url' => Url::fromRoute('maestro_template_builder.edit_task', ['templateMachineName' => $templateMachineName]),
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

    // Hidden submit ajax button that is called by the JS UI when we are adding a new task from the palette.
    $form['create_new_task_from_palette_complete'] = [
      '#type' => 'submit',
      '#value' => 'Create New Task',
      '#ajax' => [
        'callback' => [$this, 'createNewTaskFromPalette'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // End of hidden elements
    // The following are the buttons/links that show up on the task menu.
    $form['menu']['edit_this_task'] = [
      '#type' => 'button',
      '#value' => $this->t('Edit Task'),
      '#ajax' => [
        'callback' => [$this, 'editTask'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_edit',
      ],
    ];

    $form['menu']['draw_line_to'] = [
      '#type' => 'button',
      '#value' => $this->t('Draw Line To'),
      '#ajax' => [
        'callback' => [$this, 'drawLineTo'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_drawlineto',
      ],
    ];

    $form['menu']['draw_false_line_to'] = [
      '#type' => 'button',
      '#value' => $this->t('Draw False Line To'),
      '#ajax' => [
        'callback' => [$this, 'drawFalseLineTo'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_drawfalselineto',
      ],
    ];

    $form['menu']['remove_lines'] = [
      '#type' => 'button',
      '#value' => $this->t('Remove Lines'),
      '#ajax' => [
        'callback' => [$this, 'removeLines'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_removelines',
      ],
    ];

    $form['menu']['remove_task_link'] = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => $this->t('Remove Task'),
      '#attributes' => [
    // Gives us some padding from the other task mechanisms.
        'style' => 'margin-top: 20px;',
        'onclick' => 'Drupal.maestroRemoveTaskMenuSelect(event)',
        'class' => ['button'],
        'maestro_capabilities_id' => 'maestro_template_remove',
    // This form element type does not have an ID by default.
        'id' => 'maestro_template_remove',
      ],
    ];
    // End of visible task menu items.

    // We need to provide the Diagram-js Palette bar our available tasks. List them here
    $manager = \Drupal::service('plugin.manager.maestro_tasks');
    $plugins = $manager->getDefinitions();

    $task_types = [];
    foreach ($plugins as $plugin) {
      if ($plugin['id'] != 'MaestroStart') {
        
        $task = $manager->createInstance($plugin['id'], [0, 0]);
        $task_types[$task->getPluginId()] = $task->shortDescription();
      }
    }
    asort($task_types); // Get the tasks listed in alphabetical order

    // Task Type Capabilities
    $capabilities = [];
    foreach ($plugins as $plugin) {
      if ($plugin['id'] != 'MaestroStart') {
        $task = $manager->createInstance($plugin['id'], [0, 0]);
        $pluginCapabilies = $task->getTemplateBuilderCapabilities();
        $prefix = 'maestro_template_';
        // This prefix exists on each of our Maestro Task Menu buttons.  The buttons represent any of the 
        // available capabilities of a task type for which we need to append this prefix to in order for
        // the JS routine to show the appropriate buttons for the task in question.
        $prefixedCapabilities = array_map(function($value) {
            return 'maestro_template_' . $value;
        }, $pluginCapabilies);
        $capabilities[$task->getPluginId()] = $prefixedCapabilities;
      }
    }

    // Set our pan/zoom delay from our config option.
    $pan_zoom_delay = $config->get('maestro_template_builder_pan_zoom_delay') ?? '1500';

    $form['#attached'] = [
      'library' => [
        'maestro_template_builder/maestro_template_builder_diagram',
        'maestro_template_builder/maestro_template_css',
      ],
      'drupalSettings' => [
        'maestro' => ($tasks), // These are the template's tasks generated at the beginning of this form.
        'maestroTaskColours' => ($taskColours),
        'baseURL' => base_path(),
        'canvasHeight' => $height,
        'canvasWidth' => $width,
        'pan_left' => $pan_left,
        'pan_top' => $pan_top,
        'zoom' => $zoom,
        'task_types' => $task_types,
        'taskCapabilities' => $capabilities,
        'panZoomDelay' => $pan_zoom_delay,
      ],
    ];

    $form['#cache'] = [
      'max-age' => 0,
    ];

    // Notification areas at the top and bottom of the editor to ensure that messages appear in both places so people can see them.
    // we send notifications to the divs by class name in the jQuery UI portion of the editor.
    $form['#prefix'] = '<div id="template-message-area-one" class="maestro-template-message-area messages messages--status"></div>';

    return $form;
  }

}
