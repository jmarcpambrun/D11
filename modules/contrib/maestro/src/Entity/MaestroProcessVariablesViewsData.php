<?php

namespace Drupal\maestro\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for the Maestro Process VariablesEntity.
 */
class MaestroProcessVariablesViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['maestro_process_variables']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Maestro Process Variable'),
      'help' => $this->t('The Maestro Process Variable entity ID.'),
    ];

    // Relationship to the Maestro Process entity
    $data['maestro_process_variables']['process_id'] = [
      'title' => t('Maestro Process'),
      'help' => t('Relate this variable to its associated process.'),
      'relationship' => [
        'base' => 'maestro_process',
        'base field' => 'process_id',
        'field' => 'process_id',
        'id' => 'standard',
        'label' => t('Maestro Process Entity'),
      ],
    ];

    // Relationship to Maestro Queue entity.
    $data['maestro_process_variables']['queue_id'] = [
      'title' => t('Maestro Queue'),
      'help' => t('Relate this variable via the process ID to a Maestro Queue Entity.'),
      'relationship' => [
        'base' => 'maestro_queue',
        'base field' => 'process_id',
        'field' => 'process_id',
        'id' => 'standard',
        'label' => t('Maestro Queue Entity'),
      ],
    ];

    return $data;
  }

}
