<?php

declare(strict_types=1);

namespace Drupal\quiz_feedback_rules\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\quiz_feedback_rules\Entity\QuizFeedbackTypeRules;

/**
 * Hook implementations for quiz_feedback_rules.
 */
class QuizFeedbackRulesHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    if (isset($entity_types['quiz_feedback_type'])) {
      $entity_types['quiz_feedback_type']->setClass(QuizFeedbackTypeRules::class);
    }
  }

}
