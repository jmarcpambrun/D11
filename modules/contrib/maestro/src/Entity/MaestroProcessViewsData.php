<?php

namespace Drupal\maestro\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for the Maestro Process Entity.
 */
class MaestroProcessViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['maestro_process']['table']['base'] = [
      'field' => 'process_id',
      'title' => $this->t('Maestro Process'),
      'help' => $this->t('The Maestro Process entity ID.'),
    ];

    $data['maestro_process']['initiator_uid']['relationship'] = [
      'title' => $this->t('Initiator user'),
      'help' => $this->t('Relate to the initiator user entity.'),
      'id' => 'standard',
      'base' => 'users_field_data',
      'base field' => 'uid',
      'label' => $this->t('Initiator user'),
    ];

    // The reverse of maestro_process_variables.process_id: from a process to
    // one of its variables. A process has many variables, so the
    // maestro_process_variable plugin also joins on the chosen variable's
    // name, keeping one row per process.
    $data['maestro_process']['process_variable'] = [
      'title' => $this->t('Maestro Process Variable'),
      'help' => $this->t('Relate the process to one of its process variables, chosen by name.'),
      'relationship' => [
        'title' => $this->t('Maestro Process Variable'),
        'help' => $this->t('Relate the process to one of its process variables, chosen by name, for its value as a field, filter or sort.'),
        'id' => 'maestro_process_variable',
        'base' => 'maestro_process_variables',
        'base field' => 'process_id',
        'field' => 'process_id',
        'label' => $this->t('Process variable'),
      ],
    ];

    return $data;
  }

}
