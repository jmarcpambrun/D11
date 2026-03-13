<?php

namespace Drupal\quiz\Config\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the list builder for quiz question types.
 */
class QuizQuestionTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['type'] = $this->t('Question type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['type'] = $entity->toLink(NULL, 'edit-form');
    return $row + parent::buildRow($entity);
  }

}
