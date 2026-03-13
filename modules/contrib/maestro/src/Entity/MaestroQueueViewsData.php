<?php

namespace Drupal\maestro\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for the Maestro Queue Entity.
 */
class MaestroQueueViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['maestro_queue']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Maestro Queue'),
      'help' => $this->t('The Maestro Queue entity ID.'),
    ];

     // Relationship to the Maestro Process entity
    $data['maestro_queue']['process_id'] = [
      'title' => t('Maestro Process'),
      'help' => t('Relate this queue entity to its associated process.'),
      'relationship' => [
        'base' => 'maestro_process',
        'base field' => 'process_id',
        'field' => 'process_id',
        'id' => 'standard',
        'label' => t('Maestro Process Entity'),
      ],
    ];

     // Relationship to the Maestro Process Variables entity
    $data['maestro_queue']['pv_process_id'] = [
      'title' => t('Maestro Process Variables'),
      'help' => t('Relate this queue entity to its associated Process Variables.'),
      'relationship' => [
        'base' => 'maestro_process_variables',
        'base field' => 'process_id',
        'field' => 'process_id',
        'id' => 'standard',
        'label' => t('Maestro Process Variables Entity'),
      ],
    ];

     // Relationship to the Maestro Production Assignments entity
    $data['maestro_queue']['queue_id'] = [
      'title' => t('Maestro Production Assignments'),
      'help' => t('Relate this queue entity to its associated Production Assignments.'),
      'relationship' => [
        'base' => 'maestro_production_assignments',
        'base field' => 'queue_id',
        'field' => 'id',
        'id' => 'standard',
        'label' => t('Maestro Production Assignments Entity'),
      ],
    ];
    
    return $data;
  }

}
