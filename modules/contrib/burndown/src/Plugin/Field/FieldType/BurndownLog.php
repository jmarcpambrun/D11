<?php

namespace Drupal\burndown\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type "burndown_log".
 *
 * @FieldType(
 *   id = "burndown_log",
 *   label = @Translation("Burndown Log"),
 *   description = @Translation("Custom Burndown log field."),
 *   default_widget = "burndown_log_default",
 *   default_formatter = "burndown_log_default",
 * )
 */
class BurndownLog extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {

    $output = [
      'columns' => [
        // User entered comment.
        'comment' => [
          'type' => 'text',
          'size' => 'normal',
          'serialize' => TRUE,
        ],
        // Qty of work done (i.e. "30m", "2h" etc).
        'work_done' => [
          'type' => 'varchar',
          'length' => 50,
        ],
        // Date that the log was added.
        'created' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        // User id of the person who created the log.
        'uid' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        // Internal description.
        'description' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        // Type (i.e. added, changed, closed, work done, comment).
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

    $properties['comment'] = DataDefinition::create('any')
      ->setLabel(t('Comment'))
      ->setRequired(FALSE);

    $properties['work_done'] = DataDefinition::create('string')
      ->setLabel(t('Work Done'))
      ->setRequired(FALSE);

    $properties['created'] = DataDefinition::create('integer')
      ->setLabel(t('Date'))
      ->setRequired(FALSE);

    $properties['uid'] = DataDefinition::create('integer')
      ->setLabel(t('User ID'))
      ->setRequired(FALSE);

    $properties['description'] = DataDefinition::create('string')
      ->setLabel(t('Description'))
      ->setRequired(FALSE);

    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Log Type'))
      ->setRequired(FALSE);

    return $properties;

  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {

    $item = $this->getValue();

    $is_empty = TRUE;

    if (isset($item['comment']) && !empty($item['comment'])) {
      $is_empty = FALSE;
    }

    if (isset($item['work_done']) && !empty($item['work_done'])) {
      $is_empty = FALSE;
    }

    if (isset($item['description']) && !empty($item['description'])) {
      $is_empty = FALSE;
    }

    return $is_empty;

  }

}
