<?php

namespace Drupal\quiz_short_answer\Plugin\quiz\QuizQuestion;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;

/**
 * Extension of QuizResultAnswer.
 */
class ShortAnswerResponse extends QuizResultAnswer {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function score(array $values): ?int {
    $question = $this->getQuizQuestion();

    $correct = $question->get('short_answer_correct')->getString();

    $this->set('short_answer', $values['answer']);

    switch ($question->get('short_answer_evaluation')->getString()) {
      case ShortAnswerQuestion::ANSWER_MANUAL:
        $this->setEvaluated(FALSE);
        break;

      case ShortAnswerQuestion::ANSWER_MATCH:
        $this->setEvaluated();
        if ($values['answer'] == $correct) {
          return $question->getMaximumScore();
        }
        break;

      case ShortAnswerQuestion::ANSWER_INSENSITIVE_MATCH:
        $this->setEvaluated();
        if (strtolower($values['answer']) == strtolower($correct)) {
          return $question->getMaximumScore();
        }
        break;

      case ShortAnswerQuestion::ANSWER_REGEX:
        $this->setEvaluated();
        if (preg_match($correct, $values['answer']) > 0) {
          return $question->getMaximumScore();
        }
        break;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return $this->get('short_answer')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {
    $rows = [];
    $score = $this->getPoints();
    $max = $this->getMaxScore();
    $correct = FALSE;
    $icon = NULL;

    if ($this->isEvaluated()) {
      // Question has been graded.
      if ($score == 0) {
        $icon = QuizUtil::icon('incorrect');
      }
      if ($score > 0) {
        $icon = QuizUtil::icon('almost');
      }
      if ($score == $max) {
        $correct = TRUE;
        $icon = QuizUtil::icon('correct');
      }
    }
    else {
      $correct = NULL;
      $icon = QuizUtil::icon('unknown');
    }

    $answer_feedback = $this->get('answer_feedback')->getValue()[0];

    $rows[] = [
      'status' => [
        'correct' => $correct,
        'chosen' => TRUE,
      ],
      'data' => [
        // Hide this column as there are no choices for short answer.
        'choice' => NULL,
        'attempt' => $this->get('short_answer')->getString(),
        'correct' => $icon,
        'score' => !$this->isEvaluated() ? $this->t('This answer has not yet been scored.') : $this->getPoints(),
        'answer_feedback' => check_markup((string) $answer_feedback['value'], $answer_feedback['format']),
        'solution' => $this->getQuizQuestion()->get('short_answer_correct')->getString(),
      ],
    ];

    return $rows;
  }

}
