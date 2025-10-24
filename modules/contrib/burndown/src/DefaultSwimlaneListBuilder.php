<?php

namespace Drupal\burndown;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Default Swimlane entities.
 */
class DefaultSwimlaneListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Default Swimlane');
    $header['id'] = $this->t('Machine name');
    $header['boards'] = $this->t('Boards');
    $header['sort_order'] = $this->t('Sort Order');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();

    // Determine which boards this appears on.
    $show_backlog = $entity->getShowBacklog();
    $show_project_board = $entity->getShowProjectBoard();
    $show_completed = $entity->getShowCompleted();
    $boards = [];
    if ($show_backlog) {
      $boards[] = 'Backlog';
    }
    if ($show_project_board) {
      $boards[] = 'Project Board';
    }
    if ($show_completed) {
      $boards[] = 'Completed Board';
    }
    $row['boards'] = implode('<br />', $boards);

    $row['sort_order'] = $entity->getSortOrder();
    return $row + parent::buildRow($entity);
  }

}
