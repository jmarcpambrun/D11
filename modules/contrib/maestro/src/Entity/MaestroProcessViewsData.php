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

    return $data;
  }

}
