<?php

namespace Drupal\drd\Entity\ViewsData;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for DRD Major entities.
 */
class Major extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['drd_major']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('DRD Major'),
      'help' => $this->t('DRD Major ID.'),
    ];

    $data['drd_major']['status_label']['field'] = [
      'title' => $this->t('Update Status Label'),
      'help' => $this->t('TBD'),
      'id' => 'drd_update_status',
    ];

    $data['drd_major']['coreversion']['filter']['id'] = 'drd_core_versions';
    $data['drd_major']['majorversion']['filter']['id'] = 'drd_major_versions';

    $data['drd_major']['releases']['relationship'] = [
      'title' => $this->t('Releases of this Major'),
      'label' => $this->t('Releases of this Major'),
      'help' => $this->t('TBD.'),
      'id' => 'standard',
      'base' => 'drd_release',
      'base field' => 'major',
      'field' => 'id',
    ];

    return $data;
  }

}
