<?php

namespace Drupal\book\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;

/**
 * Provides migrate destination plugin for Book content.
 */
#[MigrateDestination('book')]
class Book extends EntityContentBase {

  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id): string {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  protected function updateEntity(EntityInterface $entity, Row $row) {
    $book = $entity->getBook();
    if (!empty($book)) {
      $book = $row->getDestinationProperty('book');
      foreach ($book as $key => $value) {
        $book[$key] = $value;
      }
    }
    else {
      $entity->setBook($row->getDestinationProperty('book'));
    }
    return parent::updateEntity($entity, $row);
  }

}
