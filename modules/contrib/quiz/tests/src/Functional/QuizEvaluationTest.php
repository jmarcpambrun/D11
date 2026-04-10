<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizResult;

/**
 * Test quiz evaluation.
 *
 * @group Quiz
 */
class QuizEvaluationTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['quiz_page', 'quiz_directions'];

  /**
   * Test that a quiz result is marked as evaluated.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQuizEvaluation(): void {
    $this->drupalLogin($this->admin);

    $quiz_node = $this->createQuiz();

    $question_node1 = $this->createQuestion([
      'type' => 'directions',
      'body' => 'These are the quiz directions.',
    ]);
    // QNR ID 1.
    $this->linkQuestionToQuiz($question_node1, $quiz_node);

    $page_node1 = $this->createQuestion(['type' => 'page']);
    // QNR ID 2.
    $this->linkQuestionToQuiz($page_node1, $quiz_node);

    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $post = [
      "question_list[{$question_node1->getRevisionId()}][qqr_pid]" => 2,
    ];
    $this->submitForm($post, (string) $this->t('Submit'));

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([], (string) $this->t('Finish'));

    $quiz_result = QuizResult::load(1);
    $this->assertTrue($quiz_result->isEvaluated());
  }

}
