<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the Project entity.
 *
 * @ingroup burndown
 *
 * @ContentEntityType(
 *   id = "burndown_project",
 *   label = @Translation("Project"),
 *   bundle_label = @Translation("Project type"),
 *   handlers = {
 *     "storage" = "Drupal\burndown\ProjectStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\burndown\ProjectListBuilder",
 *     "views_data" = "Drupal\burndown\Entity\ProjectViewsData",
 *     "translation" = "Drupal\burndown\ProjectTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\burndown\Form\ProjectForm",
 *       "add" = "Drupal\burndown\Form\ProjectForm",
 *       "edit" = "Drupal\burndown\Form\ProjectForm",
 *       "delete" = "Drupal\burndown\Form\ProjectDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\burndown\ProjectHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\burndown\ProjectAccessControlHandler",
 *   },
 *   base_table = "burndown_project",
 *   data_table = "burndown_project_field_data",
 *   revision_table = "burndown_project_revision",
 *   revision_data_table = "burndown_project_field_revision",
 *   translatable = TRUE,
 *   permission_granularity = "bundle",
 *   admin_permission = "administer project entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "shortcode" = "shortcode",
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
 *     "canonical" = "/burndown/project/{burndown_project}",
 *     "add-page" = "/burndown/project/add",
 *     "add-form" = "/burndown/project/add/{burndown_project_type}",
 *     "edit-form" = "/burndown/project/{burndown_project}/edit",
 *     "delete-form" = "/burndown/project/{burndown_project}/delete",
 *     "version-history" = "/burndown/project/{burndown_project}/revisions",
 *     "revision" = "/burndown/project/{burndown_project}/revisions/{burndown_project_revision}/view",
 *     "revision_revert" = "/burndown/project/{burndown_project}/revisions/{burndown_project_revision}/revert",
 *     "revision_delete" = "/burndown/project/{burndown_project}/revisions/{burndown_project_revision}/delete",
 *     "translation_revert" = "/burndown/project/{burndown_project}/revisions/{burndown_project_revision}/revert/{langcode}",
 *     "collection" = "/burndown/project",
 *   },
 *   bundle_entity_type = "burndown_project_type",
 *   field_ui_base_route = "entity.burndown_project_type.edit_form"
 * )
 */
class Project extends EditorialContentEntityBase implements ProjectInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;
  use StringTranslationTrait;

  /**
   * Load a project using its shortcode.
   */
  public static function loadFromShortcode($shortcode) {
    $project_ids = \Drupal::entityQuery('burndown_project')
      ->condition('shortcode', $shortcode)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($project_ids)) {
      $project_id = array_pop($project_ids);
      return Project::load($project_id);
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

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }

      // Ensure shortcode is uppercase alphabetical only.
      $shortcode = $translation->getShortCode();
      $shortcode = preg_replace("/[^a-zA-Z]+/", "", $shortcode);

      if (!empty($shortcode)) {
        $shortcode = strtoupper($shortcode);
        $translation->setShortcode($shortcode);
      }
    }

    // If no revision author has been set explicitly,
    // make the burndown_project owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * Get list of estimate sizes from config.
   */
  public function getEstimateSizes() {
    $values = [];
    $estimate_type = $this->getEstimateType();

    if (is_null($estimate_type)) {
      return $values;
    }

    // Get config object.
    $config = \Drupal::config('burndown.config_settings');

    if ($estimate_type == 'geometric') {
      $list = $config->get('geometric_size_defaults');
    }
    elseif ($estimate_type == 'tshirt') {
      $list = $config->get('tshirt_size_defaults');
    }
    elseif ($estimate_type == 'dot') {
      // Handled below.
    }
    // Possible future expansion to new estimation types.
    else {
      return $values;
    }

    if (!empty($list)) {
      // List is a text string with one item per line.
      $list = preg_split("/\r\n|\n|\r/", $list);

      foreach ($list as $row) {
        // Rows are in the form "id|label".
        $val = explode('|', $row);
        if ($estimate_type == 'geometric') {
          // Force the keys to be strings for geometric.
          $key = strval($val[0]) . 'D';
        }
        else {
          $key = $val[0];
        }
        $values[$key] = $this->t(strval($val[1]));
      }
    }

    // Dot sizes.
    if ($estimate_type == 'dot') {
      $values = [
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
      ];
    }

    // Return our array.
    return $values;
  }

  /**
   * Check if this is a sprint-type project.
   */
  public function isSprint() {
    return ($this->getBoardType() == 'sprint');
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
  public function getShortcode() {
    return $this->get('shortcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setShortcode($name) {
    $this->set('shortcode', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoardType() {
    return $this->get('board_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setBoardType($name) {
    $this->set('board_type', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEstimateType() {
    return $this->get('estimate_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEstimateType($estimate_type) {
    $this->set('estimate_type', $estimate_type);
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
      ->setDescription(t('The user ID of author of the Project entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'author',
        'weight' => -10,
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
      ->setDescription(t('The name of the Project entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['shortcode'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Shortcode'))
      ->setDescription(t('A short (4 to 10 letters) string that will preface the ticket IDs for this project.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'min_length' => 4,
        'max_length' => 10,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['board_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Project type'))
      ->setDescription(t('Sets whether the project will use a kanban or sprint style board.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'allowed_values' => [
          'kanban' => 'Kanban',
          'sprint' => 'Sprint',
        ],
      ])
      ->setDefaultValue('kanban')
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['estimate_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Estimate type'))
      ->setDescription(t('What type of task estimation should this project use (leave blank to not show estimation on tasks).'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'allowed_values' => [
          'geometric' => 'Geometric',
          'tshirt' => 'T-Shirt Sizing',
          'dot' => 'Dot Sizing',
        ],
      ])
      ->setDefaultValue('geometric')
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['ticket_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Current Ticket ID'))
      ->setDescription(t('The current ID for tickets in this project'))
      ->setSettings([
        'min' => 0,
      ])
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setReadOnly(TRUE)
      ->setRequired(TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the Project is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 25,
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
