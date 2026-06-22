<?php

namespace Drupal\burndown;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Task entities.
 *
 * @ingroup burndown
 */
class TaskListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Task ID');
    $header['pid'] = $this->t('Project ID');
    $header['name'] = $this->t('Task');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\burndown\Entity\Task $entity */
    $row['id'] = $entity->id();
    $row['project_id'] = Link::createFromRoute(
      $entity->label(),
      'entity.burndown_task.canonical',
      ['burndown_task' => $entity->id()]
    );
    $row['name'] = $entity->getName();
    $row['status'] = $entity->isCompleted() ? $this->t('Completed') : $entity->getSwimlane()->getName();
    return $row + parent::buildRow($entity);
  }

}
