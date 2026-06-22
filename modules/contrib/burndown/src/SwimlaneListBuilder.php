<?php

namespace Drupal\burndown;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Swimlane entities.
 *
 * @ingroup burndown
 */
class SwimlaneListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Swimlane ID');
    $header['name'] = $this->t('Name');
    $header['board'] = $this->t('Board');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\burndown\Entity\Swimlane $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.burndown_swimlane.canonical',
      ['burndown_swimlane' => $entity->id()]
    );

    $board_name = '';
    if ($entity->getProject() !== NULL) {
      $board_name = $entity->getProject()->getShortcode();
    }
    $row['board'] = $board_name;

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE);

    // Sort to keep the projects together and then by name within the project.
    $query->sort('project', 'ASC');
    $query->sort('name', 'ASC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query;
  }

}
