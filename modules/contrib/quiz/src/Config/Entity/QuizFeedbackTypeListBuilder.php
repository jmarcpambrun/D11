<?php

namespace Drupal\quiz\Config\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines the list builder for quiz question types.
 */
class QuizFeedbackTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#caption'] = $this->t('Feedback types.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['type'] = $this->t('Feedback time');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['type'] = $entity->toLink(NULL, 'edit-form');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    $operations['conditions'] = [
      'title' => $this->t('Conditions'),
      'url' => Url::fromRoute('entity.quiz_feedback_type.conditions', ['quiz_feedback_type' => $entity->id()]),
      'weight' => 50,
    ];
    return $operations;
  }

}
