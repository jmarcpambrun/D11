<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Tests random UI aspects.
 *
 * @group Quiz
 */
class QuizUiTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['quiz_truefalse'];

  /**
   * Tests the delete link appearing for questions on a quiz.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testQuizQuestionDeletion(): void {
    $this->drupalLogin($this->admin);

    $quiz = $this->createQuiz();

    $question = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 1 body text',
    ]);
    $this->linkQuestionToQuiz($question, $quiz);

    $this->drupalGet("quiz/{$quiz->id()}/questions");
    $this->assertSession()->linkByHrefExists('/quiz-question-relationship/1/delete');

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer]" => '0',
    ], (string) $this->t('Finish'));

    $this->drupalGet("quiz/{$quiz->id()}/questions");
    $this->assertSession()->linkByHrefNotExists('/quiz-question-relationship/1/delete');
  }

}
