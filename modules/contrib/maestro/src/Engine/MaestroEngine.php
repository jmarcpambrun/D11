<?php

namespace Drupal\maestro\Engine;

// Used in the dynamic task call for task execution.
use Drupal\maestro\Engine\Exception\MaestroGeneralException;
use Drupal\maestro\Engine\Exception\MaestroSaveEntityException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Component\Utility\Crypt;
use Drupal\user\Entity\User;
use Drupal\Core\Url;

/**
 * Class MaestroEngine.
 *
 * The base class for the v2 Maestro Engine
 * This class provides the base methods for the engine to act upon.
 *
 * API class for programmers is separate
 *
 * @ingroup maestro
 * @author randy
 */
class MaestroEngine {

  /**
   * Set debug to true/false to turn on/off debugging respectively.
   *
   * @var bool
   */
  protected $debug;


  /**
   * Set the development mode of the engine.
   *
   * True will turn on things like cache clearing for entity loads etc.
   *
   * @var bool
   */
  protected $developmentMode = FALSE;

  /**
   * The version of the engine.  Right now, pegged at 2.  D7's engine is v1.
   *
   * @var int
   */
  protected $version = 2;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->debug = FALSE;
    $this->developmentMode = FALSE;
  }

  /*
   * The following methods are exposed as
   * the API for the engine.
   */

  /**
   * EnableDebug method.  Call this method to turn on debug.
   */
  public function enableDebug() {
    $this->debug = TRUE;
  }

  /**
   * DisableDebug.  Call this method to turn off debug.
   */
  public function disableDebug() {
    $this->debug = FALSE;
  }

  /**
   * GetDebug.  Returns TRUE or FALSE depending on whether debug is on or not.
   */
  public function getDebug() {
    return $this->debug;
  }

  /**
   * EnableDevelopmentMode method.  Call this method to turn on devel mode.
   */
  public function enableDevelopmentMode() {
    $this->developmentMode = TRUE;
  }

  /**
   * DisableDevelopmentMode.  Call this method to turn off devel mode.
   */
  public function disableDevelopmentMode() {
    $this->developmentMode = FALSE;
  }

  /**
   * GetDevelopmentMode.  Returns TRUE or FALSE depending on whether dev mode is on or not.
   */
  public function getDevelopmentMode() {
    return $this->developmentMode;
  }

  /**
   * GetTemplates method
   *  Gets all of the available Maestro templates established in the system.
   *
   * @return array
   *   Array or FALSE
   */
  public static function getTemplates() {
    $entity_store = \Drupal::entityTypeManager()->getStorage('maestro_template');
    return $entity_store->loadMultiple();
  }

  /**
   * GetTemplate
   *   Gets a specific Maestro Template.
   *
   * @param string $machine_name
   *   The machine name of a specific template you wish to fetch.
   *
   * @return array
   *   Array or FALSE
   */
  public static function getTemplate($machine_name) {
    $entity_store = \Drupal::entityTypeManager()->getStorage('maestro_template');
    $maestro_template = $entity_store->load($machine_name);
    return $maestro_template;
  }

  /**
   * GetTemplateTaskByID fetches the task from the config template.
   *
   * @param string $templateMachineName
   *   Machine name for the template.
   * @param string $taskID
   *   Maestro Task ID.
   *
   * @return array|NULL
   *   The template's task definition array on success or NULL on failure.
   */
  public static function getTemplateTaskByID($templateMachineName, $taskID) {
    if ($templateMachineName) {
      $template = self::getTemplate($templateMachineName);
      if ($template) {
        return $template->tasks[$taskID] ?? NULL;
      }
    }

    return FALSE;
  }

  /**
   * Returns the template task based on the task stored in the queue.
   *
   * @param int $queueID
   *   Maestro Queue ID.
   */
  public static function getTemplateTaskByQueueID($queueID) {
    return MaestroEngine::getTemplateTaskByID(MaestroEngine::getTemplateIdFromProcessId(MaestroEngine::getProcessIdFromQueueId($queueID)), MaestroEngine::getTaskIdFromQueueId($queueID));
  }

  /**
   * Get the template variables from the template.
   *
   * @param string $templateMachineName
   *   Maestro machine name for the template.
   *
   * @return array
   *   The template variables.
   */
  public static function getTemplateVariables($templateMachineName) {
    $template = self::getTemplate($templateMachineName);
    return $template->variables;
  }

  /**
   * Get the template's machine name from the process ID.
   *
   * @param int $processID
   *   Maestro Process ID.
   *
   * @return string
   *   Returns the template machine name or FALSE on error.
   */
  public static function getTemplateIdFromProcessId($processID) {
    $processRecord = FALSE;
    if ($processID) {
      $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
    }
    if ($processRecord) {
      return $processRecord->template_id->getString();
    }
    return FALSE;
  }

  /**
   * Saves/updates the task.
   *
   * @param string $templateMachineName
   *   The machine name of the template the task belongs to.
   * @param string $taskMachineName
   *   The machine name of the task itself.
   * @param array $task
   *   The task array representation loaded from the template and augmented as you see fit.
   */
  public static function saveTemplateTask($templateMachineName, $taskMachineName, array $task) {
    $returnValue = FALSE;
    $template = MaestroEngine::getTemplate($templateMachineName);
    $taskID = $task['id'];
    $template->tasks[$taskID] = $task;
    try {
      $returnValue = $template->save();
    }
    catch (EntityStorageException $e) {
      // Something went wrong.  Catching it.  We will return FALSE to signify that it hasn't saved.
      $returnValue = FALSE;
    }
    return $returnValue;
  }

  /**
   * Removes a template task.
   *
   * @param string $templateMachineName
   *   Machine name of the Maestro Template.
   * @param string $taskToRemove
   *   The machine name of the task to remove.
   */
  public static function removeTemplateTask($templateMachineName, $taskToRemove) {
    $returnValue = FALSE;
    $template = MaestroEngine::getTemplate($templateMachineName);
    $templateTask = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskToRemove);
    $pointers = MaestroEngine::getTaskPointersFromTemplate($templateMachineName, $taskToRemove);

    $tasks = $template->tasks;
    unset($tasks[$taskToRemove]);
    // We also have to remove all of the pointers that point to this task.
    foreach ($pointers as $pointer) {
      $nextsteps = explode(',', $tasks[$pointer]['nextstep']);
      $key = array_search($taskToRemove, $nextsteps);
      unset($nextsteps[$key]);
      $tasks[$pointer]['nextstep'] = implode(',', $nextsteps);
    }

    $template->tasks = $tasks;
    try {
      $returnValue = $template->save();
    }
    catch (EntityStorageException $e) {
      // Something went wrong.  Catching it.  We will return FALSE to signify that it hasn't saved.
      $returnValue = FALSE;
    }
    return $returnValue;
  }

  /**
   * Determines which task(s) point to the task in question.
   *
   * @param string $templateMachineName
   *   The template of the task you wish to investigate.
   * @param string $taskMachineName
   *   The task you want to know WHO points to it.
   *
   * @return array
   *   Returns an array of resulting task machine names (IDs) or empty array.
   */
  public static function getTaskPointersFromTemplate($templateMachineName, $taskMachineName) {
    $template = MaestroEngine::getTemplate($templateMachineName);
    $pointers = [];
    foreach ($template->tasks as $task) {
      $nextSteps = explode(',', $task['nextstep']);
      $nextFalseSteps = explode(',', $task['nextfalsestep']);
      if (array_search($taskMachineName, $nextSteps) !== FALSE || array_search($taskMachineName, $nextFalseSteps) !== FALSE) {
        $pointers[] = $task['id'];
      }
    }
    return $pointers;
  }

  /**
   * Determines which FALSE IF task(s) branch(es) point to the task in question.
   *
   * @param string $templateMachineName
   *   The template of the task you wish to investigate.
   * @param string $taskMachineName
   *   The task you want to know WHO points to it.
   *
   * @return array
   *   Returns an array of resulting task machine names (IDs) or empty array.
   */
  public static function getTaskFalsePointersFromTemplate($templateMachineName, $taskMachineName) {
    $template = MaestroEngine::getTemplate($templateMachineName);
    $pointers = [];
    foreach ($template->tasks as $task) {
      $nextFalseSteps = explode(',', $task['nextfalsestep']);
      if (array_search($taskMachineName, $nextFalseSteps) !== FALSE) {
        $pointers[] = $task['id'];
      }
    }
    return $pointers;
  }

  /**
   * Determines which TRUE IDF task(s) branch(es) point to the task in question.
   *
   * @param string $templateMachineName
   *   The template of the task you wish to investigate.
   * @param string $taskMachineName
   *   The task you want to know WHO points to it.
   *
   * @return array
   *   Returns an array of resulting task machine names (IDs) or empty array.
   */
  public static function getTaskTruePointersFromTemplate($templateMachineName, $taskMachineName) {
    $template = MaestroEngine::getTemplate($templateMachineName);
    $pointers = [];
    foreach ($template->tasks as $task) {
      $nextSteps = explode(',', $task['nextstep']);
      if (array_search($taskMachineName, $nextSteps) !== FALSE) {
        $pointers[] = $task['id'];
      }
    }
    return $pointers;
  }

  /**
   * Get the template's machine name from the queue ID.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   *
   * @return string
   *   Returns the task machine name or FALSE on error.
   */
  public static function getTaskIdFromQueueId($queueID) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    if ($queueRecord) {
      return $queueRecord->task_id->getString();
    }
    return FALSE;
  }

  /**
   * Get the Queue Token from the Queue ID.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   *
   * @return string|bool
   *   Returns the Token string or FALSE on failure
   */
  public static function getTokenFromQueueId($queueID) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    if ($queueRecord) {
      return $queueRecord->token->getString();
    }
    return FALSE;
  }

  /**
   * Get the Queue ID from the Queue Token.
   *
   * @param string $token
   *   The Maestro Queue Token.
   *
   * @return int|bool
   *   Returns the Queue ID integer or FALSE on failure
   */
  public static function getQueueIdFromToken($token) {
    $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
    $query->condition('token', $token)
      ->accessCheck(FALSE);
    $entity_ids = $query->execute();
    $val = FALSE;
    if (count($entity_ids) > 0) {
      $val = current($entity_ids);
    }
    return $val;
  }

  /**
   * Get the process ID from the Queue ID.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   *
   * @return int|bool
   *   Returns the process ID integer or FALSE on failure
   */
  public static function getProcessIdFromQueueId($queueID) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    if ($queueRecord) {
      return $queueRecord->process_id->getString();
    }
    return FALSE;
  }

  /**
   * Fetch the variable's value if it exists.  Returns FALSE on not being able to find the variable.
   *
   * @param string $variableName
   *   The variable name you wish to fetch the value of.
   * @param int $processID
   *   The Maestro Process ID in which the variable exists.
   *
   * @return bool|mixed
   *   Returns a boolean (FALSE) on failure or the value of the variable.
   */
  public static function getProcessVariable($variableName, $processID) {
    $query = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->getQuery();
    $query->condition('process_id', $processID)
      ->accessCheck(FALSE)
      ->condition('variable_name', $variableName);
    $entity_ids = $query->execute();
    // We are expecting only 1 result... if any.
    $val = FALSE;
    if (count($entity_ids) > 0) {
      $entityID = current($entity_ids);
      \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->resetCache([$entityID]);
      $processVariableRecord = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->load($entityID);
      $val = $processVariableRecord->variable_value->getString();
    }
    return $val;
  }

  /**
   * Set a process variable's value.
   * Does a post-variable-set call to re-create any production assignments that may rely on this variable's value.
   *
   * @param string $variableName
   *   The variable machine name you wish to set.
   * @param string $variableValue
   *   The variable's value you wish to set.
   * @param int $processID
   *   The Maestro Process ID.
   */
  public static function setProcessVariable($variableName, $variableValue, $processID) {
    $query = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->getQuery();
    $query->condition('process_id', $processID)
      ->accessCheck(FALSE)
      ->condition('variable_name', $variableName);
    $varID = $query->execute();
    if (count($varID) > 0) {
      $varRecord = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->load(current($varID));
      $varRecord->set('variable_value', $variableValue);
      $varRecord->save();
      // TODO: handle an error condition on save here.
      // most likely trap the error and return false.
      // now we determine if there's any production assignments done on this variable
      // by_variable = 1, process_variable needs to match our variable.
      $query = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->getQuery();
      $query ->condition('process_variable', current($varID))
        ->accessCheck(FALSE)
        ->condition('by_variable', '1')
        ->condition('task_completed', '0');
      $entries = $query->execute();
      // we're going to remove these entries and actually do a production assignment.
      $engine = new MaestroEngine();
      $storeAssignmentInfo = [];
      foreach ($entries as $assignmentID) {
        $assignRecord = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->load($assignmentID);
        $queueID = $assignRecord->queue_id->getString();
        $processID = MaestroEngine::getProcessIdFromQueueId($queueID);
        $taskID = MaestroEngine::getTaskIdFromQueueId($queueID);
        $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($processID);
        // We store the assignment keyed on queueID because an assignment may have multiple asignees listed in the production assignments.
        $storeAssignmentInfo[$queueID] = [
          'templateMachineName' => $templateMachineName,
          'taskID' => $taskID,
        ];
        // Now remove this assignment.
        $assignRecord->delete();
      }
      foreach ($storeAssignmentInfo as $queueID => $assingmentInfo) {
        $engine->productionAssignments($assingmentInfo['templateMachineName'], $assingmentInfo['taskID'], $queueID);
      }

      // Now lets call our hooks on a post-save variable change
      // our built-in hook will update the production assignments if they exist.
      \Drupal::moduleHandler()->invokeAll('maestro_post_variable_save',
          [$variableName, $variableValue, $processID]);
    }
  }

  /**
   * Fetch the variable's unique ID from the variables table if it exists.  Returns FALSE on not being able to find the variable.
   *
   * @param string $variableName
   *   The variable's machine name you wish to retrieve the Unique ID for.
   * @param int $processID
   *   The Maestro Process ID.
   *
   * @return bool|mixed
   *   Returns a FALSE on failure or the variable's ID on success.
   */
  public static function getProcessVariableID($variableName, $processID) {
    $query = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->getQuery();
    $query->condition('process_id', $processID)
      ->accessCheck(FALSE)
      ->condition('variable_name', $variableName);
    $entity_ids = $query->execute();
    // We are expecting only 1 result... if any.
    $val = FALSE;
    if (count($entity_ids) > 0) {
      $entityID = current($entity_ids);
      $varRecord = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->resetCache([$entityID]);
      $varRecord = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->load($entityID);
      $val = $varRecord->id->getString();
    }
    return $val;
  }

  /**
   * SetProductionTaskLabel
   * Lets you set the task label for an in-production task.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   * @param string $taskLabel
   *   The string you wish to set the task's label to.
   */
  public static function setProductionTaskLabel($queueID, $taskLabel) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    $queueRecord->set('task_label', $taskLabel);
    $queueRecord->save();
  }

  /**
   * CompleteTask
   * Completes the queue record by setting the status bit to true/1.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   * @param int $userID
   *   The optional userID of the individual who completed this task.
   */
  public static function completeTask($queueID, $userID = 0) {
    $task_completion_time = time();
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    $queueRecord->set('status', TASK_STATUS_SUCCESS);
    $queueRecord->set('uid', $userID);
    $queueRecord->set('completed', $task_completion_time);
    $queueRecord->save();
    // Set the flag in the production assignments if it exists.
    $query = \Drupal::entityQuery('maestro_production_assignments')
      ->accessCheck(FALSE);
    $query->condition('queue_id', $queueID);
    $assignmentIDs = $query->execute();
    foreach ($assignmentIDs as $assignmentID) {
      $assignmentRecord = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->load($assignmentID);
      $assignmentRecord->set('task_completed', 1);
      $assignmentRecord->save();
    }

    // If this task participates in setting of process status, set the completion time.
    $task = MaestroEngine::getTemplateTaskByQueueID($queueID);
    if (isset($task['participate_in_workflow_status_stage']) && $task['participate_in_workflow_status_stage'] == 1) {
      // Query the maestro_process_status for the state entry.
      $process_id = MaestroEngine::getProcessIdFromQueueId($queueID);
      $query = \Drupal::entityQuery('maestro_process_status')
        ->condition('process_id', $process_id)
        ->condition('stage_number', $task['workflow_status_stage_number'])
        ->accessCheck(FALSE);
      $statusEntityIDs = $query->execute();
      foreach ($statusEntityIDs as $entity_id) {
        $statusRecord = \Drupal::entityTypeManager()->getStorage('maestro_process_status')->load($entity_id);
        if ($statusRecord) {
          $statusRecord->set('completed', $task_completion_time);
          $statusRecord->save();
        }
      }
    }
  }

  /**
   * SetTaskStatus
   * Sets a task's status.  Default is success.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   * @param int $status
   *   The status for this task (see defines in .module file)
   */
  public static function setTaskStatus($queueID, $status = TASK_STATUS_SUCCESS) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    $queueRecord->set('status', $status);
    $queueRecord->save();
  }

  /**
   * ArchiveTask
   * Archives the queue record by setting the archive bit to true/1.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   * @param int $archiveStatus
   *   The archive status you wish to set. Default is TASK_ARCHIVE_NORMAL.
   *   Use TASK_ARCHIVE_NORMAL, TASK_ARCHIVE_ACTIVE or TASK_ARCHIVE_REGEN.
   * 
   */
  public static function archiveTask($queueID, $archiveStatus = TASK_ARCHIVE_NORMAL) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    $queueRecord->set('archived',  $archiveStatus);
    $queueRecord->save();
  }

  /**
   * UnArchiveTask
   * The opposite of archiveTask.  Used in management of a flow.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   */
  public static function unArchiveTask($queueID) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    $queueRecord->set('archived', 0);
    $queueRecord->save();
  }

  /**
   * This static method will load the maestro_process entity identified by the processID param and will flag it as complete.
   *
   * @param int $processID
   *   The Maestro Process ID.
   */
  public static function endProcess($processID) {
    $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
    $processRecord->set('complete', PROCESS_STATUS_COMPLETED);
    $processRecord->set('completed', time());
    $processRecord->save();
  }

  /**
   * This static method will load the maestro_process entity identified by the processID param and will flag it as aborted.
   *
   * @param int $processID
   *   The Maestro Process ID.
   */
  public static function abortProcess($processID) {
    $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
    $processRecord->set('complete', PROCESS_STATUS_ABORTED);
    $processRecord->set('completed', time());
    $processRecord->save();
  }

  /**
   * This static method will load the maestro_process entity identified by the processID param and will set the process' label.
   *
   * @param int $processID
   *   The Maestro Process ID.
   * @param string $processLabel
   *   The label you wish to set the Process to.
   */
  public static function setProcessLabel($processID, $processLabel) {
    if ($processLabel) {
      $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
      $processRecord->set('process_name', $processLabel);
      $processRecord->save();
    }
  }

  /**
   * Fetch a user's assigned tasks.  Maestro base functionality will determine user and role assignments.
   * You can implement hook_maestro_post_fetch_assigned_queue_tasks(int userID, array &$entity_ids)
   * to fetch your own custom assignments whether that be by OG or some other assignment method.
   *
   * @param int $userID
   *   The numeric (integer) user ID for the user you wish to get their tasks for.
   *
   * @return array
   *   An array of entity IDs if they exist.  These entity IDs are the IDs from the maestro_queue table
   */
  public static function getAssignedTaskQueueIds($userID) {
    /*
     * Assignments by variable are done in the assignment table identical to that
     * of the regular user or role assignment, however, the only difference is that
     * there is an entity reference to the variable's ID and the by_variable flag
     * is set.  Therefore we can simply look for role and user assignments with
     * this query and return that as the queue IDs assigned to the user.
     *
     * The state of the task item needs to be checked to ensure that it has not been completed.
     */

    \Drupal::entityTypeManager()->getViewBuilder('maestro_production_assignments')->resetCache();
    $account = User::load($userID);
    $userRoles = $account->getRoles(TRUE);

    $query = \Drupal::entityQuery('maestro_production_assignments')
      ->accessCheck(FALSE);

    $andConditionByUserID = $query->andConditionGroup()
      ->condition('assign_id', $account->getAccountName())
      ->condition('assign_type', 'user');

    $orConditionAssignID = $query->orConditionGroup()
      ->condition($andConditionByUserID);

    $roleAND = NULL;
    foreach ($userRoles as $userRole) {
      $roleAND = $query->andConditionGroup()
        ->condition('assign_id', $userRole)
        ->condition('assign_type', 'role');
      $orConditionAssignID
        ->condition($roleAND);
    }

    $query->condition($orConditionAssignID);
    $query->condition('task_completed', '0');
    $query->condition('queue_id.entity.process_id.entity.complete', '0');
    $query->condition('queue_id.entity.archived', '0');
    $query->condition('queue_id.entity.status', '0');

    $assignmentIDs = $query->execute();
    $queueIDs = [];
    foreach ($assignmentIDs as $entity_id) {
      $assignmentRecord = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->load($entity_id);
      $queueIDs[] = $assignmentRecord->queue_id->getString();
    }

    // Now to invoke other modules to add their entity IDs to the already fetched list
    // pass to the invoked handler, the user ID and the current set of entity IDs.
    \Drupal::moduleHandler()->invokeAll('maestro_post_fetch_assigned_queue_tasks', [$userID, &$queueIDs]);
    return $queueIDs;
  }

  /**
   * Returns the queue entity record based on the queueID.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   */
  public static function getQueueEntryById($queueID) {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->resetCache([$queueID]);
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    return $queueRecord;
  }

  /**
   * Returns the process entity record based on the processID.
   *
   * @param int $processID
   *   The Maestro Process ID.
   */
  public static function getProcessEntryById($processID) {
    $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->resetCache([$processID]);
    $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
    return $processRecord;
  }

  /**
   * Returns the $task variable populated with a Maestro Task plugin.
   * See the getExecutableFormFields method in MaestroInteractiveFormBase class to see how you can fetch and use a task.
   *
   * @param string $taskClassName
   *   The task's class name.
   * @param int $processID
   *   Optional.
   * @param int $queueID
   *   Optional.
   *
   * @return mixed|NULL $task The task is returned in the $task variable.  This is one of the plugins defined in Drupal\maestro\Plugin\EngineTasks
   */
  public static function getPluginTask($taskClassName, $processID = 0, $queueID = 0) {
    $manager = \Drupal::service('plugin.manager.maestro_tasks');
    $plugins = $manager->getDefinitions();
    $task = NULL;
    // Ensure that this task type exists.
    if (array_key_exists($taskClassName, $plugins)) {
      $task = $manager->createInstance($plugins[$taskClassName]['id'], [$processID, $queueID]);
    }
    else {
      \Drupal::logger('maestro')->error('Plugin for task %task does not exist', ['%task' => $taskClassName]);
    }
    return $task;
  }

  /**
   * Determines if a user is assigned to execute a specific task.
   *
   * @param int $queueID
   *   The Maestro Queue ID.
   * @param int $userID
   *   The user ID you wish to determine if they can execute the task identified by the Queue ID.
   */
  public static function canUserExecuteTask($queueID, $userID) {
    $queueIDs = self::getAssignedTaskQueueIds($userID);
    $returnValue = FALSE;
    if (array_search($queueID, $queueIDs) !== FALSE) {
      $returnValue = TRUE;
    }
    /*
     * In the event we have a use case where there is no userID (e.g.: known-anonymous-user scenario)
     * we will need to provide a mechanism for Developers to validate a user.
     * Using an ALTER here, we let the developer override the existing value if needed.
     */
    \Drupal::moduleHandler()->alter('maestro_can_user_execute_task', $returnValue, $queueID, $userID);
    return $returnValue;
  }

  /**
   * Returns the assignment records as an associative array (if specified as such), or keyed array with the assigned ID as the
   * key and the assignment as a string, for the specific Queue item.
   *
   * Format of the associative array is.
   *
   * [Assigned ID][
   *   'assign_id' => the assigned ID (username, role name etc),
   *   'by_variable' => the assignment type if by variable or not (fixed or variable),
   *   'assign_type' => the assignment type,
   *   'id' => the ID of the assignment record
   *   ]
   *
   * Alternatively the structure of the keyed single entry array is:
   * [Assigned ID (username, role name, etc.)] => "The Assigned ID":"fixed or variable"
   *
   * @param string $queueID
   *   The queue ID for the fetch to operate on.
   * @param bool $associativeArray
   *   Set to TRUE to have an associative array returned.  FALSE for simple keyed array on the asignees.
   */
  public static function getAssignedNamesOfQueueItem($queueID, $associativeArray = FALSE) {
    $output = [];
    // Lets get the assignments based on this queue ID.
    $query = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('queue_id', $queueID);
    $entity_ids = $query->execute();
    if (count($entity_ids) > 0) {
      foreach ($entity_ids as $assignmentID) {
        $assignRecord = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->load($assignmentID);
        $assignRecord->by_variable->getString() == 0 ? $type = t('Fixed') : $type = t('Variable');
        if ($associativeArray) {
          $output[$assignRecord->assign_id->getString()] = [
            'assign_id' => $assignRecord->assign_id->getString(),
            'by_variable' => $type,
            'assign_type' => $assignRecord->assign_type->getString(),
            'id' => $assignRecord->id->getString(),
          ];
        }
        else {
          // technically, you should only have one assignment record per entity here.  So shouldn't have to
          // worry about having the same username or role or group etc. named twice and having the array overwritten.
          $output[$assignRecord->assign_id->getString()] = $assignRecord->assign_id->getString() . ':' . $type;
        }
      }
    }

    return $output;
  }

  /**
   * Fetches a translatable label for task status.
   *
   * Please see maestro.module for TASK_STATUS defines.
   *
   * @param int $status
   *   The task's status integer value.
   */
  public static function getTaskStatusLabel($status) {
    $return_status = '';
    switch ($status) {
      case TASK_STATUS_ACTIVE:
        $return_status = t('Active');

        break;

      case TASK_STATUS_ABORTED:
        $return_status = t('Aborted');

        break;

      case TASK_STATUS_CANCEL:
        $return_status = t('Cancelled');

        break;

      case TASK_STATUS_HOLD:
        $return_status = t('On Hold');

        break;

      case TASK_STATUS_SUCCESS:
        $return_status = t('Successfully Completed');

        break;

      case TASK_STATUS_FALSE_BRANCH:
        $return_status = t('False Branch Completed');
        break;

      default:
        $return_status = t('Unknown');
        break;
    }
    return $return_status;
  }

  /**
   * Fetches the task status values in an associative array where the key
   * is the status ID and the value is the translated string.
   */
  public static function getTaskStatusArray() {
    $arr = [];
    $arr[TASK_STATUS_ACTIVE] = t('Active');
    $arr[TASK_STATUS_SUCCESS] = t('Successfully Completed');
    $arr[TASK_STATUS_CANCEL] = t('Cancelled');
    $arr[TASK_STATUS_HOLD] = t('On Hold');
    $arr[TASK_STATUS_ABORTED] = t('Aborted');
    $arr[TASK_STATUS_FALSE_BRANCH] = t('False Branch Completed');
    return $arr;
  }

  /**
   * Fetches the task archival status values in an associative array where the key
   * is the archival status ID and the value is the translated string.
   */
  public static function getTaskArchiveArray() {
    $arr = [];
    $arr[TASK_ARCHIVE_ACTIVE] = t('Active');
    $arr[TASK_ARCHIVE_NORMAL] = t('Archived');
    $arr[TASK_ARCHIVE_REGEN] = t('Regenerated');
    return $arr;
  }

  /**
   * Checks the validity of the template in question.
   *
   * @param string $templateMachineName
   *   The machine name of the template.
   *
   * @return array
   *   Will return an array with keys 'failures' and 'information' with each array contains an array
   *   in the form of ['taskID', 'taskLabel', 'reason'] that fail or create information during the validity check.
   *   Empty 'failures' key array if no issues.
   */
  public static function performTemplateValidityCheck($templateMachineName) {
    $template = MaestroEngine::getTemplate($templateMachineName);
    $pointers = [];
    $endTaskExists = FALSE;
    $validation_failure_tasks = [];
    $validation_information_tasks = [];
    foreach ($template->tasks as $task) {
      // Now determine who points to this task.  If this is an AND or an OR task, you can have multiple pointers.
      // if you're any other type of task, you've broken the validity of the template.
      $pointers = MaestroEngine::getTaskPointersFromTemplate($templateMachineName, $task['id']);
      // $task['tasktype'] holds the task type.
      // this is validation information, not an error.
      if ($task['tasktype'] !== 'MaestroOr' && $task['tasktype'] !== 'MaestroAnd') {
        // We have a non-logical validation failure here.
        if (count($pointers) > 1) {
          $validation_information_tasks[] = [
            'taskID' => $task['id'],
            'taskLabel' => $task['label'],
            'reason' => t('This task, with two pointers to it, will cause a regeneration to happen.  Please see documentation for more information.'),
          ];
        }
      }

      // Now check to see if the task has at least ONE pointer to it OTHER THAN THE START TASK!
      if ($task['tasktype'] !== 'MaestroStart') {
        // No pointers to this task.
        if (count($pointers) == 0) {
          $validation_failure_tasks[] = [
            'taskID' => $task['id'],
            'taskLabel' => $task['label'],
            'reason' => t('Task has no other tasks pointing to it. Only the Start Task is allowed to have no tasks pointing to it.'),
          ];
        }
      }
      if ($task['tasktype'] === 'MaestroEnd') {
        $endTaskExists = TRUE;
      }

      // Now let the task itself determine if it should fail the validation.
      $executable_task = NULL;
      $executable_task = MaestroEngine::getPluginTask($task['tasktype']);
      if ($executable_task != NULL) {
        $executable_task->performValidityCheck($validation_failure_tasks, $validation_information_tasks, $task);
      }

    }
    // Now we check to see if an end task exists...
    if (!$endTaskExists) {
      $validation_failure_tasks[] = [
        'taskID' => t('No Task ID'),
        'taskLabel' => t('No Task Label'),
        'reason' => t('This template is missing an END Task.  Without an END Task, the process will never be flagged as complete.'),
      ];
    }

    // Anyone else would like to add to or remove from the validation failure list here?  by all means:
    \Drupal::moduleHandler()->invokeAll('maestro_template_validation_check',
        [$templateMachineName, &$validation_failure_tasks, &$validation_information_tasks]);
    // If we have no validation issues, lets set the template to have its validity set to true.
    if (count($validation_failure_tasks) == 0) {
      $template->validated = TRUE;
    }
    else {
      $template->validated = FALSE;
    }
    $template->save();
    return [
      'failures' => $validation_failure_tasks,
      'information' => $validation_information_tasks,
    ];

  }

  /**
   * Sets a template to unvalidated based on machine name.
   *
   * @param string $templateMachineName
   *   The machine name of the template.
   */
  public static function setTemplateToUnvalidated($templateMachineName) {
    $template = MaestroEngine::getTemplate($templateMachineName);
    if ($template !== NULL) {
      $template->validated = FALSE;
      $template->save();
    }
  }

  /**
   * Creates a maestro_entity_identifier entity entry.
   *
   * @param int $processID
   *   The Maestro Process ID.
   * @param string $entityType
   *   The type of entity you are saving.
   * @param string $entityBundle
   *   The bundle of the entity.
   * @param string $taskUniqueID
   *   The unique ID you are associating to the entity identifier.
   * @param string|int $entityID
   *   The ID of the entity.
   *
   * @return int|string|null
   *   The row ID (entity ID) of the newly created maestro_entity_identifiers if successful.  NULL if not successful.
   */
  public static function createEntityIdentifier($processID, $entityType, $entityBundle, $taskUniqueID, $entityID) {
    if (isset($processID) && isset($entityType) && isset($entityBundle) && isset($taskUniqueID) && isset($entityID)) {
      $values = [
        'process_id' => $processID,
        'unique_id' => $taskUniqueID,
        'entity_type' => $entityType,
        'entity_id' => $entityID,
        'bundle' => $entityBundle,
      ];
      $new_entry = \Drupal::entityTypeManager()->getStorage('maestro_entity_identifiers')->create($values);
      $new_entry->save();

      return $new_entry->id();
    }
    return NULL;
  }

  /**
   * Updates an existing maestro_entity_identifiers entity using the maestro_entity_identifiers table ID as the key.
   *
   * @param int $entityIdentifierID
   *   The entity identifier ID.
   * @param string $entityID
   *   The entity ID.
   * @param string $entityType
   *   The type of entity.
   * @param string $entityBundle
   *   The entity bundle.
   * @param string $taskUniqueID
   *   The unique identifier associated to the entity.
   */
  public static function updateEntityIdentifierByEntityTableID($entityIdentifierID, $entityID = NULL, $entityType = NULL, $entityBundle = NULL, $taskUniqueID = NULL) {
    if (isset($entityIdentifierID)) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_entity_identifiers')->load($entityIdentifierID);
      if ($record) {
        if (isset($taskUniqueID)) {
          $record->set('unique_id', $taskUniqueID);
        }
        if (isset($entityType)) {
          $record->set('entity_type', $entityType);
        }
        if (isset($entityID)) {
          $record->set('entity_id', $entityID);
        }
        if (isset($entityBundle)) {
          $record->set('bundle', $entityBundle);
        }
        $record->save();
      }
    }
  }

  /**
   * Fetches the entity identifier (entity_id field) using the process ID and the unique ID given to the entity from the Maestro task.
   * Technically, there should only be one entity identifier for the uniqueID.
   *
   * @param int $processID
   *   The process ID from the workflow.
   * @param string $taskUniqueID
   *   The unique identifier given to the entity in the task definition.
   *
   * @return string
   *   The value of the entity_id field.
   */
  public static function getEntityIdentiferByUniqueID($processID, $taskUniqueID) {
    $value = NULL;

    $query = \Drupal::entityQuery('maestro_entity_identifiers')
      ->condition('process_id', $processID)
      ->condition('unique_id', $taskUniqueID)
      ->accessCheck(FALSE);
    $entityID = current($query->execute());
    if ($entityID) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_entity_identifiers')->load($entityID);
      if ($record) {
        $value = $record->entity_id->getString();
      }
    }
    return $value;
  }

  /**
   * Fetches the full record of the maestro_entity_identifiers entity for the process and unique ID.
   *
   * @param int $processID
   *   The process ID from the workflow.
   * @param string $taskUniqueID
   *   The unique identifier given to the entity in the task definition.
   *
   * @return array
   *   An array of arrays keyed by the task's unique identifier for the entity. Empty array if nothing found.
   */
  public static function getEntityIdentiferFieldsByUniqueID($processID, $taskUniqueID) {
    $retArray = [];
    $query = \Drupal::entityQuery('maestro_entity_identifiers')
      ->condition('process_id', $processID)
      ->condition('unique_id', $taskUniqueID)
      ->accessCheck(FALSE);
    $entityIDs = $query->execute();
    foreach ($entityIDs as $entityID) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_entity_identifiers')->load($entityID);
      if ($record) {
        $retArray[$record->unique_id->getString()] = [
          'unique_id' => $record->unique_id->getString(),
          'entity_type' => $record->entity_type->getString(),
          'bundle' => $record->bundle->getString(),
          'entity_id' => $record->entity_id->getString(),
        ];
      }
    }

    return $retArray;
  }

  /**
   * Fetches the entity identifier (entity_id field) using the maestro_entity_identifiers "id" column.
   *
   * @param int $rowID
   *   The ID column of the entity identifier entity table.
   *
   * @return string
   *   The value of the entity_id field.
   */
  public static function getEntityIdentiferByIdentifierRowID($rowID) {
    $value = NULL;

    $query = \Drupal::entityQuery('maestro_entity_identifiers')
      ->condition('id', $rowID)
      ->accessCheck(FALSE);
    $entityID = current($query->execute());
    $record = \Drupal::entityTypeManager()->getStorage('maestro_entity_identifiers')->load($entityID);
    if ($record) {
      $value = $record->entity_id->getString();
    }

    return $value;
  }

  /**
   * Fetches all of the the entity identifiers in the maestro_entity_identifiers entity for the process.
   *
   * @param int $processID
   *   The Maestro Process ID.
   *
   * @return array
   *   An array of arrays keyed by the task's unique identifier for the entity. Empty array if nothing found.
   */
  public static function getAllEntityIdentifiersForProcess($processID) {
    $retArray = [];
    $query = \Drupal::entityQuery('maestro_entity_identifiers')
      ->condition('process_id', $processID)
      ->accessCheck(FALSE);
    $entityIDs = $query->execute();
    foreach ($entityIDs as $entityID) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_entity_identifiers')->load($entityID);
      if ($record) {
        $retArray[$record->unique_id->getString()] = [
          'unique_id' => $record->unique_id->getString(),
          'entity_type' => $record->entity_type->getString(),
          'bundle' => $record->bundle->getString(),
          'entity_id' => $record->entity_id->getString(),
        ];
      }
    }

    return $retArray;
  }

  /**
   * Fetches all of the the status entries for the process.
   *
   * @param int $processID
   *   The Maestro Process ID.
   *
   * @return array
   *   An array of arrays keyed by the stage number for the message. Empty array if nothing found.
   */
  public static function getAllStatusEntriesForProcess($processID) {
    $retArray = [];
    $query = \Drupal::entityQuery('maestro_process_status')
      ->condition('process_id', $processID)
      ->sort('stage_number', 'ASC')
      ->accessCheck(FALSE);
    $entityIDs = $query->execute();
    foreach ($entityIDs as $entityID) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_process_status')->load($entityID);
      if ($record) {
        $retArray[$record->stage_number->getString()] = [
          'message' => $record->stage_message->getString(),
          'completed' => $record->completed->getString(),
          'stage_number' => $record->stage_number->getString(),
        ];
      }
    }

    return $retArray;
  }

  /**
   * Removes all data elements associated with a process. This includes queue, assignment, status, entity identifiers and variables.
   *
   * @param int $processID
   *   The Maestro Process ID.
   */
  public static function deleteProcess($processID) {
    // Delete queue items.
    $query = \Drupal::entityQuery('maestro_queue')
      ->condition('process_id', $processID)
      ->accessCheck(FALSE);
    $ids = $query->execute();
    foreach ($ids as $queueID) {
      // Delete the assignments.
      $query = \Drupal::entityQuery('maestro_production_assignments')
        ->condition('queue_id', $queueID)
        ->accessCheck(FALSE);
      $entityIDs = $query->execute();
      foreach ($entityIDs as $entityID) {
        $record = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->load($entityID);
        if ($record) {
          $record->delete();
        }
      }
      $queueRecord = MaestroEngine::getQueueEntryById($queueID);
      $queueRecord->delete();
    }

    // Delete entity identifiers.
    $query = \Drupal::entityQuery('maestro_entity_identifiers')
      ->condition('process_id', $processID)
      ->accessCheck(FALSE);
    $entityIDs = $query->execute();
    foreach ($entityIDs as $entityID) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_entity_identifiers')->load($entityID);
      if ($record) {
        $record->delete();
      }
    }

    // Delete process variables.
    $query = \Drupal::entityQuery('maestro_process_variables')
      ->condition('process_id', $processID)
      ->accessCheck(FALSE);
    $entityIDs = $query->execute();
    foreach ($entityIDs as $entityID) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->load($entityID);
      if ($record) {
        $record->delete();
      }
    }

    // Delete the status.
    $query = \Drupal::entityQuery('maestro_process_status')
      ->condition('process_id', $processID)
      ->accessCheck(FALSE);
    $entityIDs = $query->execute();
    foreach ($entityIDs as $entityID) {
      $record = \Drupal::entityTypeManager()->getStorage('maestro_process_status')->load($entityID);
      if ($record) {
        $record->delete();
      }
    }

    // finally, delete the process.
    $processRecord = MaestroEngine::getProcessEntryById($processID);
    if ($processRecord) {
      $processRecord->delete();
    }
  }
  
  /**
   * getQueueItemTaskData  
   * Provide a Maestro Queue ID and this method will return to you the LIVE queue item's task data as an array.
   * The task data is seeded by the task's configuration settings upon creation.
   * @param  int $queueID  
   *   The integer Queue ID you wish to load  
   * @param  string|null $configuration_key  
   *   Optional.  Some tasks save their data within a sub-array. For example, the Maestro Webform task does not use a 
   *   sub-array, however, the Set Process Variable task does use an "spv" sub-array.  
   *   If you omit the configuration key, the return array will be zero-keyed. For example:  
   *   <pre>
   * &nbsp;Array [  
   * &nbsp;&nbsp;&nbsp;&nbsp;[0] => [  
   * &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;....  
   * &nbsp;&nbsp;&nbsp;&nbsp;]  
   * &nbsp;]  
   * </pre>
   * @return array  
   *   Returns an empty array when the queue item is not able to be loaded.  Otherwise an array of the task data.
   */
  public static function getQueueItemTaskData($queueID, $configuration_key = NULL) {
    $maestro_queue = MaestroEngine::getQueueEntryById($queueID);
    $queue_task_data = [];
    if($maestro_queue)   {
      if($configuration_key) {
        $queue_task_data = $maestro_queue->get('task_data')->{$configuration_key} ?? []; // This is LIVE data vs. config data  
      }
      else {
        $queue_task_data = $maestro_queue->get('task_data')->getValue() ?? []; // This is LIVE data vs. config data
      }
    }  
    return $queue_task_data;
  }
  
  /**
   * setQueueItemTaskData  
   * Sets the $taskDataKey key to the $value provided.  If your task data lives under a sub-array key, 
   * specify the key with $configuration_key.
   *
   * @param  int $queueID  
   *   The Maestro Queue ID  
   * @param  string $taskDataKey  
   *   The key you are trying to set to $value  
   * @param  mixed $value  
   *   The value you are setting to the $taskDataKey  
   * @param  string $configuration_key  
   *   Optional.  Some tasks save their data within a sub-array. For example, the Maestro Webform task does not use a 
   *   sub-array, however, the Set Process Variable task does use an "spv" sub-array.  
   * @return void
   */
  public static function setQueueItemTaskData($queueID, $taskDataKey, $value = NULL, $configuration_key = NULL) {
    $maestro_queue = MaestroEngine::getQueueEntryById($queueID);
    $queue_task_data = MaestroEngine::getQueueItemTaskData($queueID, $configuration_key);
    if($configuration_key !== NULL) { // Could be a 0!
      $queue_task_data[0][$configuration_key][$taskDataKey] = $value;
    }
    else {
      $queue_task_data[0][$taskDataKey] = $value;
    }
    $maestro_queue->set('task_data', $queue_task_data);
    $maestro_queue->save();
  }
  
  /**
   * setSuspensionFlag  
   * Provide a Maestro Queue ID and the suspension flag and this will set the 
   * task's queue entry inside of the task_data column's value to the 
   * specified value.
   *
   * @param  int $queueID  
   *   The Maestro Queue ID  
   * @param  mixed $suspension_flag  
   *   The suspension flag value. The built in values are  
   *   MAESTRO_UNSUSPENDED  
   *   MAESTRO_SUSPEND  
   *   MAESTRO_ALLOW_SUSPENDED_COMPLETION 
   * @return void
   */
  public static function setSuspensionFlag($queueID, $suspension_flag = MAESTRO_SUSPEND) {
    MaestroEngine::setQueueItemTaskData($queueID, 'maestro_engine_suspend', $suspension_flag);
    if($suspension_flag == MAESTRO_SUSPEND) {
      MaestroEngine::setTaskStatus($queueID, TASK_STATUS_HOLD);
    }
    else {
      MaestroEngine::setTaskStatus($queueID, TASK_STATUS_ACTIVE);
    }
    
  }

  /**
   * getSuspensionFlag  
   * Provide a Maestro Queue ID and this method will return the suspension flag
   * value.
   *
   * @param  int $queueID  
   *   The Maestro Queue ID.  
   * @return int  
   *   Returns one of the Maestro suspension flag values:  
   *   MAESTRO_UNSUSPENDED  
   *   MAESTRO_SUSPEND  
   *   MAESTRO_ALLOW_SUSPENDED_COMPLETION
   */
  public static function getSuspensionFlag($queueID) {
    $task_data = MaestroEngine::getQueueItemTaskData($queueID);
    $suspension_flag = $task_data[0]['maestro_engine_suspend'] ?? 0;
    return $suspension_flag;
  }
  
  /**
   * getAncestryInformation  
   * Provide a Maestro Queue ID and this method will return the ancestry
   * data stored in the task_data blob.
   *
   * @param  int $queueID  
   *   The Maestro Queue ID  
   * @return array  
   *   An array of populated data holding this task's ancestry.  
   *   Key => value return value is queueID => maestroTaskMachineName.  
   *   Returns an empty array if no ancestry data exists.
   */
  public static function getAncestryInformation($queueID) {
    $task_data = MaestroEngine::getQueueItemTaskData($queueID);
    $ancestry = $task_data[0]['maestro_ancestry_data'] ?? [];
    return $ancestry;
  }
  
  /**
   * setAncestryInformation  
   * Provide an array of ancestry data and this method will set the task_data
   * blob.
   *
   * @param  int $queueID  
   *   The Maestro Queue ID  
   * @param  array $ancestry  
   *   Array of keyed data that is in the format of 
   *   queueID=>maestroTaskMachineName
   * @return void
   */
  public static function setAncestryInformation($queueID, array $ancestry) {
    MaestroEngine::setQueueItemTaskData($queueID, 'maestro_ancestry_data', $ancestry);
  }

  /**
   * getRegenCount  
   * Provide a Maestro Queue ID and this method will return the regen
   * loop count.
   *
   * @param  int $queueID  
   *   The Maestro Queue ID  
   * @return int  
   *   Returns the current Regen loop count. 0 by default.
   */
  public static function getRegenCount($queueID) {
    $regenCount = 0;
    $queueEntity = MaestroEngine::getQueueEntryById($queueID);
    if($queueEntity) {
      $regenCount = $queueEntity->regen_count->value ?? 0;
    }
    return $regenCount;
  }
  
  /**
   * setRegenCount  
   * Provide an int to set the regen count loop to.
   *
   * @param  int $queueID  
   *   The Maestro Queue ID  
   * @param  int $regenCount  
   *   The integer count of the current regeneration loop.
   * @return void
   */
  public static function setRegenCount($queueID, $regenCount) {
    $queueEntity = MaestroEngine::getQueueEntryById($queueID);
    $queueEntity->set('regen_count', $regenCount);
    $queueEntity->save();

  }

  /*
   *************************************
   * End of static api functionality.
   *************************************
   */


  /*
   ***************************************
   * API for dealing with the queue.
   ***************************************
   */

  /**
   * NewProcess
   * Creates a new process in the Maestro content entities based on the template name which is mandatory.
   * This method will only create a new process if the template has the validated property set.
   *
   * @param string $templateName
   *   Machine name of the template you wish to kick off.
   * @param string $startTask
   *   The offset starting task you wish to use as your first task.  Default is 'start'.
   *
   * @return int|bool
   *   Returns the process ID if the engine has started the process.  FALSE if there was an issue.
   *   Please use === to ensure that you are testing for FALSE and not 0 as there may be other issues with the save.
   */
  public function newProcess($templateName, $startTask = 'start') {
    // Pessimistic return.
    $process_id = FALSE;
    $template = $this->getTemplate($templateName);
    if (!isset($template->validated) || $template->validated == FALSE) {
      if ($this->debug) {
        \Drupal::messenger()->addError(t('This template has not been validated.  You must validate before launching.'));
      }
      return FALSE;
    }
    if ($template !== NULL) {
      $values = [
        'process_name' => $template->label,
        'template_id' => $template->id,
        'complete' => 0,
        'initiator_uid' => \Drupal::currentUser()->id(),
      ];
      $new_process = \Drupal::entityTypeManager()->getStorage('maestro_process')->create($values);
      $new_process->save();
      if ($new_process->id()) {
        // The process has been kicked off and we're ready to add the particulars to the queue and variables.
        $process_id = $new_process->id();
        // Now to add variables.
        $variables = $template->variables;
        foreach ($variables as $variable) {
          $values = [
            'process_id' => $process_id,
            'variable_name' => $variable['variable_id'],
            'variable_value' => $variable['variable_value'],
          ];
          // Handle any mandatory variable presetting here.
          switch ($variable['variable_id']) {
            case 'initiator':
              $values['variable_value'] = \Drupal::currentUser()->getAccountName();
              break;

            // Pull from the workflow template and populate the variable.
            case 'workflow_timeline_stage_count':
              $values['variable_value'] = $template->default_workflow_timeline_stage_count;
              break;

            // Starting of any process is step 0.
            case 'workflow_current_stage':
              $values['variable_value'] = 0;
              break;

            // Blank out the current stage/status message.
            case 'workflow_current_stage_message':
              $values['variable_value'] = '';
              break;
          }

          $new_var = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->create($values);
          $new_var->save();
          if (!$new_var->id()) {
            // Throw a maestro exception
            // completion should technically end here for this initiation.
            throw new MaestroSaveEntityException('maestro_process_variable', $variable['variable_id'] . ' failed saving during new process creation.');
          }
        }
        // Now to add the process status and stage information
        // we do this by looping through the tasks and creating an array of values for which we then set
        // the maestro_process_status entity.
        $status_message_array = [];
        foreach ($template->tasks as $task) {
          // Relates to the checkbox on the task editor.
          if (isset($task['participate_in_workflow_status_stage']) && $task['participate_in_workflow_status_stage'] == 1) {
            if (isset($task['workflow_status_stage_number'])) {
              $status_message_array[$task['workflow_status_stage_number']] = $task['workflow_status_stage_message'];
            }
          }
        }
        if (count($status_message_array)) {
          // We have status messages to set.
          foreach ($status_message_array as $stage_number => $stage_message) {
            $values = [
              'process_id' => $process_id,
              'stage_number' => $stage_number,
              'stage_message' => $stage_message,
            ];
            $new_stage = \Drupal::entityTypeManager()->getStorage('maestro_process_status')->create($values);
            $new_stage->save();
            if (!$new_stage->id()) {
              // Throw a maestro exception
              // completion should technically end here for this initiation.
              throw new MaestroSaveEntityException('maestro_process_status', 'Stage ' . $variable['stage_number'] . ' status message failed saving during new process creation.');
            }
          }
        }

        // Now to add the initiating task.
        $start_task = $template->tasks[$startTask];
        $pointers_to_task = MaestroEngine::getTaskPointersFromTemplate($templateName, $startTask);
        $regen_pointers = count($pointers_to_task) ?? 0;
        if (is_array($start_task)) {
          $values = [
            'process_id' => $process_id,
            'task_class_name' => $start_task['tasktype'],
            'task_id' => $start_task['id'],
            'task_label' => $start_task['label'],
            'engine_version' => 2,
            'is_interactive' => $start_task['assignto'] == 'engine' ? 0 : 1,
            'show_in_detail' => isset($start_task['showindetail']) ? $start_task['showindetail'] : 0,
            'handler' => isset($start_task['handler']) ? $start_task['handler'] : '',
            'task_data' => isset($start_task['data']) ? $start_task['data'] : '',
            'status' => 0,
            'run_once' => isset($start_task['runonce']) ? $start_task['runonce'] : 0,
            'regen_count' => 0,
            'regen_pointers' => $regen_pointers,
            'uid' => \Drupal::currentUser()->id(),
            'archived' => 0,
            'started_date' => time(),
            'token' => Crypt::randomBytesBase64(),
          ];

          $queue = \Drupal::entityTypeManager()->getStorage('maestro_queue')->create($values);
          $queue->save();
          if ($queue->id()) {
            // Successful queue insertion
            // do any assignments here.
            $this->productionAssignments($templateName, $startTask, $queue->id());
          }
          else {
            // Throw a maestro exception.
            throw new MaestroSaveEntityException('maestro_queue', $start_task['tasktype'] . ' failed saving new task during new process creation.');
          }
        }
        else {
          // We have an issue here.
          // Throw some sort of exception that we can catch.
          // For now, ignore this case.
          throw new MaestroGeneralException('Start task for template ' . $template->id . ' may be corrupt.');
        }
      }
    }
    return $process_id;
  }

  /**
   * CleanQueue method
   * This is the core method used by the orchestrator to move the process forward and to determine assignments and next steps.
   */
  public function cleanQueue() {
    $config = \Drupal::config('maestro.settings');

    // We first check to see if there are any tasks that need processing
    // we do this by looking at the queue flags to determine if the
    // task is not archived, not completed and hasn't run once.
    $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
    $query->condition('archived', '0')
      ->accessCheck(FALSE)
      ->condition('status', '0')
      ->condition('is_interactive', '0')
      ->condition('run_once', '0')
      ->condition('process_id.entity.complete', '0');
    $entity_ids = $query->execute();
    // This gives us a list of entity IDs that we can use to determine the state of the process and what, if anything, we
    // have to do with this task.
    // now we need interactive tasks that have a completion status.
    $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
    $query->condition('archived', '0')
      ->accessCheck(FALSE)
      ->condition('is_interactive', '1')
    // This allows the interactive tasks to set their status.
      ->condition('status', '0', '<>')
      ->condition('run_once', '1')
      ->condition('process_id.entity.complete', '0');

    $entity_ids += $query->execute();
    ksort($entity_ids);
    foreach ($entity_ids as $queueID) {
      // queueID is the numeric ID of the entity ID.  Load it, but first clear any cache if dev mode is on.
      if ($this->developmentMode) {
        $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->resetCache([$queueID]);
      }
      $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
      $processID = $queueRecord->process_id->getString();
      if ($this->developmentMode) {
        $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->resetCache([$processID]);
      }
      $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
      $taskClassName = $queueRecord->task_class_name->getString();
      $taskID = $queueRecord->task_id->getString();
      $templateMachineName = $processRecord->template_id->getString();
      $suspension_flag = MaestroEngine::getSuspensionFlag($queueID);
      // If the process is not complete AND the task is not suspended
      if ($processRecord->complete->getString() == '0' && $suspension_flag != MAESTRO_SUSPEND) {
        // Execute it!
        $task = $this->getPluginTask($taskClassName, $processID, $queueID);
        // Its a task and not an interactive task.
        if ($task && !$task->isInteractive()) {
          $result = $task->execute();
          if ($result === TRUE) {
            // We now set the task's status and create the next task instance!
            $queueRecord->set('status', $task->getExecutionStatus());
            $queueRecord->set('completed', time());
            $queueRecord->save();
            $archive_status = $this->nextStep($templateMachineName, $taskID, $processID, $queueID, $task->getCompletionStatus());
            $this->archiveTask($queueID, $archive_status);
          }
        }
        else {
          // If it IS an interactive task.
          if ($task && $task->isInteractive()) {
            $archive_status = $this->nextStep($templateMachineName, $taskID, $processID, $queueID, $task->getCompletionStatus());
            $this->archiveTask($queueID, $archive_status);
          }
          else {
            // This plugin task definition doesn't exist and its not interactive, however, throwing an exception will lock up the engine.
            // we will throw the exception here knowing that this exception will lock the engine at this point.
            // This process is doomed for failure anyway, too bad it's causing the engine to stall...
            // suggestion here is to create a new status of "error", apply it to the task, which the engine will skip over and we can report on easily with views.
            throw new MaestroGeneralException('Task definition doesn\'t exist. TaskID:' . $taskID . ' in ProcessID:' . $processID . ' is not flagged as interactive or non-interactive.');
          }
        }
      } //end if process is not completed (0)
    } //end foreach through open queue items

    // Retaining this section for now to determine if we should be closing any open tasks that have a process that is complete.
    // this has no effect on the engine either way.
    /* //Now we check for queue items that need to be archived
    //These are tasks that have a status of 1 (complete) and are not yet archived
    $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
    $query->condition('archived', '0')
    ->condition('status', '0', '<>')
    ->condition('process_id.entity.complete', '0');
    $entity_ids = $query->execute();
    foreach($entity_ids as $queueID) {
    //TODO: pre-archive hook?
    $this->archiveTask($queueID);
    //TODO: post archive hook?
    } */

    // TODO: pull notifications out like this to its own cron hook?
    if ($config->get('maestro_send_notifications')) {
      $currentTime = time();
      // So now only for interactive tasks that are not complete and have aged beyond their reminder intervals.
      $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
      $query->condition('archived', '0')
        ->accessCheck(FALSE)
        ->condition('is_interactive', '1')
        ->condition('status', '0')
        ->condition('run_once', '1')
        ->condition('next_reminder_time', $currentTime, '<')
        ->condition('next_reminder_time', '0', '<>')
        ->condition('reminder_interval', '0', '>');
      $entity_ids = $query->execute();
      // Now for each of these entity_ids, we send out a reminder.
      foreach ($entity_ids as $queueID) {
        // We know that because we're in this loop, these interactive tasks require reminders.
        if ($this->developmentMode) {
          $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->resetCache([$queueID]);
        }
        $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
        $taskMachineName = $queueRecord->task_id->getString();
        $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($queueRecord->process_id->getString());
        // Just days * seconds to get an offset.
        $reminderInterval = intval($queueRecord->reminder_interval->getString()) * 86400;
        // we're in here because we need a reminder.  So do it.
        $this->doProductionAssignmentNotifications($templateMachineName, $taskMachineName, $queueID, 'reminder');
        $queueRecord->set('next_reminder_time', $currentTime + $reminderInterval);
        $queueRecord->set('num_reminders_sent', intval($queueRecord->num_reminders_sent->getString()) + 1);
        $queueRecord->save();
      }

      // Now for escalations.
      $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
      $query->condition('archived', '0')
        ->accessCheck(FALSE)
        ->condition('is_interactive', '1')
        ->condition('status', '0')
        ->condition('run_once', '1')
        ->condition('escalation_interval', 0, '>');
      $entity_ids = $query->execute();
      foreach ($entity_ids as $queueID) {
        // So for only those queue records that have an escalation interval
        // has this task aged beyond the escalation interval number of days since it was created?  if so, notify.
        if ($this->developmentMode) {
          $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->resetCache([$queueID]);
        }
        $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
        $taskMachineName = $queueRecord->task_id->getString();
        $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($queueRecord->process_id->getString());
        $createdTime = $queueRecord->created->getString();
        $numberOfEscalationsSent = intval($queueRecord->num_escalations_sent->getString());
        // First time through, numberOfEscalations is 0... second time it's 1 etc.
        // that means that our interval needs to be numberOfEscalations +1 * the offset of the escalation in days.
        $escalationInterval = (1 + $numberOfEscalationsSent) * (intval($queueRecord->escalation_interval->getString()) * 86400);
        if ($currentTime > ($createdTime + $escalationInterval)) {
          // We need to send out an escalation.
          $this->doProductionAssignmentNotifications($templateMachineName, $taskMachineName, $queueID, 'escalation');
          $queueRecord->set('last_escalation_time', $currentTime);
          $queueRecord->set('num_escalations_sent', intval($queueRecord->num_escalations_sent->getString()) + 1);
          $queueRecord->save();
        }
      }
    }

  } //end cleanQueue

  /*
   ***************************************
   * Protected methods used by the engine.
   ***************************************
   */

  /**
   * NextStep
   * Engine method that determines which is the next step based on the
   * current step and does all assignments as necessary.
   *
   * @param string $template
   *   The Maestro template.
   * @param string $templateTaskID
   *   The machine name of the task.
   * @param int $processID
   *   The Maestro Process ID.
   * @param int $currentQueueID
   *   The Maestro Queue ID of the current step.
   * @param int $completionStatus
   *   The completion status being set.  Definitions found in maestro.module.
   * @return int
   *   Returns either TASK_ARCHIVE_NORMAL or TASK_ARCHIVE_REGEN based on whether
   *   the task caused a regeneration or not.
   */
    protected function nextStep($template, $templateTaskID, $processID, $currentQueueID, $completionStatus) {
    // The return status will allow us to archive the task with the
    // appropriate setting based on regen or no regen.
    $return_status = TASK_ARCHIVE_NORMAL; // No regen is the default.
    $templateTask = $this->getTemplateTaskByID($template, $templateTaskID);
    $regenerationFlag = FALSE;
    $regen_count = MaestroEngine::getRegenCount($currentQueueID);
    // Nextstep or nextfalsestep is a comma separated string of next task machine names to point to.
    $nextSteps = $templateTask['nextstep'];
    // Completion status tells us to point to the false branch.
    if ($completionStatus == MAESTRO_TASK_COMPLETION_USE_FALSE_BRANCH) {
      $nextSteps = $templateTask['nextfalsestep'];
    }
    // TODO:  In the event we have a feature request for extra completion status codes, at this point we've established the core Maestro status codes and,
    // have altered the next steps to respect the IF task scenario.
    // Is there really any other scenario?  We've never come across this issue.  But this would be the spot in-code to allow for a
    // module invocation to alter the next steps based on completion status.
    if ($nextSteps != '') {
      $taskArray = explode(',', $nextSteps);
      foreach ($taskArray as $taskID) {
        // Determine if this task is already present in this instance of the queue/process combo
        // but first, determine if they're trying to recreate a task that has already been completed
        // this is our chance to do an auto-regeneration
        // we also filter for the tasks not being an OR or AND task.
        $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
        // Race condition?  what if its complete and not archived, yet a loopback happens?  Leave for now.
        $query
          ->accessCheck(FALSE)
          ->condition('archived', TASK_ARCHIVE_REGEN, '<>')
          ->condition('status', TASK_STATUS_ACTIVE, '<>')
          ->condition('process_id', $processID)
          ->condition('task_id', $taskID)
          ->condition('regen_count', $regen_count) // Lock to the current regen.
        // Task is not an OR.
          ->condition('task_class_name', 'MaestroOr', '<>')
        // Task is not an AND.
          ->condition('task_class_name', 'MaestroAnd', '<>');
        $entity_ids = $query->execute();
        if (count($entity_ids) == 0) {
          // No regeneration!  this is a straightforward engine carry-on condition
          // in Drupal 7's engine, we had flags to check to see if a task REEEEEEALY wanted to be regenerated.
          // no more.  After 10 years of engine development, we've found that regeneration of all in-prod tasks is the way to go
          // look at the ELSE clause to see the regen.
          $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
          // We don't need to recreate if this thing is already in the queue.
          $query->condition('archived', '0')
            ->accessCheck(FALSE)
            ->condition('status', TASK_STATUS_ACTIVE)
            ->condition('process_id', $processID)
            ->condition('task_id', $taskID);
          $entity_ids = $query->execute();
          // Means we haven't already created it in this process. avoids mimicking the regen issue.
          if (count($entity_ids) == 0) {
            // Detect if this is an AND task we're about to create.
            // If so, see if it has already been completed in this process instance
            // and is not a regen.
            $nextTask = $this->getTemplateTaskByID($template, $taskID);
            if($nextTask['tasktype'] == 'MaestroAnd') {
              $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
              $query->condition('archived', TASK_ARCHIVE_REGEN, '<>')
                ->accessCheck(FALSE)
                ->condition('status', TASK_STATUS_SUCCESS)
                ->condition('process_id', $processID)
                ->condition('task_id', $taskID);
              $entity_ids = $query->execute();
              if (count($entity_ids) == 0) {
                $queueID = $this->createProductionTask($taskID, $template, $processID, $currentQueueID, $regen_count);
              }
            }
            else {
              $queueID = $this->createProductionTask($taskID, $template, $processID, $currentQueueID, $regen_count);
            }
          }
        }
        // REGENERATION.
        else {
          // It is in this area where we are doing a complete loopback over our
          // existing template. After years of development experience and
          // creating many business logic templates, we've found that the
          // overwhelming majority (like 99%) of all templates really do a
          // regeneration of all in-production tasks and that people really
          // do want to do a regeneration.
          // Thus the regen flags have been omitted.  Now we just handle
          // everything with status flags and keep the same process ID.

          // What we do first is to gather all the open tasks.  We will NOT
          // regenerate any of the tasks which share an ancestor that is
          // THIS next task (shares THIS $taskID).

          // Set the task return status to regen archive status.
          $return_status = TASK_ARCHIVE_REGEN;
          $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
          $query->condition('archived', TASK_ARCHIVE_ACTIVE)
            ->condition('status', TASK_STATUS_ACTIVE)
            ->condition('process_id', $processID)
            ->accessCheck(FALSE);
          $entity_ids = $query->execute();
          // We now have a list of all open tasks.  
            foreach($entity_ids as $open_task_id) {
              $task_ancestors = MaestroEngine::getAncestryInformation($open_task_id);
              // Does this task have any ancestors which match the $taskID?
              if(array_search($taskID, $task_ancestors) !== FALSE) {
                // Set these to non active status
                $queueRecord = MaestroEngine::getQueueEntryById($open_task_id);
                $queueRecord->set('archived', TASK_ARCHIVE_REGEN);
                $queueRecord->set('status', TASK_STATUS_SUCCESS);
                $queueRecord->save();
              } 
            }

          // The biggest issue are the AND tasks.  We need to know which tasks the AND has pointing to it and keep those
          // tasks hanging around in the queue in either a completed and archived state or in their fully open, executable state.
          // so we have to first find all AND tasks, and then determine who points to them and leave their archive condition alone.
          $noRegenStatusArray = [];
          // So first, search for open AND tasks:
          $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
          $query
            ->condition('archived', TASK_ARCHIVE_REGEN, '<>')
            ->accessCheck(FALSE)
            ->condition('status', '0')
            ->condition('process_id', $processID)
            ->condition('regen_count', $regen_count)
          // Task is an AND.
            ->condition('task_class_name', 'MaestroAnd');
          // Going to use these IDs to determine who points to them.
          $andIDs = $query->execute();
          if (is_array($andIDs)) {
            $noRegenStatusArray += $andIDs;
          }
          foreach ($andIDs as $entityID) {
            // Load the entity from the queue.
            $queueRecord = MaestroEngine::getQueueEntryById($entityID);
            $pointers = MaestroEngine::getTaskPointersFromTemplate(MaestroEngine::getTemplateIdFromProcessId($processID), $queueRecord->task_id->getString());
            // Now we query the queue to add the pointers to the noRegenStatusArray.
            $query = \Drupal::entityQuery('maestro_queue')
              ->accessCheck(FALSE);
            $andMainConditions = $query->andConditionGroup()
              ->condition('process_id', $processID);
            $orConditionGroup = $query->orConditionGroup();
            foreach ($pointers as $pointer) {
              $orConditionGroup->condition('task_id', $pointer);
            }
            if(count($pointers)) {
              $andMainConditions->condition($orConditionGroup);
            }
            $query->condition($andMainConditions);
            $pointerIDs = $query->execute();
            if (is_array($pointerIDs)) {
              // Add the entity IDs to the tasks that point to the AND as those that shouldn't be flagged.
              $noRegenStatusArray += $pointerIDs;
            }
          }
          // now, we have a list of noRegenStatusArray which are entity IDs in the maestro_queue for which we do NOT change the archive flag for.
          $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
          $query->condition('status', TASK_STATUS_ACTIVE, '<>')
            ->condition('archived', TASK_ARCHIVE_REGEN, '<>')
            ->condition('regen_count', $regen_count)
            ->accessCheck(FALSE)
          // All completed tasks that haven't been regen'd.
            ->condition('process_id', $processID);
          $regenIDs = $query->execute();
          foreach ($regenIDs as $entityID) {
            // Set this queue record to regenerated IF it doesn't exist in the noRegenStatusArray.
            if (array_search($entityID, $noRegenStatusArray) === FALSE) {
              MaestroEngine::archiveTask($entityID, TASK_ARCHIVE_REGEN);
            }
          }

          // And now we create the task being looped back over to:
          $queueID = $this->createProductionTask($taskID, $template, $processID, $currentQueueID, $regen_count + 1);
        }
      }//end foreach next task
        
      }
    else {
      // This is the condition where there isn't a next step listed in the task
      // this doesn't necessarily suggest an end of process though, as that's what
      // the end task is meant for.  However, we have a situation here where
      // the task doesn't specify a next task.  Perhaps this is legitimately the end
      // of a task chain while other parallel tasks continue to execute.
      // We will consider this a NOOP condition and do nothing.
    }

    // If we've regenerated at any point
    if($return_status == TASK_ARCHIVE_REGEN) {
      // Now we update all open tasks to the current regen count + 1
      $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
      $query
        ->accessCheck(FALSE)
        ->condition('status', TASK_STATUS_ACTIVE)
        ->condition('archived', TASK_ARCHIVE_ACTIVE)
        ->condition('process_id', $processID);
      $activeIDs = $query->execute();
      foreach ($activeIDs as $entityID) {
        $queueRecord = MaestroEngine::getQueueEntryById($entityID);
        $queueRecord->set('regen_count', $regen_count + 1);
        $queueRecord->save();
      }

      // Now for any task that has regen_pointers > 1, we move those
      // to the current regen_count and set their archive status.
      // This allows the engine to know that there are tasks that have multiple
      // pointers, that they have been created previously and that if they
      // are being pointed BACK to, we will trigger a regeneration routine.
      // You may run into situations where a closer-to-Start task loop back
      // happens, which in turn closes any tasks which may have parallel regen
      // branches themselves.  When you eventually hit those further-down-the-
      // chain tasks which have multiple pointers, they too with trigger a
      // "regen".  This is because the tasks have multiple pointers, have been
      // created in the queue before, so the engine **thinks** they're
      // being looped back over.  This is a false positive that has no
      // effect on the processing of tasks.

      // Query to get all tasks which are successfully completed (1) AND are
      // normally archived (1) within this process.
      // We also look at ONLY the last regen count. This helps focus the query
      // to the last round of regeneration.
      $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
      $query
        ->accessCheck(FALSE)
        ->condition('regen_pointers', '1', '>')
        ->condition('regen_count', $regen_count)
        ->condition('process_id', $processID)
        ->condition('status', TASK_STATUS_ACTIVE, '<>')
        ->condition('archived', TASK_ARCHIVE_ACTIVE, '<>')
        ->sort('id', 'DESC');
      $regen_pointer_entities = $query->execute();
      foreach($regen_pointer_entities as $entityID) {
        $queueRecord = MaestroEngine::getQueueEntryById($entityID);
        $task_id = $queueRecord->task_id->value ?? '';
        // Does this task already exist as having a regen count we're setting?
        // If so, skip it.
        $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
        $query
          ->accessCheck(FALSE)
          ->condition('regen_count', $regen_count + 1)
          ->condition('process_id', $processID)
          ->condition('task_id', $task_id);
        $existing_queue_entries = $query->execute();
        if(count($existing_queue_entries) == 0) {
          $queueRecord->set('regen_count', $regen_count + 1);
          // Set the archive status to normal archive as the orchestrator
          // is set to ignore regenerated tasks on purpose.
          // We need tasks to exist in normal archive status to be able to
          // detect a loop back condition.
          $queueRecord->set('archived', TASK_ARCHIVE_NORMAL);
          $queueRecord->save();
        }
      }
    }

    return $return_status;
  }//end nextStep

  /**
   * Using the queueID, the task's machine name and the template machine name, we assign the task
   * using the appropriate method defined in the template.
   *
   * @param string $templateMachineName
   *   Machine name of the template.
   * @param string $taskID
   *   Machine name of the task.
   * @param int $queueID
   *   The ID of the queue entity this task belongs to.
   */
  protected function productionAssignments($templateMachineName, $taskID, $queueID) {
    $config = \Drupal::config('maestro.settings');
    $task = $this->getTemplateTaskByID($templateMachineName, $taskID);
    $executableTask = MaestroEngine::getPluginTask($task['tasktype']);
    $assigned = '';
    // For tasks that have not set the assignment as they're engine tasks.
    if (array_key_exists('assigned', $task)) {
      $assigned = $task['assigned'];
    }

    // If the assignment is blank ir set to engine, and it's not an interactive task this is an engine assignment.
    if (($assigned == '' || $assigned == 'engine') && !$executableTask->isInteractive()) {
      return;
    }
    if ($assigned == '' && $executableTask->isInteractive()) {
      // hmm.  this is a condition where the template says there's nobody assigned, yet the task says it's an interactive.
      // TODO:  Throw an error here?  Do an invoke to modules to see if they know why?
      // for now, we return and let it be assigned to the engine.
      return;
    }

    // The format of the assignment is as follows
    // towhat:by:who   example:  user:fixed:admin,role:variable:var_name.
    $assignments = explode(',', $assigned);
    foreach ($assignments as $assignment) {
      $thisAssignment = explode(':', $assignment);
      // [0] is user, role etc.  [1] is how such as fixed or by variable.  [2] is to who or which var
      // Assigned by fixed name.
      if ($thisAssignment[1] == 'fixed') {
        $values = [
          'queue_id' => $queueID,
          'assign_type' => $thisAssignment[0],
          'by_variable' => 0,
          'assign_id' => $thisAssignment[2],
          'process_variable' => 0,
          'assign_back_id' => 0,
          'task_completed' => 0,
        ];
        $prodAssignments = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->create($values);
        $prodAssignments->save();
      }
      // Assigned by variable.
      elseif ($thisAssignment[1] == 'variable') {
        $var = MaestroEngine::getProcessVariable($thisAssignment[2], MaestroEngine::getProcessIdFromQueueId($queueID));
        $varID = MaestroEngine::getProcessVariableID($thisAssignment[2], MaestroEngine::getProcessIdFromQueueId($queueID));
        // Now to use the information supplied to us from [0] to determine is this a user or role and then also let other modules do their own thing here.
        $assignmentsByVar = explode(',', $var);
        foreach ($assignmentsByVar as $assignTo) {
          // What if the variable is blank?  This is an assignment to a variable that may have gone wrong and this could orphan a task.
          // Let's create the task, assign it to a very specific non-user so that it is visible in the assignments and views.
          if ($assignTo == '') {
            $assignTo = 'unable-to-assign'; // We force it to a non-user. If a user is actually named "unable-to-assign", well...
          }
          $values = [
            'queue_id' => $queueID,
            'assign_type' => $thisAssignment[0],
            'by_variable' => 1,
            'assign_id' => $assignTo,
            'process_variable' => $varID,
            'assign_back_id' => 0,
            'task_completed' => 0,
          ];
          $prodAssignments = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->create($values);
          $prodAssignments->save();
          
        }

      }
    }

    // And now we do a module invoke for any add-on modules to do their own assignments or tweak the assignments.
    \Drupal::moduleHandler()->invokeAll('maestro_post_production_assignments',
        [$templateMachineName, $taskID, $queueID]);

  }

  /**
   * Creates a task in the Maestro Queue table.
   *
   * @param string $taskMachineName
   *   The machine name of the task.
   * @param string $templateMachineName
   *   The machine name of the template.
   * @param int $processID
   *   The Maestro Process ID.
   * @param int $lastTaskQueueID
   *   The last task's Maestro Queue ID.
   * @param int $regenCount
   *   Default 0. Sets the new task's regen count to this value.
   *
   * @return int|bool
   *   Returns FALSE on no queue entry.  Returns the QueueID upon success.
   */
  protected function createProductionTask($taskMachineName, $templateMachineName, $processID, $lastTaskQueueID, $regenCount = 0) {
    $config = \Drupal::config('maestro.settings');
    $queueID = FALSE;
    $nextTask = $this->getTemplateTaskByID($templateMachineName, $taskMachineName);
    $executableTask = MaestroEngine::getPluginTask($nextTask['tasktype']);

    $currentTime = time();
    $nextReminderTime = 0;
    $reminderInterval = 0;
    $escalationInterval = 0;
    if (is_array($nextTask) && array_key_exists('notifications', $nextTask)) {
      $reminderInterval = $nextTask['notifications']['reminder_after'];
      if (intval($reminderInterval) > 0) {
        $nextReminderTime = $currentTime + (intval($reminderInterval) * 86400);
      }
      $escalationInterval = $nextTask['notifications']['escalation_after'];
    }

    $pointers_to_task = MaestroEngine::getTaskPointersFromTemplate($templateMachineName, $taskMachineName);
    $regen_pointers = count($pointers_to_task) ?? 0;

    $values = [
      'process_id' => $processID,
      'task_class_name' => $nextTask['tasktype'],
      'task_id' => $nextTask['id'],
      'task_label' => $nextTask['label'],
      'engine_version' => 2,
      'is_interactive' => $executableTask->isInteractive() ? 1 : 0,
      'show_in_detail' => isset($nextTask['showindetail']) ? $nextTask['showindetail'] : 0,
      'handler' => isset($nextTask['handler']) ? $nextTask['handler'] : '',
      'task_data' => isset($nextTask['data']) ? $nextTask['data'] : '',
      'status' => 0,
      'run_once' => $executableTask->isInteractive() ? 1 : 0,
      'regen_count' => $regenCount,
      'regen_pointers' => $regen_pointers,
    // This should probably be 0 to signify the engine.
      'uid' => 0,
      'archived' => 0,
      'started_date' => $currentTime,
      'num_reminders_sent' => 0,
      'num_escalations_sent' => 0,
      'next_reminder_time' => $nextReminderTime,
      'reminder_interval' => $reminderInterval,
      'escalation_interval' => $escalationInterval,
      'token' => Crypt::randomBytesBase64(),
    ];
    $queue = \Drupal::entityTypeManager()->getStorage('maestro_queue')->create($values);
    $queue->save();
    // Now to do assignments if the queue ID has been set.
    if ($queue->id()) {
      // Save the new task's ancestry information based on the
      // previous tasks's ancestry information.
      $ancestry = MaestroEngine::getAncestryInformation($lastTaskQueueID);
      $last_task = MaestroEngine::getTemplateTaskByQueueID($lastTaskQueueID);
      // Now add our new task to the ancestry information for THIS task
      $ancestry[$lastTaskQueueID] = $last_task['id'];
      MaestroEngine::setAncestryInformation($queue->id(), $ancestry);

      // Perform maestro assignments.
      $this->productionAssignments($templateMachineName, $taskMachineName, $queue->id());
      $queueID = $queue->id();
      if ($config->get('maestro_send_notifications')) {
        $this->doProductionAssignmentNotifications($templateMachineName, $taskMachineName, $queue->id(), 'assignment');
      }
      // Now lets set the workflow status process variables if the task requires us to do so.
      // Relates to the checkbox on the task editor.
      if (isset($nextTask['participate_in_workflow_status_stage']) && $nextTask['participate_in_workflow_status_stage'] == 1) {
        if (isset($nextTask['workflow_status_stage_number'])) {
          $this->setProcessVariable('workflow_current_stage', $nextTask['workflow_status_stage_number'], $processID);
        }
        if (isset($nextTask['workflow_status_stage_message'])) {
          $this->setProcessVariable('workflow_current_stage_message', $nextTask['workflow_status_stage_message'], $processID);
        }
      }
    }
    else {
      // TODO: throw maestro exception here.
    }
    return $queueID;
  }

  /**
   * Internal method to do production assignment notifications.
   */
  protected function doProductionAssignmentNotifications($templateMachineName, $taskMachineName, $queueID, $notificationType = 'assignment') {
    $config = \Drupal::config('maestro.settings');
    $queueToken = MaestroEngine::getTokenFromQueueId($queueID);
    $templateTask = $this->getTemplateTaskByID($templateMachineName, $taskMachineName);
    $notificationList = [];
    if (!empty($templateTask['notifications']['notification_assignments'])) {
      $notifications = explode(',', $templateTask['notifications']['notification_assignments']);
      foreach ($notifications as $notification) {
        // We will assume that WE are the ones doing the notification.  Otherwise we're offloading to a different module to do so.
        $doNotification = TRUE;
        // [0] is to what type of entity, [1] is fixed or variable, [2] is entity or variable, [3] is type
        $thisNotification = explode(':', $notification);
        // Works for any assignment type passed in.
        if ($thisNotification[3] == $notificationType) {
          $entity = '';
          if ($thisNotification[1] == 'fixed') {
            $entity = $thisNotification[2];
          }
          elseif ($thisNotification[1] == 'variable') {
            // Variable is in [2].  Need to get its value.
            $processID = MaestroEngine::getProcessIdFromQueueId($queueID);
            $variableValue = MaestroEngine::getProcessVariable($thisNotification[2], $processID);
            // Assumption here is that these values are actual names of users or roles or whatever other entity.
            $entity = $variableValue;
          }
          // Oh oh.  not fixed or by variable.  Don't do this notification.
          else {
            $doNotification = FALSE;
          }

          if ($thisNotification[0] == 'user' && $doNotification) {
            // Load the user(s) by name and get their email addr.
            $users = explode(',', $entity);
            foreach ($users as $accountName) {
              if ($accountName != '') {
                $account = user_load_by_name($accountName);
                if ($account) {
                  $notificationList[$account->get('mail')->getString()] = $account->get('mail')->getString();
                }
                // There is no account by that name.  Log this as an exception.
                else {
                  throw new MaestroGeneralException('Unknown account name identified when attempting a notification.');
                }
              }
            }
          }
          elseif ($thisNotification[0] == 'role' && $doNotification) {
            // Have to parse out WHO is in the role and then get their email addr.
            $roles = explode(',', $entity);
            foreach ($roles as $roleName) {
              if ($roleName != '') {
                $ids = \Drupal::entityQuery('user')
                  ->accessCheck(FALSE)
                  ->condition('status', 1)
                  ->condition('roles', $roleName)
                  ->execute();
                $users = User::loadMultiple($ids);
                foreach ($users as $account) {
                  $notificationList[$account->get('mail')->getString()] = $account->get('mail')->getString();
                }
              }
            }
          }
          else {
            // This is not a Maestro-managed assignment type of role or user whether that be by variable or fixed.
            // Manage this yourself via this hook.
            \Drupal::moduleHandler()->invokeAll('maestro_production_' . $notificationType . '_notification',
                [$queueID, $thisNotification, &$notificationList]);
          }
        }
      } //end foreach over each assignment
      // ok, now we can check to see if we have any assignments.
      if (count($notificationList) > 0) {
        $notificationMessage = '';
        if (array_key_exists('notification_' . $notificationType, $templateTask['notifications']) && $templateTask['notifications']['notification_' . $notificationType] != '') {
          $notificationMessage = $templateTask['notifications']['notification_' . $notificationType];
          // Now do a token replacement
          $tokenService = \Drupal::token();
          $notificationMessage = $tokenService->replace($notificationMessage, ['maestro' => ['task' => $templateTask, 'queueID' => $queueID]]);
          if ($notificationType == 'assignment') {
            $subject = array_key_exists('notification_assignment_subject', $templateTask['notifications']) ? $tokenService->replace($templateTask['notifications']['notification_assignment_subject'], ['maestro' => ['task' => $templateTask, 'queueID' => $queueID, 'queueToken' => $queueToken]]) : 'You have a new task assignment';
          }
          elseif ($notificationType == 'reminder') {
            $subject = array_key_exists('notification_reminder_subject', $templateTask['notifications']) ? $tokenService->replace($templateTask['notifications']['notification_reminder_subject'], ['maestro' => ['task' => $templateTask, 'queueID' => $queueID, 'queueToken' => $queueToken]]) : 'You have a new task assignment';
          }
          elseif ($notificationType == 'escalation') {
            $subject = array_key_exists('notification_escalation_subject', $templateTask['notifications']) ? $tokenService->replace($templateTask['notifications']['notification_escalation_subject'], ['maestro' => ['task' => $templateTask, 'queueID' => $queueID, 'queueToken' => $queueToken]]) : 'You have a new task assignment';
          }
        }
        // Default built in message.
        else {
          // Strip off a trailing slash as we're going to add it ourselves.
          $redirectionLocation = rtrim($config->get('maestro_redirect_location'), '/');
          if ($redirectionLocation == '') {
            $redirectionLocation = '/taskconsole';
          }
          $queueRecord = MaestroEngine::getQueueEntryById($queueID);
          if ($notificationType == 'assignment') {
            $notificationMessage = t('A new task titled:') . ' ' . $queueRecord->task_label->getString() . ' ' . t('has been assigned to you.');
            $notificationMessage .= t('Click here to go to see your tasks:') . ' ' . '<a href="' . Url::fromUserInput($redirectionLocation . '/' . $queueToken, ['absolute' => TRUE])->toString() . '">Task Console</a>';
          }
          elseif ($notificationType == 'reminder') {
            $notificationMessage = t('A reminder you have open tasks.  Please review the task:') . ' ' . $queueRecord->task_label->getString();
            $notificationMessage .= t('Click here to go to your tasks:') . ' <a href="' . Url::fromUserInput($redirectionLocation . '/' . $queueToken, ['absolute' => TRUE])->toString() . '">Task Console</a>';
          }
          elseif ($notificationType == 'escalation') {
            $notificationMessage = t('An escalation for an overdue task has been generated for the task:') . ' ' . $queueRecord->task_label->getString();
            // The escalation will have to have more information associated with it.. who is assigned, when it was assigned etc etc.
            // TODO:  need the list of assigned users/roles/whatever.
            $notificationMessage .= t('Assigned To:') . ' ';
            $notificationMessage .= t('Queue ID:') . ' ' . $queueID;
            $notificationMessage .= t('Queue Token:') . ' ' . $queueToken;
            $notificationMessage .= t('Process IDr:') . ' ' . $queueRecord->process_id->getString();
          }
        }
        $mailManager = \Drupal::service('plugin.manager.mail');
        $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $params = [];
        $params['queueID'] = $queueID;
        $tokenService = \Drupal::token();
        $params['subject'] = isset($subject) ? $subject : t('You have a new task assignment');
        $params['message'] = $notificationMessage;
        foreach ($notificationList as $email) {
          $result = $mailManager->mail('maestro', $notificationType . '_notification', $email, $langcode, $params, NULL, TRUE);
        }
      }
      else {
        /*
         * This is the scenario where we have no assignees/actors listed.
         * NOTE: this will NEVER fire if you have an explicit actor or role with known Drupal users within it, assigned to this task.
         *
         * There is a use case where a Maestro task is assigned to a known entity, however, they are not a part of the Drupal users.
         * For example: a webform could be presented to a person who does not have a Drupal account and still requires
         * to provide information to complete a workflow.  An outgoing message can be sent to known non-Drupal actors of a specific task or task type
         * using the Maestro site-wide Token in the URL.  It is up to the developers to write a completion routine and check the
         * validity of the non-Drupal actor.
         *
         * We will check to see if Maestro is configured to use the outgoing Maestro Zero-User option, thereby allowing
         * Developers to create a custom message.
         */

         if($config->get('maestro_token_zero_user') == 1) { //This can only be set if the token key has been set.
           //Developers can fetch the Queue Token via the Maestro API in their hook.
           \Drupal::moduleHandler()->invokeAll('maestro_zero_user_notification', [$templateMachineName, $taskMachineName, $queueID, $notificationType]);
         }


      }
      // End of actual mail sending routine.
    }
  }

}
