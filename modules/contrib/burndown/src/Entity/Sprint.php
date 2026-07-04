<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Sprint entity.
 *
 * @ingroup burndown
 *
 * @ContentEntityType(
 *   id = "burndown_sprint",
 *   label = @Translation("Sprint"),
 *   handlers = {
 *     "storage" = "Drupal\burndown\SprintStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\burndown\SprintListBuilder",
 *     "views_data" = "Drupal\burndown\Entity\SprintViewsData",
 *     "translation" = "Drupal\burndown\SprintTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\burndown\Form\SprintForm",
 *       "add" = "Drupal\burndown\Form\SprintForm",
 *       "edit" = "Drupal\burndown\Form\SprintForm",
 *       "delete" = "Drupal\burndown\Form\SprintDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\burndown\SprintHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\burndown\SprintAccessControlHandler",
 *   },
 *   base_table = "burndown_sprint",
 *   data_table = "burndown_sprint_field_data",
 *   revision_table = "burndown_sprint_revision",
 *   revision_data_table = "burndown_sprint_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer sprint entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "label" = "name",
 *     "sort_order" = "sort_order",
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
 *     "canonical" = "/burndown/sprint/{burndown_sprint}",
 *     "add-form" = "/burndown/sprint/add",
 *     "edit-form" = "/burndown/sprint/{burndown_sprint}/edit",
 *     "delete-form" = "/burndown/sprint/{burndown_sprint}/delete",
 *     "version-history" = "/burndown/sprint/{burndown_sprint}/revisions",
 *     "revision" = "/burndown/sprint/{burndown_sprint}/revisions/{burndown_sprint_revision}/view",
 *     "revision_revert" = "/burndown/sprint/{burndown_sprint}/revisions/{burndown_sprint_revision}/revert",
 *     "revision_delete" = "/burndown/sprint/{burndown_sprint}/revisions/{burndown_sprint_revision}/delete",
 *     "translation_revert" = "/burndown/sprint/{burndown_sprint}/revisions/{burndown_sprint_revision}/revert/{langcode}",
 *     "collection" = "/burndown/sprint",
 *   },
 *   field_ui_base_route = "burndown_sprint.settings"
 * )
 */
class Sprint extends EditorialContentEntityBase implements SprintInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * Get the current sprint (if there is one).
   */
  public static function getCurrentSprintFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $sprint_ids = \Drupal::entityQuery('burndown_sprint')
        ->condition('project', $project->id())
        ->condition('status', 'started')
        ->sort('sort_order', 'ASC')
        ->range(0, 1)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($sprint_ids)) {
        $sprint_ids = array_pop($sprint_ids);
        return Sprint::load($sprint_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get new (unopened) sprints.
   */
  public static function getBacklogSprintsFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $sprint_ids = \Drupal::entityQuery('burndown_sprint')
        ->condition('project', $project->id())
        ->condition('status', 'new')
        ->sort('sort_order', 'ASC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($sprint_ids)) {
        return Sprint::loadMultiple($sprint_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get top unopened sprint in backlog (i.e. so we can check if it can open).
   */
  public static function getTopBacklogSprintFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $sprint_ids = \Drupal::entityQuery('burndown_sprint')
        ->condition('project', $project->id())
        ->condition('status', 'new')
        ->sort('sort_order', 'ASC')
        ->range(0, 1)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($sprint_ids)) {
        $sprint_id = array_pop($sprint_ids);
        return Sprint::load($sprint_id);
      }
    }

    return FALSE;
  }

  /**
   * Get completed sprints.
   */
  public static function getCompletedSprintsFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $sprint_ids = \Drupal::entityQuery('burndown_sprint')
        ->condition('project', $project->id())
        ->condition('status', 'completed')
        ->sort('sort_order', 'ASC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($sprint_ids)) {
        return Sprint::loadMultiple($sprint_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get all sprints for a project.
   */
  public static function getSprintsFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $sprint_ids = \Drupal::entityQuery('burndown_sprint')
        ->condition('project', $project->id())
        ->sort('sort_order', 'ASC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($sprint_ids)) {
        return Sprint::loadMultiple($sprint_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get max sort order of sprints for a project.
   */
  public static function getMaxSortOrderFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $sprint_ids = \Drupal::entityQuery('burndown_sprint')
        ->condition('project', $project->id())
        ->condition('status', 'completed')
        ->sort('sort_order', 'DESC')
        ->range(0, 1)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($sprint_ids)) {
        $sprint_id = array_pop($sprint_ids);
        $sprint = Sprint::load($sprint_id);
        if ($sprint !== FALSE) {
          return $sprint->getSortOrder();
        }
      }
    }

    // This will get incremented to zero.
    return -1;
  }

  /**
   * Check if a sprint can be opened.
   */
  public function canOpen() {
    // Get project.
    $project = $this->getProject();
    $shortcode = $project->getShortcode();

    // Ensure sprint isn't already started (or completed).
    if ($this->getStatus() !== 'new') {
      return FALSE;
    }

    // Ensure project doesn't have existing open sprint.
    $already_open = Sprint::getCurrentSprintFor($shortcode);
    if ($already_open !== FALSE) {
      return FALSE;
    }

    // Check if this is the first sprint in the backlog list.
    $top = Sprint::getTopBacklogSprintFor($shortcode);
    if ($top === FALSE || $top->id() != $this->id()) {
      return FALSE;
    }

    // Ensure sprint has tasks (i.e. don't open an empty sprint).
    $tasks = Task::getTasksForBacklogSprint($shortcode, $this->id());
    if ($tasks === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Start a sprint. Optional start_date should be a time stamp.
   */
  public function startSprint($start_date = NULL) {
    // Get project.
    $project = $this->getProject();
    $shortcode = $project->getShortcode();

    // Check if this sprint can be opened.
    if (!$this->canOpen()) {
      return FALSE;
    }

    // Date.
    if ($start_date == NULL) {
      $start = time();
      $start = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, time());
    }
    else {
      $start = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_date);
    }

    // Set status.
    $this
      ->setStatus('started')
      ->set('start_date', $start)
      ->save();

    // Move all tasks to To Do column.
    $todo = Swimlane::getTodoSwimlane($shortcode);
    $tasks = Task::getTasksForBacklogSprint($shortcode, $this->id());
    foreach ($tasks as $task) {
      $task
        ->setSwimlane($todo)
        ->save();
    }

    // Success.
    return TRUE;
  }

  /**
   * Close a sprint.
   */
  public function closeSprint($end_date = NULL) {
    // Date.
    if ($end_date == NULL) {
      $end_date = time();
      $end_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, time());
    }
    else {
      $end_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_date);
    }

    // Set status.
    $this
      ->setStatus('completed')
      ->set('end_date', $end_date)
      ->save();
  }

  /**
   * Get data array, for usage in the board controllers.
   */
  public function getData() {
    return [
      'id' => $this->id(),
      'name' => $this->getName(),
      'status' => $this->getStatus(),
      'sort' => $this->getSortOrder(),
    // Filled in by the controller.
      'tasks' => [],
    ];
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
          'status' => 'new',
        ];

        // Determine the current highest sort order for a backlog sprint.
        $sort_order = Sprint::getMaxSortOrderFor($shortcode);

        // Make this sprint's order one larger.
        $values += [
          'sort_order' => ($sort_order + 1),
        ];
      }
    }
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

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly,
    // make the burndown_sprint owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
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
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOrder() {
    return $this->get('sort_order')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSortOrder($sort_order) {
    $this->set('sort_order', $sort_order);
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
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate() {
    return $this->get('start_date')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate() {
    return $this->get('end_date')->value;
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

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Sprint entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Sprint entity (i.e. "Week 1" etc).'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
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

    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project'))
      ->setDescription(t('The project that this column is part of.'))
      ->setSetting('target_type', 'burndown_project')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -5,
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

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Sprint Status'))
      ->setDescription(t('The sprint status.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue('new')
      ->setSettings([
        'allowed_values' => [
          'new' => 'New (not yet started)',
          'started' => 'Started (only one at a time per project)',
          'completed' => 'Completed',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['sort_order'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Sort Order'))
      ->setSettings([
        'min' => 0,
      ])
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['start_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Start Date'))
      ->setDescription(t('The date that the sprint is started.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'medium',
        ],
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('End Date'))
      ->setDescription(t('The date that the sprint is completed.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'medium',
        ],
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the Sprint is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
