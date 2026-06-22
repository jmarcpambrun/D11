<?php

namespace Drupal\burndown;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Sprint entities.
 *
 * @ingroup burndown
 */
class SprintListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Sprint ID');
    $header['project'] = $this->t('Project');
    $header['name'] = $this->t('Name');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\burndown\Entity\Sprint $entity */
    $row['id'] = $entity->id();
    $row['project'] = $entity->getProject() ? $entity->getProject()->getShortcode() : '';
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.burndown_sprint.canonical',
      ['burndown_sprint' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE);

    // Sort to keep the projects together and then by id within the project.
    $query->sort('project', 'ASC');
    $query->sort('id', 'ASC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query;
  }

}
