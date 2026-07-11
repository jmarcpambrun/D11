<?php

namespace Drupal\quiz\Config\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines the list builder for Quiz entities.
 */
class QuizListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Quiz');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->toLink(NULL, 'edit-form');
    $row['type'] = $entity->bundle();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    if ($entity->access('update')) {
      $operations['questions'] = [
        'title' => $this->t('Questions'),
        'weight' => 101,
        'url' => Url::fromRoute('quiz.questions', ['quiz' => $entity->id()]),
      ];

      $operations['results'] = [
        'title' => $this->t('Results'),
        'weight' => 102,
        'url' => Url::fromRoute('view.quiz_results.list', ['quiz' => $entity->id()]),
      ];
    }

    return $operations;
  }

}
