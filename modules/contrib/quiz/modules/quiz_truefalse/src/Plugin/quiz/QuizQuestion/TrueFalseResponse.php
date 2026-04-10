<?php

namespace Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;

/**
 * Extension of QuizQuestionResponse.
 */
class TrueFalseResponse extends QuizResultAnswer {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function score(array $response): ?int {
    $tfQuestion = $this->getQuizQuestion();
    $this->set('truefalse_answer', $response['answer']);
    $this->setEvaluated();

    if ($response['answer'] == $tfQuestion->getCorrectAnswer()) {
      return $tfQuestion->getMaximumScore();
    }
    else {
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return $this->get('truefalse_answer')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {

    $answer = $this->getResponse();
    if (is_numeric($answer)) {
      $answer = intval($answer);
    }

    $correct_answer = intval($this->getQuizQuestion()->getCorrectAnswer());

    $rows = [];

    $true_chosen = TRUE;
    $true_correct = TRUE;
    $false_chosen = FALSE;
    $false_correct = FALSE;
    if ($answer === 0) {
      $true_chosen = FALSE;
      $false_chosen = TRUE;
    }
    if ($correct_answer === 0) {
      $true_correct = FALSE;
      $false_correct = TRUE;
    }

    $rows[] = [
      'status' => [
        'correct' => $true_correct,
        'chosen' => $true_chosen,
      ],
      'data' => [
        'choice' => $this->t('True'),
        'attempt' => $true_chosen ? QuizUtil::icon('selected') : '',
        'correct' => $true_chosen ? QuizUtil::icon($true_correct ? 'correct' : 'incorrect') : '',
        'score' => intval($true_correct && $true_chosen),
        'answer_feedback' => '',
        'solution' => $true_correct ? QuizUtil::icon('should') : '',
      ],
    ];

    $rows[] = [
      'status' => [
        'correct' => $false_correct,
        'chosen' => $false_chosen,
      ],
      'data' => [
        'choice' => $this->t('False'),
        'attempt' => $false_chosen ? QuizUtil::icon('selected') : '',
        'correct' => $false_chosen ? (QuizUtil::icon($false_correct ? 'correct' : 'incorrect')) : '',
        'score' => intval($false_correct && $false_chosen),
        'answer_feedback' => '',
        'solution' => $false_correct ? QuizUtil::icon('should') : '',
      ],
    ];

    return $rows;
  }

  /**
   * Get answers for a question in a result.
   *
   * This static method assists in building views for the mass export of
   * question answers.
   *
   * @see views_handler_field_prerender_list
   */
  public static function viewsGetAnswers(array $result_answer_ids = []): array {
    $items = [];
    foreach (QuizResultAnswer::loadMultiple($result_answer_ids) as $qra) {
      $items[$qra->getQuizResultId()][] = [
        'answer' => $qra->getResponse() ? t('True') : t('False'),
      ];
    }
    return $items;
  }

}
