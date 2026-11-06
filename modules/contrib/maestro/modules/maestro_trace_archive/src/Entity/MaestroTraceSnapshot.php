<?php

namespace Drupal\maestro_trace_archive\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Maestro Trace Snapshot entity.
 *
 * One row per (queue_id, snapshot_point). A point-in-time capture of every
 * process variable and entity identifier for the owning process, taken
 * immediately before or after a task executes.
 *
 * @ContentEntityType(
 *   id = "maestro_trace_snapshot",
 *   label = @Translation("Maestro Trace Snapshot"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "maestro_trace_snapshot",
 *   admin_permission = "administer maestro templates",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 * )
 */
class MaestroTraceSnapshot extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['queue_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Queue ID'))
      ->setDescription(t('The maestro_queue entity ID this snapshot was taken for.'))
      ->setRequired(TRUE);

    $fields['process_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Process ID'))
      ->setDescription(t('The maestro_process entity ID.'))
      ->setRequired(TRUE);

    $fields['task_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Task ID'))
      ->setDescription(t('The machine name of the task, from the template.'))
      ->setRequired(TRUE);

    $fields['snapshot_point'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Snapshot Point'))
      ->setDescription(t('Either "before" or "after" the task executed.'))
      ->setRequired(TRUE);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('Serialized snapshot of process variables and entity identifiers.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the snapshot was taken.'));

    return $fields;
  }

}
