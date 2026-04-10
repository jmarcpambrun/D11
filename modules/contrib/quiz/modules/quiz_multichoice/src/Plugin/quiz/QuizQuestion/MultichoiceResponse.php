<?php

namespace Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;
use function check_markup;

/**
 * Extension of QuizQuestionResponse.
 */
class MultichoiceResponse extends QuizResultAnswer {

  /**
   * {@inheritdoc}
   */
  public function score(array $response): ?int {
    if (!is_array($response['answer']['user_answer'])) {
      $selected_vids = [$response['answer']['user_answer']];
    }
    else {
      $selected_vids = $response['answer']['user_answer'];
    }

    // Reset whatever was here already.
    $this->get('multichoice_answer')->setValue(NULL);

    // The answer ID is the revision ID of the Paragraph item of the MCQ.
    // Fun!
    foreach ($selected_vids as $vid) {
      // Loop through all selected answers and append them to the paragraph
      // revision reference.
      $this->get('multichoice_answer')->appendItem($vid);
    }

    $simple = $this->getQuizQuestion()->get('choice_boolean')->getString();
    $multi = $this->getQuizQuestion()->get('choice_multi')->getString();

    // Set score at 1 for simple scoring, 0 for regular scoring.
    $score = $simple ? 1 : 0;

    $alternatives = $this->getQuizQuestion()
      ->get('alternatives')
      ->referencedEntities();

    foreach ($alternatives as $alternative) {
      // Take action on each alternative being selected (or not).
      $vid = $alternative->getRevisionId();
      // If this alternative was selected.
      $selected = in_array($vid, $selected_vids);
      $correct = $alternative->get('multichoice_correct')->getString();

      if ($simple) {
        if ($selected && !$correct) {
          // Selected this alt, simple scoring on, and the alt was
          // incorrect. Answer is wrong.
          $this->setEvaluated();
          return 0;
        }

        if (!$selected && $correct) {
          // Did not select this alt, simple scoring on, and the alt was
          // correct. Answer is wrong.
          $this->setEvaluated();
          return 0;
        }
      }
      else {
        if ($selected && $correct && !$multi) {
          // User selected a correct answer and this is not a multiple answer
          // question. User gets the point value of the question.
          $this->setEvaluated();
          return $alternative->get('multichoice_score_chosen')->getString();
        }

        if ($multi) {
          // In multiple answer questions we add (or subtract) some points based
          // on what answers they selected.
          $score += $selected ? $alternative->get('multichoice_score_chosen')->getString() : $alternative->get('multichoice_score_not_chosen')->getString();
        }
      }
    }

    // Return the total points for a multiple answer question.
    $this->setEvaluated();
    return $score;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    $vids = [];
    foreach ($this->get('multichoice_answer')->getValue() as $alternative) {
      $vids[] = $alternative['value'];
    }
    return $vids;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {
    $simple_scoring = $this->getQuizQuestion()
      ->get('choice_boolean')
      ->getString();

    $user_answers = $this->getResponse();

    $rows = [];
    $alternatives = $this->getQuizQuestion()
      ->get('alternatives')
      ->referencedEntities();
    foreach ($alternatives as $alternative) {
      $chosen = in_array($alternative->getRevisionId(), $user_answers);
      $not = $chosen ? '' : 'not_';
      $chosen_feedback = $alternative->{"multichoice_feedback_{$not}chosen"};
      $correct = $alternative->multichoice_score_chosen->value > 0;
      $icon_correct = $correct ? QuizUtil::icon('correct') : QuizUtil::icon('incorrect');

      $rows[] = [
        'status' => [
          'correct' => $correct,
          'chosen' => $chosen,
        ],
        'data' => [
          'choice' => check_markup($alternative->multichoice_answer->value, $alternative->multichoice_answer->format),
          'attempt' => $chosen ? QuizUtil::icon('selected') : '',
          'correct' => $icon_correct,
          'score' => (int) $alternative->{"multichoice_score_{$not}chosen"}->value,
          'answer_feedback' => check_markup((string) $chosen_feedback->value, $chosen_feedback->format),
          'question_feedback' => 'Question feedback',
          'solution' => $correct ? QuizUtil::icon('should') : ($simple_scoring ? QuizUtil::icon('should-not') : ''),
          'quiz_feedback' => 'Quiz feedback',
        ],
      ];
    }

    return $rows;
  }

  /**
   * Order the alternatives according to the choice order stored in the db.
   *
   * @param array $alternatives
   *   The alternatives to be ordered.
   */
  protected function orderAlternatives(array &$alternatives): void {
    if (!$this->question->choice_random) {
      return;
    }
    // @todo Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
    // You will need to use `\Drupal\core\Database\Database::getConnection()`
    // if you do not yet have access to the container here.
    $result = \Drupal::database()
      ->query('SELECT choice_order FROM {quiz_multichoice_user_answers}
            WHERE result_answer_id = :raid', [':raid' => $this->result_answer_id])
      ->fetchField();
    if (!$result) {
      return;
    }
    $order = explode(',', $result);
    $newAlternatives = [];
    foreach ($order as $value) {
      foreach ($alternatives as $alternative) {
        if ($alternative['id'] == $value) {
          $newAlternatives[] = $alternative;
          break;
        }
      }
    }
    $alternatives = $newAlternatives;
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
      foreach ($qra->getResponse() as $vid) {
        if ($vid) {
          /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
          $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
          /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
          $paragraph = $storage->loadRevision($vid);
          $answer = trim(strip_tags($paragraph->get('multichoice_answer')->value));
          $items[$qra->getQuizResultId()][] = ['answer' => $answer];
        }
      }
    }

    return $items;
  }

}
