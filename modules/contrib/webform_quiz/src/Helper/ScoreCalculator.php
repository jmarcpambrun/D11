<?php

namespace Drupal\webform_quiz\Helper;

use Drupal\webform_quiz\Model\WebformQuizResults;
use Drupal\webform_quiz\QuizResults;

/**
 * Score calculator class.
 */
class ScoreCalculator extends CalculatorBase {

  /**
   * Calculate the score based on the quiz submission.
   */
  protected function calculate() {
    $qr = new QuizResults($this->webformSubmission);

    $this->results = WebformQuizResults::create([
      'webform_quiz_number_of_points_received' => $qr->getNumberOfPointsReceived(),
      'webform_quiz_total_number_of_points' => $qr->getNumberOfPointsAvailable(),
      'webform_quiz_score' => $qr->getPercentageCorrect(),
    ]);

  }

}
