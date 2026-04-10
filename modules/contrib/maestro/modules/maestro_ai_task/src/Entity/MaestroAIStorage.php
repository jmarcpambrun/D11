<?php

namespace Drupal\maestro_ai_task\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * Defines the MaestroAIStorage entity.
 *
 * @ContentEntityType(
 *   id = "maestro_ai_storage",
 *   label = @Translation("Maestro AI Storage"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\maestro_ai_task\Entity\Controller\MaestroAIStorageListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\maestro\MaestroProcessAccessControlHandler",
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "maestro_ai_storage",
 *   admin_permission = "administer maestro ai storage entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "machine_name",
 *   },
 *   links = {
 *     "canonical" = "/maestro_ai_storage/{maestro_ai_storage}",
 *     "edit-form" = "/maestro_ai_storage/{maestro_ai_storage}/edit",
 *     "delete-form" = "/maestro_ai_storage/{maestro_ai_storage}/delete"
 *   }
 * )
 */
class MaestroAIStorage extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Machine Name field.
    $fields['machine_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Storage Entity Machine Name'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Task ID field.
    $fields['task_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Task ID'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Queue ID field.
    $fields['queue_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Queue ID'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Process ID field.
    $fields['process_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Process ID'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // AI Storage (blob) field.
    $fields['ai_storage'] = BaseFieldDefinition::create('map')
      ->setLabel(t('AI Storage'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Timestamp for entity creation.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 10,
      ]);

    // Entity author.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the entity author.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 11,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->isNew()) {
      // Set the creation timestamp to the current time for new entities.
      $this->set('created', time());
    }
  }

  /**
   * Gets the current user ID for default author field value.
   */
  public static function getCurrentUserId() {
    return \Drupal::currentUser()->id();
  }

}
