<?php

namespace Drupal\quiz\Util;

use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;

/**
 * Utility functions that don't belong anywhere else.
 */
class QuizUtil {

  /**
   * Get the quiz name variable .
   *
   * And set it as a constant, so we don't have to
   * keep calling it in every function.
   *
   * @return string
   *   Quiz name variable.
   */
  public static function getQuizName(): string {
    $quiz = \Drupal::entityTypeManager()->getDefinition('quiz');
    return $quiz->getLabel();
  }

  /**
   * Helper function to facilitate icon display, like "correct" or "selected".
   *
   * @param string $type
   *   The type of icon.
   *
   * @return array
   *   Render array.
   */
  public static function icon(string $type): array {
    $options = [];

    switch ($type) {
      case 'correct':
        $options['path'] = 'check_008000_64.png';
        $options['alt'] = t('Correct');
        break;

      case 'incorrect':
        $options['path'] = 'times_ff0000_64.png';
        $options['alt'] = t('Incorrect');
        break;

      case 'unknown':
        $options['path'] = 'question_808080_64.png';
        $options['alt'] = t('Unknown');
        break;

      case 'should':
        $options['path'] = 'check_808080_64.png';
        $options['alt'] = t('Should have chosen');
        break;

      case 'should-not':
        $options['path'] = 'times_808080_64.png';
        $options['alt'] = t('Should not have chosen');
        break;

      case 'almost':
        $options['path'] = 'check_ffff00_64.png';
        $options['alt'] = t('Almost');
        break;

      case 'selected':
        $options['path'] = 'arrow-right_808080_64.png';
        $options['alt'] = t('Selected');
        break;

      case 'unselected':
        $options['path'] = 'circle-o_808080_64.png';
        $options['alt'] = t('Unselected');
        break;

      default:
        $options['path'] = '';
        $options['alt'] = '';
    }

    if (!empty($options['path'])) {
      $options['path'] = \Drupal::service('extension.list.module')->getPath('quiz') . '/images/' . $options['path'];
    }
    if (!empty($options['alt'])) {
      $options['title'] = $options['alt'];
    }

    return [
      '#theme' => 'image',
      '#uri' => $options['path'],
      '#alt' => $options['title'],
      '#attributes' => ['class' => ['quiz-score-icon', $type]],
    ];
  }

  /**
   * Use in the case where a quiz may have ended.
   *
   * The temporary result ID must be used instead.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz.
   *
   * @return \Drupal\quiz\Entity\QuizResult|null
   *   Quiz result from the current user's session.
   */
  public static function resultOrTemp(Quiz $quiz): ?QuizResult {
    /** @var \Drupal\quiz\Services\QuizSessionInterface $quiz_session */
    $quiz_session = \Drupal::service('quiz.session');
    if ($quiz_result = $quiz_session->getResult($quiz)) {
      return $quiz_result;
    }
    elseif ($quiz_result = $quiz_session->getTemporaryResult()) {
      return $quiz_result;
    }
    return NULL;
  }

}
