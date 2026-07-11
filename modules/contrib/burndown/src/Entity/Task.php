<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\link\LinkItemInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the Task entity.
 *
 * @ingroup burndown
 *
 * @ContentEntityType(
 *   id = "burndown_task",
 *   label = @Translation("Task"),
 *   bundle_label = @Translation("Task type"),
 *   handlers = {
 *     "storage" = "Drupal\burndown\TaskStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\burndown\TaskListBuilder",
 *     "views_data" = "Drupal\burndown\Entity\TaskViewsData",
 *     "translation" = "Drupal\burndown\TaskTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\burndown\Form\TaskForm",
 *       "add" = "Drupal\burndown\Form\TaskForm",
 *       "edit" = "Drupal\burndown\Form\TaskForm",
 *       "delete" = "Drupal\burndown\Form\TaskDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\burndown\TaskHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\burndown\TaskAccessControlHandler",
 *   },
 *   base_table = "burndown_task",
 *   data_table = "burndown_task_field_data",
 *   revision_table = "burndown_task_revision",
 *   revision_data_table = "burndown_task_field_revision",
 *   translatable = TRUE,
 *   permission_granularity = "bundle",
 *   admin_permission = "administer task entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "ticket_id",
 *     "ticket_id" = "ticket_id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/burndown/task/{burndown_task}",
 *     "add-page" = "/burndown/task/add",
 *     "add-form" = "/burndown/task/add/{burndown_task_type}",
 *     "edit-form" = "/burndown/task/{burndown_task}/edit",
 *     "delete-form" = "/burndown/task/{burndown_task}/delete",
 *     "version-history" = "/burndown/task/{burndown_task}/revisions",
 *     "revision" = "/burndown/task/{burndown_task}/revisions/{burndown_task_revision}/view",
 *     "revision_revert" = "/burndown/task/{burndown_task}/revisions/{burndown_task_revision}/revert",
 *     "revision_delete" = "/burndown/task/{burndown_task}/revisions/{burndown_task_revision}/delete",
 *     "translation_revert" = "/burndown/task/{burndown_task}/revisions/{burndown_task_revision}/revert/{langcode}",
 *     "collection" = "/burndown/task",
 *   },
 *   bundle_entity_type = "burndown_task_type",
 *   field_ui_base_route = "entity.burndown_task_type.edit_form"
 * )
 */
class Task extends EditorialContentEntityBase implements TaskInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;
  use StringTranslationTrait;

  /**
   * Load a task using its ticket ID.
   */
  public static function loadFromTicketId($ticket_id) {
    $task_ids = \Drupal::entityQuery('burndown_task')
      ->condition('ticket_id', $ticket_id)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($task_ids)) {
      $task_id = array_pop($task_ids);
      return Task::load($task_id);
    }

    return FALSE;
  }

  /**
   * Get tasks for a column (optionally filtered on a sprint).
   */
  public static function getTasksForSwimlane($shortcode, $swimlane_name, $sprint_id = NULL, $assigned_to = NULL) {
    $project = Project::loadFromShortcode($shortcode);

    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane = Swimlane::getSwimlane($shortcode, $swimlane_name);
      if ($swimlane !== FALSE) {
        $swimlane_id = $swimlane->id();

        $query = \Drupal::entityQuery('burndown_task');
        $query
          ->condition('project', $project_id)
          ->condition('swimlane', $swimlane_id);

        if (!is_null($sprint_id)) {
          $query->condition('sprint', $sprint_id);
        }

        if (!empty($assigned_to)) {
          $query->condition('assigned_to', $assigned_to, 'IN');
        }

        $query->sort('board_sort', 'ASC');

        $task_ids = $query
          ->accessCheck(FALSE)
          ->execute();

        if (!empty($task_ids)) {
          return Task::loadMultiple($task_ids);
        }
      }
    }

    return FALSE;
  }

  /**
   * Get tasks closed before being assigned to a sprint.
   *
   * Get tasks for a sprint-based project that were closed prior to being
   * assigned to a sprint.
   *
   * @param string $shortcode
   *   The shortcode of the project to search.
   * @param string $swimlane_name
   *   The name of the swimlane to search.
   *
   * @return false|array
   *   False if no results or an array of Tasks otherwise.
   */
  public static function getClosedPreSprintTasks($shortcode, $swimlane_name) {
    $project = Project::loadFromShortcode($shortcode);

    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane = Swimlane::getSwimlane($shortcode, $swimlane_name);
      if ($swimlane !== FALSE) {
        $swimlane_id = $swimlane->id();

        $task_ids = \Drupal::entityQuery('burndown_task')
          ->condition('project', $project_id)
          ->condition('swimlane', $swimlane_id)
          ->condition('sprint', NULL, 'IS NULL')
          ->sort('board_sort', 'ASC')
          ->accessCheck(FALSE)
          ->execute();

        if (!empty($task_ids)) {
          return Task::loadMultiple($task_ids);
        }
      }
    }

    return FALSE;
  }

  /**
   * Get backlog tasks.
   */
  public static function getBacklogTasks($shortcode, $filter_if_in_sprint = FALSE) {
    $project = Project::loadFromShortcode($shortcode);

    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane = Swimlane::getBacklogFor($shortcode);
      if ($swimlane !== FALSE) {
        $swimlane_id = $swimlane->id();

        // For kanban backlogs, we don't care if tasks are allocated to
        // a sprint or not.
        if (!$filter_if_in_sprint) {
          $task_ids = \Drupal::entityQuery('burndown_task')
            ->condition('project', $project_id)
            ->condition('swimlane', $swimlane_id)
            ->sort('backlog_sort', 'ASC')
            ->accessCheck(FALSE)
            ->execute();
        }
        // For sprint backlogs, we want to be able to filter out tasks
        // that have been assigned to a sprint (whether or not it is
        // opened).
        else {
          $task_ids = \Drupal::entityQuery('burndown_task')
            ->condition('project', $project_id)
            ->condition('swimlane', $swimlane_id)
            ->notExists('sprint')
            ->sort('backlog_sort', 'ASC')
            ->accessCheck(FALSE)
            ->execute();
        }

        if (!empty($task_ids)) {
          return Task::loadMultiple($task_ids);
        }
      }
    }

    return FALSE;
  }

  /**
   * Get tasks for a backlog (or completed) sprint.
   */
  public static function getTasksForBacklogSprint($shortcode, $sprint_id) {
    $project = Project::loadFromShortcode($shortcode);

    if ($project !== FALSE) {
      $project_id = $project->id();

      $task_ids = \Drupal::entityQuery('burndown_task')
        ->condition('project', $project_id)
        ->condition('sprint', $sprint_id)
        ->sort('backlog_sort', 'ASC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($task_ids)) {
        return Task::loadMultiple($task_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get count of open tasks for a project.
   */
  public static function getOpenTasksFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);

    if ($project !== FALSE) {
      $project_id = $project->id();

      $query = \Drupal::entityQuery('burndown_task')
        ->condition('project', $project_id);

      $group = $query->orConditionGroup()
        ->notExists('completed')
        ->condition('completed', 0);

      $task_ids = $query
        ->condition($group)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($task_ids)) {
        return count($task_ids);
      }
    }

    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];

    // Check if we're passing a project shortcode in.
    $shortcode = \Drupal::request()->query->get('shortcode');
    if (!empty($shortcode)) {
      $project = Project::loadFromShortcode($shortcode);
      if ($project !== FALSE) {
        $values += [
          'project' => $project->id(),
        ];
      }

      // Check if we have a backlog swimlane set up for the project.
      $backlog = Swimlane::getBacklogFor($shortcode);
      if ($backlog !== FALSE) {
        $values += [
          'swimlane' => $backlog->id(),
        ];
      }
    }

    // Log the entity creation.
    $values += [
      'log' => [
        'type' => 'created',
        'created' => time(),
        'uid' => \Drupal::currentUser()->id(),
        'description' => 'Task added',
      ],
    ];
  }

  /**
   * Check if this task is in the backlog.
   */
  public function inBacklog() {
    $swimlane = $this->getSwimlane();
    $project = $this->getProject();
    $shortcode = $project->getShortcode();
    $backlog = Swimlane::getBacklogFor($shortcode);
    return ($backlog->id() == $swimlane->id());
  }

  /**
   * Check if this task is on the board.
   */
  public function onBoard() {
    $swimlane = $this->getSwimlane();
    $project = $this->getProject();
    $shortcode = $project->getShortcode();
    $board_lanes = Swimlane::getBoardSwimlanes($shortcode);
    if ($board_lanes !== FALSE) {
      foreach ($board_lanes as $lane) {
        if ($lane->id() == $swimlane->id()) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Check if this task is on the completed board.
   */
  public function onCompletedBoard() {
    $swimlane = $this->getSwimlane();
    $project = $this->getProject();
    $shortcode = $project->getShortcode();
    $completed_lanes = Swimlane::getCompletedSwimlanes($shortcode);
    if ($completed_lanes !== FALSE) {
      foreach ($completed_lanes as $lane) {
        if ($lane->id() == $swimlane->id()) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Check if task is completed.
   */
  public function isCompleted() {
    // If the task is flagged as done, then just return TRUE.
    $completed = $this->getCompleted();
    if ($completed == TRUE) {
      return TRUE;
    }

    // Check if in the final "done" lane of the board.
    $project = $this->getProject();
    $shortcode = $project->getShortcode();
    $swimlane = $this->getSwimlane();
    $done = Swimlane::getDoneSwimlane($shortcode);
	if ($done !== FALSE) {
      if ($swimlane->id() == $done->id()) {
        return TRUE;
      }
	}

    // The task is not yet completed.
    return FALSE;
  }

  /**
   * Check if task is in progress.
   */
  public function inProgress() {
    return (!$this->inBacklog() && !$this->isCompleted());
  }

  /**
   * Check if this task's project is an sprint mode.
   */
  public function isSprint() {
    return $this->getProject()->isSprint();
  }

  /**
   * Get data array, for usage in the board controllers.
   */
  public function getData() {
    $project = $this->getProject();

    return [
      'id' => $this->id(),
      'ticket_id' => $this->getTicketId(),
      'name' => $this->getName(),
      'description' => $this->getDescription(),
      'is_completed' => $this->isCompleted(),
      'shortcode' => $project->getShortcode(),
      'project_id' => $project->id(),
      'backlog' => $this->inBacklog(),
      'board' => $this->onBoard(),
      'sprint' => $this->isSprint(),
      'priority' => $this->getPriority(),
      'estimate_type' => $this->getEstimateType(),
      'estimate' => $this->getEstimate(),
      'assigned_to' => $this->getAssignedToName(),
      'assigned_to_image' => $this->getAssignedToImage(),
      'assigned_to_first_letter' => $this->getAssignedToFirstLetter(),
      'reporter' => $this->getOwnerName(),
      'reporter_image' => $this->getOwnerImage(),
      'reporter_first_letter' => $this->getOwnerFirstLetter(),
      'tags' => $this->getTagsFormatted(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // On ticket creation (but not edit), we need to obtain a unique
    // id from the project.
    if ($this->isNew()) {
      // Get the project.
      $project = $this->getProject();
      if (is_null($project)) {
        throw new \Exception('Task does not have a Project');
      }
      $project_id = $project->id();
      $shortcode = $project->getShortCode();

      // Get the next task id from the project.
      $next_id_service = \Drupal::service('burndown_service.next_id');
      $next_id = $next_id_service->getNextIdFor($project_id);
      if ($next_id === FALSE) {
        throw new \Exception('Could not get Task ID from Project ' . $project_id);
      }
      $ticket_id = $shortcode . '-' . $next_id;
    }

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }

      // Set the task id field value.
      if ($this->isNew()) {
        $translation->setTicketId($ticket_id);
      }
    }

    // If no revision author has been set explicitly,
    // make the burndown_task owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * Get the type of estimation.
   */
  public function getEstimateType() {
    return $this
      ->getProject()
      ->getEstimateType();
  }

  /**
   * Get number of task bundles.
   *
   * Used to determine the structure of the add task link.
   */
  public static function numberOfTaskTypes() {
    $bundles = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo('burndown_task');
    return count($bundles);
  }

  /**
   * Check if valid task type.
   *
   * If not, then return the first valid type.
   */
  public static function getValidTaskType(string $taskType) {
    $bundles = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo('burndown_task');

    $taskTypes = array_keys($bundles);

    if (in_array($taskType, $taskTypes)) {
      return $taskType;
    }

    return array_shift($taskTypes);
  }

  /**
   * Get back-reference task relationships.
   *
   * That is, what tasks have relationships with this task?
   */
  public function getRelationshipReferences() {
    $relationships = [];

    $database = \Drupal::database();

    $query = $database
      ->select('burndown_task__relationships', 'r');

    $query
      ->condition('r.deleted', 0)
      ->condition('r.bundle', 'task')
      ->condition('r.relationships_task_id', $this->id())
      ->fields('r', ['entity_id', 'relationships_type']);

    $result = $query
      ->execute();

    foreach ($result as $record) {
      $task_id = $record->entity_id;
      $task = Task::load($task_id);
      $ticket_id = $task->getTicketId();
      $type = $record->relationships_type;
      $type = Task::getRelationshipOpposite($type);
      $relationships[] = [
        'task_id' => $task_id,
        'is_completed' => $task->isCompleted(),
        'ticket_id' => $ticket_id,
        'type' => $type,
      ];
    }

    return $relationships;
  }

  /**
   * Search specified project for tasks matching search for name or ticket_id.
   *
   * @param string $search
   *   The search term to be used.
   * @param string $shortcode
   *   The shortcode of the project being searched.
   *
   * @return false|array
   *   False if no tasks match or an array of tasks otherwise.
   */
  public static function search($search, $shortcode) {
    $db = \Drupal::database();

    $project = Project::loadFromShortcode($shortcode);

    if ($project !== FALSE) {
      $project_id = $project->id();

      $query = \Drupal::entityQuery('burndown_task')
        ->condition('project', $project_id);

      $group = $query->orConditionGroup()
        ->condition('name', "%" . $db->escapeLike($search) . "%", 'LIKE')
        ->condition('ticket_id', "%" . $db->escapeLike($search) . "%", 'LIKE');

      $task_ids = $query
        ->condition($group)
        ->sort('board_sort', 'ASC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($task_ids)) {
        return Task::loadMultiple($task_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get the opposite of a relationship type.
   */
  public static function getRelationshipOpposite($type) {
    $options = [];

    // Get config object.
    $config = \Drupal::config('burndown.config_settings');
    $list = $config->get('relationship_opposites');

    if (!empty($list)) {
      // List is a text string with one item per line.
      $list = preg_split("/\r\n|\n|\r/", $list);

      foreach ($list as $row) {
        // Rows are in the form "id|opposite_id".
        $val = explode('|', $row);
        $options[$val[0]] = strval($val[1]);
      }
    }

    // If we have an opposite of $type, return it.
    if (isset($options[$type])) {
      return $options[$type];
    }

    // Otherwise return out original type.
    return $type;
  }

  /**
   * Validate that a relationship type exists.
   */
  public static function relationshipTypeExists($type) {
    $types = Task::getRelationshipTypes();
    return array_key_exists($type, $types);
  }

  /**
   * Get list of relationship types.
   */
  public static function getRelationshipTypes() {
    $options = [];

    // Get config object.
    $config = \Drupal::config('burndown.config_settings');
    $list = $config->get('relationship_types');

    if (!empty($list)) {
      // List is a text string with one item per line.
      $list = preg_split("/\r\n|\n|\r/", $list);

      foreach ($list as $row) {
        // Rows are in the form "id|opposite_id".
        $val = explode('|', $row);
        $options[$val[0]] = $val[1];
      }
    }

    return $options;
  }

  /**
   * Get a list of resolution statuses.
   */
  public static function getResolutionStatuses() {
    $options = [];

    // Get config object.
    $config = \Drupal::config('burndown.config_settings');
    $list = $config->get('resolution_statuses');

    if (!empty($list)) {
      // List is a text string with one item per line.
      $list = preg_split("/\r\n|\n|\r/", $list);

      foreach ($list as $row) {
        // Rows are in the form "id|label".
        $val = explode('|', $row);
        $options[$val[0]] = strval($val[1]);
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->set('description', $description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProject() {
    return $this->get('project')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setProject(Project $project) {
    $this->set('project', $project->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSwimlane() {
    return $this->get('swimlane')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setSwimlane(Swimlane $swimlane) {
    $this->set('swimlane', $swimlane->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSprint() {
    return $this->get('sprint')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setSprint(Sprint $sprint) {
    $this->set('sprint', $sprint->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTicketId() {
    return $this->get('ticket_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTicketId($ticket_id) {
    $this->set('ticket_id', $ticket_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBacklogSort() {
    return $this->get('backlog_sort')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setBacklogSort($backlog_sort) {
    $this->set('backlog_sort', $backlog_sort);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoardSort() {
    return $this->get('board_sort')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setBoardSort($board_sort) {
    $this->set('board_sort', $board_sort);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEstimate() {
    if ($this->getEstimateType() == 'geometric') {
      $estimate = $this->get('estimate')->value ?? '';
      $estimate = str_replace('D', '', $estimate);
      return $estimate;
    }
    return $this->get('estimate')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setEstimate($estimate) {
    $this->set('estimate', $estimate);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority() {
    return $this->get('priority')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPriority($priority) {
    $this->set('priority', $priority);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignedTo() {
    return $this->get('assigned_to')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignedToId() {
    return $this->get('assigned_to')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssignedToId($uid) {
    $this->set('assigned_to', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssignedTo(UserInterface $account) {
    $this->set('assigned_to', $account->id());
    return $this;
  }

  /**
   * Get assigned to profile image.
   */
  public function getAssignedToImage() {
    if ($this->getAssignedTo() === NULL) {
      return FALSE;
    }

    $user_id = $this->getAssignedToId();
    $user = User::load($user_id);
    if ($user->hasField('user_picture') &&
      !$user->user_picture->isEmpty()) {
      return \Drupal::service('file_url_generator')
        ->generateAbsoluteString($user->user_picture->entity->getFileUri());
    }

    return FALSE;
  }

  /**
   * Get display name of assigned to.
   */
  public function getAssignedToName() {
    if ($this->getAssignedTo() === NULL) {
      return FALSE;
    }

    return $this->getAssignedTo()->getDisplayName();
  }

  /**
   * Get first letter of assigned to's user name.
   */
  public function getAssignedToFirstLetter() {
    $name = $this->getAssignedToName();
    if ($name === FALSE) {
      return FALSE;
    }
    $first = substr($name, 0, 1);
    return strtoupper($first);
  }

  /**
   * Get array of assigned-to user info.
   */
  public function getAssignedToData() {
    return [
      'id' => $this->getAssignedToId(),
      'assigned_to' => $this->getAssignedToName(),
      'assigned_to_image' => $this->getAssignedToImage(),
      'assigned_to_first_letter' => $this->getAssignedToFirstLetter(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * Get assigned to profile image.
   */
  public function getOwnerImage() {
    if ($this->getOwner() === NULL) {
      return FALSE;
    }

    $user_id = $this->getOwnerId();
    $user = User::load($user_id);
    if ($user->hasField('user_picture') &&
      !$user->user_picture->isEmpty()) {
      return \Drupal::service('file_url_generator')
        ->generateAbsoluteString($user->user_picture->entity->getFileUri());
    }

    return FALSE;
  }

  /**
   * Get display name of owner.
   */
  public function getOwnerName() {
    if ($this->getOwner() === NULL) {
      return FALSE;
    }

    return $this->getOwner()->getDisplayName();
  }

  /**
   * Get first letter of owner's user name.
   */
  public function getOwnerFirstLetter() {
    $name = $this->getOwnerName();
    if ($name === FALSE) {
      return FALSE;
    }
    $first = substr($name, 0, 1);
    return strtoupper($first);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompleted() {
    return $this->get('completed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCompleted($completed) {
    $this->set('completed', $completed);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolution() {
    return $this->get('resolution')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setResolution($resolution) {
    $this->set('resolution', $resolution);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    return $this->get('tags')->referencedEntities();
  }

  /**
   * Get tags with their first letters, so we can format nicely.
   */
  public function getTagsFormatted() {
    $tagList = [];

    $tags = $this->getTags();

    if (!empty($tags)) {
      foreach ($tags as $tag) {
        $name = $tag->getName();

        $first = substr($name, 0, 1);
        $first = strtoupper($first);

        $tagList[] = [
          'name' => $name,
          'first_letter' => $first,
        ];
      }
    }

    return $tagList;
  }

  /**
   * {@inheritdoc}
   */
  public function getLog() {
    return $this->get('log')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function addLog($type, $comment, $work_done, $created, $uid, $description) {
    // Note that using t() on $description or $comment causes an error
    // in SqlContentEntityStorage in Drupal > 9.1. These cannot be
    // translated as a result.
    $new_log = [
      'type' => $type,
      'work_done' => $work_done,
      'created' => $created,
      'uid' => $uid,
      'description' => $description,
    ];

    if (!empty($comment)) {
      $new_log['comment'] = $comment;
    }

    $this
      ->get('log')
      ->appendItem($new_log);

    return $this;
  }

  /**
   * Add a user the watchlist.
   */
  public function addToWatchlist($user) {
    // Check if user is on list already.
    $found_index = $this->checkIfOnWatchlist($user);

    if ($found_index === FALSE) {
      // Add user to the list.
      $this
        ->get('watch_list')
        ->appendItem($user->id());
    }

    return $this;
  }

  /**
   * Remove a user from the watchlist.
   */
  public function removeFromWatchlist($user) {
    // Find user in list.
    $found_index = $this->checkIfOnWatchlist($user);

    // If the user is in the list, remove.
    if ($found_index !== FALSE) {
      // ItemList::removeItem doesn't appear to work properly.
      $connection = \Drupal::service('database');

      // Delete the specific record.
      $connection->delete('burndown_task__watch_list')
        ->condition('entity_id', $this->id())
        ->condition('watch_list_target_id', $user->id())
        ->execute();
      $connection->delete('burndown_task_revision__watch_list')
        ->condition('entity_id', $this->id())
        ->condition('watch_list_target_id', $user->id())
        ->execute();

      // Invalidate the entity cache.
      \Drupal::entityTypeManager()
        ->getStorage('burndown_task')
        ->resetCache([$this->id()]);
    }

    return $this;
  }

  /**
   * Check if user is on watchlist.
   *
   * Returns the index if found, otherwise FALSE;
   */
  public function checkIfOnWatchlist($user) {
    $uid = $user->id();

    $watch_list = $this->get('watch_list')->getValue();
    if (!empty($watch_list)) {
      foreach ($watch_list as $key => $watcher) {
        if ($watcher['target_id'] == $uid) {
          return $key;
        }
      }
    }

    return FALSE;
  }

  /**
   * Get the watchlist.
   */
  public function getWatchlist() {
    return $this->get('watch_list')->referencedEntities();
  }

  /**
   * Find the index (if exists) of a relationship .
   *
   * Note that this doesn't check back-references. Also note that we
   * do not check the relationship type (i.e. only one task-task
   * relationship allowed, regardless of type).
   */
  public function checkIfRelationshipExists($to_task_id) {
    $relationships = $this->get('relationships')->getValue();
    if (!empty($relationships)) {
      foreach ($relationships as $key => $relationship) {
        if ($relationship['task_id'] == $to_task_id) {
          return $key;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationships() {
    return $this->get('relationships')->getValue();
  }

  /**
   * Add a relationship to another ticket.
   *
   * Validation occurs in TaskController.
   */
  public function addRelationship($task_id, $type) {
    $found_index = $this->checkIfRelationshipExists($task_id);

    if ($found_index === FALSE) {
      $this
        ->get('relationships')
        ->appendItem([
          'task_id' => $task_id,
          'type' => $type,
        ]);
    }

    return $this;
  }

  /**
   * Remove a relationship to another ticket.
   *
   * Only removes on this end (i.e. this does not check back references).
   */
  public function removeRelationship($task_id) {
    // Find relationship in list.
    $found_index = $this->checkIfRelationshipExists($task_id);

    // If the user is in the list, remove.
    if ($found_index !== FALSE) {
      // ItemList::removeItem doesn't appear to work properly.
      $connection = \Drupal::service('database');

      // Delete the specific record.
      $connection->delete('burndown_task__relationships')
        ->condition('entity_id', $this->id())
        ->condition('bundle', 'task')
        ->condition('relationships_task_id', $task_id)
        ->execute();
      $connection->delete('burndown_task_revision__relationships')
        ->condition('entity_id', $this->id())
        ->condition('bundle', 'task')
        ->condition('relationships_task_id', $task_id)
        ->execute();

      // Invalidate the entity cache.
      \Drupal::entityTypeManager()
        ->getStorage('burndown_task')
        ->resetCache([$this->id()]);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    // Hide revision log message.
    if (isset($fields['revision_log_message'])) {
      $fields['revision_log_message']
        ->setDisplayOptions('form', [
          'region' => 'hidden',
        ]);
    }

    // Created by user.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Reported by'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 11,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Label.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // Ticket ID (display id within the context of the project).
    $fields['ticket_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Ticket ID'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -15,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => -15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setReadOnly(TRUE);

    // Priority.
    $fields['priority'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Priority'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'allowed_values' => [
          '0' => t('Trivial'),
          '1' => t('Low'),
          '2' => t('Medium'),
          '3' => t('High'),
          '4' => t('Critical'),
          '5' => t('Blocker'),
        ],
      ])
      ->setDefaultValue('2')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'list_default',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    // Project.
    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project'))
      ->setDescription(t('The project that this task is for.'))
      ->setSetting('target_type', 'burndown_project')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // Swimlane.
    $fields['swimlane'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Column'))
      ->setDescription(t('The Column that the task is currently in.'))
      ->setSetting('target_type', 'burndown_swimlane')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Sprint (optional).
    $fields['sprint'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sprint'))
      ->setDescription(t('The sprint that the task is currently in.'))
      ->setSetting('target_type', 'burndown_sprint')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Description.
    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textfield',
        'weight' => 5,
      ])
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Assigned To.
    $fields['assigned_to'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Assigned To'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 10,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Sort order in backlog.
    $fields['backlog_sort'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Backlog Sort Order'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Sort order on boards within column).
    $fields['board_sort'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Board Sort Order'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Size estimate.
    $fields['estimate'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Estimate'))
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setSettings([
        'allowed_values' => [],
        'allowed_values_function' => 'burndown_allowed_estimate_values',
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 12,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textfield',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Allow users to tag tasks.
    $fields['tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tags'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
          [
            'target_bundles' => [
              'tags' => 'tags',
            ],
          ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 14,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => 14,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // List of users receiving change notifications for this task.
    $fields['watch_list'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Watch list'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Links.
    $fields['link'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Link(s)'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_EXTERNAL,
        'title' => DRUPAL_OPTIONAL,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'link_default',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setReadOnly(TRUE);

    // Attach images.
    $fields['images'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image(s)'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSettings([
        'file_directory' => 'task',
        'file_extensions' => 'png jpg jpeg',
        'alt_field_required' => FALSE,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => 25,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'image_image',
        'weight' => 25,
        'settings' => [
          'title_field' => TRUE,
          'alt_field' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Relationship with other tasks.
    $fields['relationships'] = BaseFieldDefinition::create('burndown_task_relationship')
      ->setLabel(t('Related to'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => 28,
      ])
      ->setDisplayOptions('form', [
        'type' => 'burndown_task_relationship_default',
        'weight' => 28,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Completed.
    $fields['completed'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Task is completed.'))
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Resolution.
    $fields['resolution'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Resolution status'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Log / comments.
    $fields['log'] = BaseFieldDefinition::create('burndown_log')
      ->setLabel(t('Log'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Publication status.
    $fields['status']->setDescription(t('A boolean indicating whether the Task is published.'))
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 30,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 35,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 45,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
