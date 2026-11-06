<?php

namespace Drupal\maestro_reactflow_template_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_reactflow_template_builder\Ajax\FireJavascriptCommand;

/**
 * The ReactFlow builder's edit task form, shown in a modal.
 */
class MaestroReactFlowEditTaskForm extends FormBase {

  /**
   * {@inheritdoc}
   *
   * The same ID as the diagram-js builder's edit task form, on purpose: Maestro and
   * its task modules alter the task edit form through
   * hook_form_template_edit_task_alter() (e.g. maestro_form_template_edit_task_alter()),
   * and those alters must apply here too.
   */
  public function getFormId() {
    return 'template_edit_task';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $task = MaestroEngine::getTemplateTaskByID($form_state->getValue('template_machine_name'), $form_state->getValue('task_id'));
    MaestroEngine::getPluginTask($task['tasktype'])->validateTaskEditForm($form, $form_state);
  }

  /**
   * Ajax callback for the Close button.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $this->editingTaskStore()->set('maestro_editing_task', '');
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $templateMachineName = $form_state->getValue('template_machine_name');
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $form_state->getValue('task_id'));
    $executableTask = MaestroEngine::getPluginTask($task['tasktype']);

    // Let the task prepare its own settings, then save them along with the core
    // Maestro settings such as assignments and notifications.
    $executableTask->prepareTaskForSave($form, $form_state, $task);
    if ($executableTask->saveTask($form, $form_state, $task) === FALSE) {
      $this->messenger()->addError($this->t('Error saving your task.'));
    }

    // Rebuild the form to get an updated table of assignment information.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback for the Save Task button; the task was saved in submitForm().
   */
  public function saveForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      // Replaces the form HTML with the validated HTML.
      $response->addCommand(new HtmlCommand('#edit-task-form', $form));
      return $response;
    }

    // Update the task's node on the canvas.
    $task = MaestroEngine::getTemplateTaskByID($form_state->getValue('template_machine_name'), $form_state->getValue('task_id'));
    $update = [
      'label' => $task['label'],
      'taskid' => $task['id'],
      'body' => 'placeholder',
      'participate_in_workflow_status_stage' => $task['participate_in_workflow_status_stage'],
      'workflow_status_stage_number' => $task['workflow_status_stage_number'],
      'workflow_status_stage_message' => $task['workflow_status_stage_message'],
    ];

    $response->addCommand(new FireJavascriptCommand('maestroUpdateMetaData', $update));
    $response->addCommand(new HtmlCommand('#edit-task-form', $form));
    $response->addCommand(new FireJavascriptCommand('maestroShowSavedMessage', []));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $templateMachineName = '') {
    // MaestroReactFlowBuilderForm::editTask() stored the clicked task's ID before opening this modal.
    $taskID = $this->editingTaskStore()->get('maestro_editing_task');
    $template = MaestroEngine::getTemplate($templateMachineName);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskID);
    if ($taskID == '' || $template == NULL || $task == NULL) {
      return [
        '#title' => $this->t('Error!'),
        '#markup' => $this->t('The task or template you are attempting to edit does not exist'),
      ];
    }
    $task['form_state'] = $form_state;

    $form = [
      '#title' => $this->t('Editing Task') . ': ' . $task['label'] . '(' . $taskID . ')',
      '#prefix' => '<div id="edit-task-form">',
      '#suffix' => '</div>',
    ];

    $form['save_task_notification'] = [
      '#markup' => $this->t('Task Saved'),
      '#prefix' => '<div id="save-task-notificaiton" class="messages messages--status">',
      '#suffix' => '</div>',
    ];

    $executableTask = MaestroEngine::getPluginTask($task['tasktype']);
    // The base edit form all tasks share, then the task's own settings, then, for
    // interactive tasks, the assignment and notification settings.
    $form += $executableTask->getBaseEditForm($task, $templateMachineName);
    $form += $executableTask->getTaskEditForm($task, $templateMachineName);
    if ($executableTask->isInteractive()) {
      $form += $executableTask->getAssignmentsAndNotificationsForm($task, $templateMachineName);
    }

    // The Spawn Sub Flow task links its chosen template to the diagram-js builder,
    // which may not be installed; open it in this builder instead.
    if (isset($form['maestro_sub_flow_label']['#url']) && $form['maestro_sub_flow_label']['#url'] instanceof Url) {
      $form['maestro_sub_flow_label']['#url'] = Url::fromRoute('maestro_reactflow_template_builder.builder', $form['maestro_sub_flow_label']['#url']->getRouteParameters());
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Task'),
      '#required' => TRUE,
      '#ajax' => [
        // Use saveForm rather than submitForm to avoid calling a save handler twice.
        'callback' => [$this, 'saveForm'],
        'wrapper' => '',
      ],
    ];
    $form['actions']['close'] = [
      '#type' => 'button',
      '#value' => $this->t('Close'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'cancelForm'],
        'wrapper' => '',
      ],
    ];
    return $form;
  }

  /**
   * The tempstore holding the ID of the task being edited.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The builder's private tempstore.
   */
  protected function editingTaskStore() {
    return \Drupal::service('tempstore.private')->get(MaestroReactFlowBuilderForm::TEMPSTORE);
  }

}
