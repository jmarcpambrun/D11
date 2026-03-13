<?php

namespace Drupal\burndown\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type "burndown_task_relationship".
 *
 * @FieldType(
 *   id = "burndown_task_relationship",
 *   label = @Translation("Burndown Task Relationship"),
 *   description = @Translation("Relationship between two tasks."),
 *   default_widget = "burndown_task_relationship_default",
 *   default_formatter = "burndown_task_relationship_default",
 * )
 */
class BurndownTaskRelationship extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $output = [
      'columns' => [

        // ID (internal) of the other task.
        'task_id' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        // Type (i.e. blocks, follow up of, etc).
        'type' => [
          'type' => 'varchar',
          'length' => 50,
        ],
      ],
    ];

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['task_id'] = DataDefinition::create('integer')
      ->setLabel(t('Task ID'))
      ->setRequired(TRUE);

    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Relationship Type'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $item = $this->getValue();

    if (isset($item['task_id']) && !empty($item['task_id']) &&
      isset($item['type']) && !empty($item['type'])) {
      return FALSE;
    }

    return TRUE;
  }

}
