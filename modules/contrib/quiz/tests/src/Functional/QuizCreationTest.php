<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test aspects of quiz creation.
 *
 * @group Quiz
 */
class QuizCreationTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['quiz_truefalse'];

  /**
   * Test basic quiz creation.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testQuizCreation() {
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/add/quiz");

    // These are the basic system defaults.
    $this->assertSession()->checkboxChecked('edit-allow-resume-value');
    $this->assertSession()->checkboxChecked('edit-allow-skipping-value');
    $this->assertSession()->checkboxNotChecked('edit-allow-jumping-value');
    $this->assertSession()->checkboxChecked('edit-allow-change-value');
    $this->assertSession()->checkboxChecked('edit-backwards-navigation-value');
    $this->assertSession()->checkboxNotChecked('edit-repeat-until-correct-value');
    $this->assertSession()->checkboxNotChecked('edit-mark-doubtful-value');
    $this->assertSession()->checkboxChecked('edit-show-passed-value');
    $this->assertSession()->checkboxChecked('edit-status-value');

    $this->submitForm([
      'title[0][value]' => 'Test quiz creation',
      'body[0][value]' => 'Test quiz description',
    ], (string) $this->t('Save'));
    $this->assertSession()->pageTextContains('Manage questions');
  }

  /**
   * Test cloning quizzes with questions.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testCloneQuiz() {
    $this->drupalLogin($this->admin);
    $question = $this->createQuestion([
      'title' => 'TF 1',
      'body' => 'TF 1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $quiz = $this->linkQuestionToQuiz($question);

    $quiz->save();
    $new_quiz = $quiz->createDuplicate();
    $new_quiz->save();
    $this->assertNotEquals($new_quiz->id(), $quiz->id());

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->assertSession()->pageTextContains('TF 1');
    $this->drupalGet("quiz/{$new_quiz->id()}/take");
    $this->assertSession()->pageTextContains('TF 1');
  }

}
