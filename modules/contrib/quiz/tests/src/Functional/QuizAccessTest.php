<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz_short_answer\Plugin\quiz\QuizQuestion\ShortAnswerQuestion;

/**
 * @file
 * Unit tests for the quiz question Module.
 */

/**
 * Test aspects of quiz access and permissions.
 *
 * @group Quiz
 */
class QuizAccessTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['quiz_short_answer'];

  /**
   * Test quiz authors being able to score results for own quiz.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQuizOwnerResultEdit(): void {
    $grader = $this->drupalCreateUser(['score own quiz']);

    $question = $this->createQuestion([
      'type' => 'short_answer',
      'title' => 'SA 1 title',
      'short_answer_evaluation' => ShortAnswerQuestion::ANSWER_MANUAL,
      'short_answer_correct' => 'blue',
      'body' => 'SA 1 body text',
    ]);
    $quiz = $this->createQuiz(['uid' => $grader->id()]);
    $this->linkQuestionToQuiz($question, $quiz);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 'bluish',
    ], (string) $this->t('Finish'));

    // Score.
    $this->drupalLogin($grader);

    // Check unevaluated quiz results view.
    $this->drupalGet("user/{$grader->id()}/quiz-score");
    $this->clickLink($this->t('Score'));
    $this->submitForm([
      "question[{$question->id()}][score]" => 5,
    ], (string) $this->t('Save score'));
  }

  /**
   * Test quiz takers being able to grade their own results.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testQuizTakerAnswerScore(): void {
    $question = $this->createQuestion([
      'type' => 'short_answer',
      'title' => 'SA 1 title',
      'short_answer_evaluation' => ShortAnswerQuestion::ANSWER_MANUAL,
      'short_answer_correct' => 'blue',
      'body' => 'SA 1 body text',
    ]);
    $quiz = $this->linkQuestionToQuiz($question);

    $grader = $this->drupalCreateUser(['update own quiz_result']);
    $other = $this->drupalCreateUser(['update own quiz_result']);
    $this->drupalLogin($grader);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 'bluish',
    ], (string) $this->t('Finish'));

    // Make sure others cannot edit.
    $this->drupalLogin($other);
    // Check unevaluated quiz results view.
    $this->drupalGet("user/{$other->id()}/quiz-result-score");
    $this->assertSession()->linkNotExists($this->t('Score'));
    $this->drupalGet("quiz/{$quiz->id()}/result/1/edit");
    $this->assertSession()->statusCodeEquals(403);

    // Score.
    $this->drupalLogin($grader);
    // Check unevaluated quiz results view.
    $this->drupalGet("user/{$grader->id()}/quiz-result-score");
    $this->clickLink($this->t('Score'));
    $this->submitForm([
      "question[{$question->id()}][score]" => 5,
    ], (string) $this->t('Save score'));
  }

}
