<?php

namespace Drupal\group\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\Access\ConfigWrapperAccessControlHandler;
use Drupal\group\Entity\Storage\ConfigWrapperStorage;
use Drupal\group\Entity\Storage\ConfigWrapperStorageSchema;

/**
 * Defines the ConfigWrapper entity.
 *
 * @ingroup group
 */
#[ContentEntityType(
  id: 'group_config_wrapper',
  label: new TranslatableMarkup('Config wrapper'),
  label_singular: new TranslatableMarkup('config wrapper'),
  label_plural: new TranslatableMarkup('config wrappers'),
  entity_keys: [
    'id' => 'id',
    'bundle' => 'bundle',
  ],
  handlers: [
    'access' => ConfigWrapperAccessControlHandler::class,
    'storage' => ConfigWrapperStorage::class,
    'storage_schema' => ConfigWrapperStorageSchema::class,
  ],
  base_table: 'group_config_wrapper',
  label_count: [
    'singular' => '@count config wrapper',
    'plural' => '@count config wrappers',
  ],
)]
class ConfigWrapper extends ContentEntityBase implements ConfigWrapperInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigEntity() {
    return $this->get('entity_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigEntityId() {
    return $this->get('entity_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Config'))
      ->setDescription(t('The config entity to wrap.'))
      // Required to force a string data type.
      ->setSetting('target_type', 'group_type')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    assert($base_field_definitions['entity_id'] instanceof BaseFieldDefinition);
    $fields['entity_id'] = clone $base_field_definitions['entity_id'];
    $fields['entity_id']->setSetting('target_type', $bundle);
    return $fields;
  }

}
