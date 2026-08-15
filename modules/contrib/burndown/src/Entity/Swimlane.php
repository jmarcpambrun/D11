<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the Column (swimlane) entity.
 *
 * @ingroup burndown
 *
 * @ContentEntityType(
 *   id = "burndown_swimlane",
 *   label = @Translation("Column"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\burndown\SwimlaneListBuilder",
 *     "views_data" = "Drupal\burndown\Entity\SwimlaneViewsData",
 *     "translation" = "Drupal\burndown\SwimlaneTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\burndown\Form\SwimlaneForm",
 *       "add" = "Drupal\burndown\Form\SwimlaneForm",
 *       "edit" = "Drupal\burndown\Form\SwimlaneForm",
 *       "delete" = "Drupal\burndown\Form\SwimlaneDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\burndown\SwimlaneHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\burndown\SwimlaneAccessControlHandler",
 *   },
 *   base_table = "burndown_swimlane",
 *   data_table = "burndown_swimlane_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer swimlane entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "sort_order" = "sort_order",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/burndown/swimlane/{burndown_swimlane}",
 *     "add-form" = "/burndown/swimlane/add",
 *     "edit-form" = "/burndown/swimlane/{burndown_swimlane}/edit",
 *     "delete-form" = "/burndown/swimlane/{burndown_swimlane}/delete",
 *     "collection" = "/burndown/swimlane",
 *   },
 *   field_ui_base_route = "burndown_swimlane.settings"
 * )
 */
class Swimlane extends ContentEntityBase implements SwimlaneInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * Load swimlanes for a project.
   */
  public static function loadForProject($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane_ids = \Drupal::entityQuery('burndown_swimlane')
        ->condition('project', $project_id)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($swimlane_ids)) {
        return Swimlane::loadMultiple($swimlane_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get specific column (swimlane) for a project by name.
   */
  public static function getSwimlane($shortcode, $name) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane_ids = \Drupal::entityQuery('burndown_swimlane')
        ->condition('project', $project_id)
        ->condition('name', $name)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($swimlane_ids)) {
        $swimlane_id = array_pop($swimlane_ids);
        return Swimlane::load($swimlane_id);
      }
    }

    return FALSE;
  }

  /**
   * Find the backlog lane (if it exists) for a project.
   */
  public static function getBacklogFor($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane_ids = \Drupal::entityQuery('burndown_swimlane')
        ->condition('project', $project_id)
        ->condition('show_backlog', 1)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($swimlane_ids)) {
        $swimlane_id = array_pop($swimlane_ids);
        return Swimlane::load($swimlane_id);
      }
    }

    return FALSE;
  }

  /**
   * Get swimlanes for a project board.
   */
  public static function getBoardSwimlanes($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane_ids = \Drupal::entityQuery('burndown_swimlane')
        ->condition('project', $project_id)
        ->condition('show_project_board', 1)
        ->sort('sort_order', 'ASC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($swimlane_ids)) {
        return Swimlane::loadMultiple($swimlane_ids);
      }
    }

    return FALSE;
  }

  /**
   * Get the final "done" column on the project board.
   */
  public static function getDoneSwimlane($shortcode) {
    $lanes = Swimlane::getBoardSwimlanes($shortcode);
    if (!$lanes === FALSE) {
      return end($lanes);
    }

    return FALSE;
  }

  /**
   * Get first board swimlane (i.e. typically To Do col).
   */
  public static function getTodoSwimlane($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane_ids = \Drupal::entityQuery('burndown_swimlane')
        ->condition('project', $project_id)
        ->condition('show_project_board', 1)
        ->sort('sort_order', 'ASC')
        ->range(0, 1)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($swimlane_ids)) {
        $swimlane_id = array_pop($swimlane_ids);
        return Swimlane::load($swimlane_id);
      }
    }

    return FALSE;
  }

  /**
   * Get swimlane(s) for completed status(es).
   */
  public static function getCompletedSwimlanes($shortcode) {
    $project = Project::loadFromShortcode($shortcode);
    if ($project !== FALSE) {
      $project_id = $project->id();

      $swimlane_ids = \Drupal::entityQuery('burndown_swimlane')
        ->condition('project', $project_id)
        ->condition('show_completed', 1)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($swimlane_ids)) {
        return Swimlane::loadMultiple($swimlane_ids);
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
      'status' => 1,
      'show_project_board' => 1,
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
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    foreach ($entities as $entity) {
      /** @var \Drupal\burndown\Entity\Swimlane $entity */
      // Get the project.
      $project = $entity->getProject();
      if (isset($project)) {
        $shortcode = $project->getShortcode();

        // Get the backlog swimlane.
        $backlog = Swimlane::getBacklogFor($shortcode);

        // Get all tasks for this swimlane.
        $tasks = Task::getTasksForSwimlane($shortcode, $entity->getName());

        // Reassign the tasks to todo.
        if (!empty($tasks)) {
          foreach ($tasks as $task) {
            $task
              ->setSwimlane($backlog)
              ->save();
          }
        }
      }
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
    /** @var \Drupal\user\UserInterface|null $owner */
    $owner = $this->get('user_id')->entity;
    return $owner;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    $owner_id = $this->get('user_id')->target_id;
    return is_numeric($owner_id) ? (int) $owner_id : NULL;
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
   * Is this Column the backlog for a project?
   */
  public function isBacklog() {
    return $this->getShowBacklog();
  }

  /**
   * {@inheritdoc}
   */
  public function getShowBacklog() {
    return $this->get('show_backlog')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setShowBacklog($show_backlog) {
    $this->set('show_backlog', $show_backlog);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShowProjectBoard() {
    return $this->get('show_project_board')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setShowProjectBoard($show_project_board) {
    $this->set('show_project_board', $show_project_board);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShowCompleted() {
    return $this->get('show_completed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setShowCompleted($show_completed) {
    $this->set('show_completed', $show_completed);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Swimlane entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Swimlane entity.'))
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
      ->setDescription(t('The project that this swimlane is part of.'))
      ->setSetting('target_type', 'burndown_project')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
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
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['show_backlog'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Show in backlog display?'))
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 0,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['show_project_board'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Show on project board?'))
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 1,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['show_completed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Show in completed tasks?'))
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 2,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the Column is published.'))
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 15,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
