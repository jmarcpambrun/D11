<?php

namespace Drupal\burndown;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Project entities.
 *
 * @ingroup burndown
 */
class ProjectListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Project');
    $header['backlog'] = $this->t('Backlog');
    $header['board'] = $this->t('Board');
    $header['completed'] = $this->t('Completed');
    $header['swimlanes'] = $this->t('Manage Columns');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\burndown\Entity\Project $entity */
    $row['id'] = Link::createFromRoute(
      $entity->getShortcode() . ' - ' . $entity->label(),
      'entity.burndown_project.canonical',
      ['burndown_project' => $entity->id()]
    );
    $row['backlog'] = Link::createFromRoute(
      $this->t('Backlog'),
      'burndown.backlog',
      ['shortcode' => $entity->getShortcode()]
    );
    $row['board'] = Link::createFromRoute(
      $this->t('Board'),
      'burndown.board',
      ['shortcode' => $entity->getShortcode()]
    );
    $row['completed'] = Link::createFromRoute(
      $this->t('Completed'),
      'burndown.completed',
      ['shortcode' => $entity->getShortcode()]
    );
    $row['swimlanes'] = Link::createFromRoute(
      $this->t('Columns'),
      'burndown.project_swimlanes',
      ['shortcode' => $entity->getShortcode()]
    );
    return $row + parent::buildRow($entity);
  }

}
