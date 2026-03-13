<?php

namespace Drupal\maestro\Plugin\EngineTasks;

use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Form\MaestroExecuteInteractive;

/**
 * Maestro And Task Plugin.
 *
 * The plugin annotations below should include:
 * id: The task type ID for this task.  For Maestro tasks, this is Maestro[TaskType].
 *     So for example, the start task shipped by Maestro is MaestroStart.
 *     The Maestro End task has an id of MaestroEnd
 *     Those task IDs are what's used in the engine when a task is injected into the queue.
 *
 * @Plugin(
 *   id = "MaestroAnd",
 *   task_description = @Translation("The Maestro Engine's And task."),
 * )
 */
class MaestroAndTask extends PluginBase implements MaestroEngineTaskInterface {

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
    return $this->t('And Task');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('And Task.');
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\Component\Plugin\PluginBase::getPluginId()
   */
  public function getPluginId() {
    return 'MaestroAnd';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#daa520';
  }

  /**
   * Part of the ExecutableInterface
   * Execution of the AND task returns TRUE when all pointers are complete and FALSE when still waiting..
   * {@inheritdoc}.
   */
  public function execute() {
    $queueIdCount = 0;

    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($this->processID);
    $taskMachineName = MaestroEngine::getTaskIdFromQueueId($this->queueID);
    // Determine who is pointing at me. Specifically, IF tasks will show up in either true or false branches.
    // All other task types will be true pointers.
    $truePointers = MaestroEngine::getTaskTruePointersFromTemplate($templateMachineName, $taskMachineName);
    $falsePointers = MaestroEngine::getTaskFalsePointersFromTemplate($templateMachineName, $taskMachineName);
    // Let's just keep a count of all pointers for counting purposes.
    $pointers = MaestroEngine::getTaskPointersFromTemplate($templateMachineName, $taskMachineName);

    // We now detect if all the true pointers and all the false pointers have been completed

    // TRUE pointers:
    $query = \Drupal::entityQuery('maestro_queue')
      ->accessCheck(FALSE);

    $andMainConditions = $query->andConditionGroup()
      ->condition('status', TASK_STATUS_SUCCESS) // This is a true branch (default for all tasks other than IF)
      ->condition('archived', '2', '!=')
      ->condition('process_id', $this->processID);

    $orConditionGroup = $query->orConditionGroup();
    foreach ($truePointers as $taskID) {
      $orConditionGroup->condition('task_id', $taskID);
    }

    $andMainConditions->condition($orConditionGroup);
    $query->condition($andMainConditions);

    $queueIdCount = $query->count()->execute();


    // FALSE pointers:
    if ($falsePointers) {
      $query = \Drupal::entityQuery('maestro_queue')
        ->accessCheck(FALSE);

      $andMainConditions = $query->andConditionGroup()
        ->condition('status', TASK_STATUS_FALSE_BRANCH) // This is specifically for tasks that follow a false branch like IF
        ->condition('archived', '2', '!=')
        ->condition('process_id', $this->processID);

      $orConditionGroup = $query->orConditionGroup();
      foreach ($falsePointers as $taskID) {
        $orConditionGroup->condition('task_id', $taskID);
      }

      $andMainConditions->condition($orConditionGroup);
      $query->condition($andMainConditions);

      $queueIdCount += $query->count()->execute(); // Add them up.
    }

    if (count($pointers) == $queueIdCount) { // The total count should equate to the true and false pointers
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {} //we don't do anything in the AND

  /**
   * {@inheritDoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritDoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {

  }

  /**
   * {@inheritDoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    // Nothing to validate.
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
