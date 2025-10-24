<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\SelectEntitiesInterface;

/**
 * Provides abstract 'ListEntities' class.
 */
abstract class ListEntities extends BaseGlobal {

  /**
   * {@inheritdoc}
   */
  public function restrictAccess(): bool {
    return FALSE;
  }

  /**
   * Prepare the service to select entities.
   *
   * @return \Drupal\drd\SelectEntitiesInterface
   *   The service to select DRD entities from.
   */
  protected function prepareSelection(): SelectEntitiesInterface {
    return $this->entities
      ->setTag($this->arguments['tag'])
      ->setHost($this->arguments['host'])
      ->setHostId($this->arguments['host-id'])
      ->setCore($this->arguments['core'])
      ->setCoreId($this->arguments['core-id'])
      ->setDomain($this->arguments['domain'])
      ->setDomainId($this->arguments['domain-id']);
  }

}
