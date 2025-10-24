<?php

namespace Drupal\quiz_matching\Plugin\quiz\QuizQuestion;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;

/**
 * Extension of QuizQuestionResponse.
 */
class MatchingResponse extends QuizResultAnswer {

  /**
   * Implementation of score().
   *
   * @see QuizQuestionResponse::score()
   */
  public function score(array $values): ?int {
    $response = [];
    $this->setEvaluated();

    // Reset whatever was here already.
    $this->get('matching_user_answer')->setValue(NULL);

    foreach ($this->getQuizQuestion()->get('quiz_matching')->referencedEntities() as $paragraph) {
      // Foreach ($values['answer']['user_answer'] as $left_id => $right_id) {.
      $left_paragraph = $paragraph;
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
      /** @var \Drupal\paragraphs\Entity\Paragraph $right_paragraph */
      $right_paragraph = $storage->loadRevision($values['answer']['user_answer'][$paragraph->getRevisionId()]);
      $new_paragraph = Paragraph::create([
        'type' => 'quiz_matching_answer',
        'matching_user_question' => $left_paragraph->getRevisionId(),
        'matching_user_answer' => $right_paragraph ? $right_paragraph->getRevisionId() : '',
      ]);
      $this->get('matching_user_answer')->appendItem($new_paragraph);
    }

    $answers = $this->getQuizQuestion()->getCorrectAnswer();

    foreach ($this->get('matching_user_answer')->referencedEntities() as $pair) {
      if ($pair->get('matching_user_answer')->getValue()) {
        $response[$pair->get('matching_user_question')->value] = $pair->get('matching_user_answer')->value;
      }
      else {
        $response[$pair->get('matching_user_question')->value] = -1;
      }
    }

    $score = 0;

    foreach ($answers as $vid => $answer) {
      if ($response[$vid] == $vid) {
        $score++;
      }
      elseif ($response[$vid] != '' && $this->getQuizQuestion()->get('choice_penalty')->getString()) {
        $score -= 1;
      }
    }

    return max($score, 0);
  }

  /**
   * Implementation of getResponse().
   *
   * @see QuizQuestionResponse::getResponse()
   */
  public function getResponse() {
    return $this->get('matching_user_answer')->referencedEntities();
  }

  /**
   * Implementation of getFeedbackValues().
   *
   * @see QuizQuestionResponse::getFeedbackValues()
   */
  public function getFeedbackValues(): array {
    $rows = [];
    $response = [];
    $answers = $this->getQuizQuestion()->getCorrectAnswer();

    foreach ($this->get('matching_user_answer')->referencedEntities() as $pair) {
      if ($pair->get('matching_user_answer')->getValue()) {
        $response[$pair->get('matching_user_question')
          ->getValue()[0]['value']] = $pair->get('matching_user_answer')
          ->getValue()[0]['value'];
      }
    }

    $penalty = $this->getQuizQuestion()->get('choice_penalty')->value;
    foreach ($this->getQuizQuestion()
      ->get('quiz_matching')
      ->referencedEntities() as $paragraph) {
      $vid = $paragraph->getRevisionId();

      $match = ($response[$vid] ?? -1) == $vid;
      $score = 0;

      if ($match && !$penalty) {
        $score = 1;
      }

      $attempt_index = $response[intval($paragraph->getRevisionId())];
      $correct = ($response[$vid] ?? -1) == $vid;
      $icon_correct = $correct ? QuizUtil::icon('correct') : QuizUtil::icon('incorrect');

      $rows[] = [
        'status' => [
          'correct' => $correct,
          'chosen' => TRUE,
        ],
        'data' => [
          'choice' => $paragraph->get('matching_question')->getString(),
          'attempt' => isset($attempt_index) && $answers[$attempt_index] ? $answers[$attempt_index]->get('matching_answer')->getString() : '',
          'correct' => $icon_correct,
          'score' => $score,
        // @todo $answer->get('matching_feedback')->getString(),
          'answer_feedback' => 'placeholder',
          'solution' => $paragraph->get('matching_answer')->getString(),
        ],
      ];
    }

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
      foreach ($qra->getResponse() as $paragraph) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
        $q_vid = $paragraph->get('matching_user_question')->value;
        /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
        $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
        /** @var \Drupal\paragraphs\Entity\Paragraph $qp */
        $qp = $storage->loadRevision($q_vid);
        $a_vid = $paragraph->get('matching_user_answer')->value;
        if ($a_vid) {
          /** @var \Drupal\paragraphs\Entity\Paragraph $qa */
          $qa = $storage->loadRevision($a_vid);
          $items[$qra->getQuizResultId()][] = ['answer' => $qp->get('matching_question')->value . ': ' . $qa->get('matching_answer')->value];

        }
      }
    }

    return $items;
  }

}
