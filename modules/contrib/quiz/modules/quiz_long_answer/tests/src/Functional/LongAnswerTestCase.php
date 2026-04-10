<?php

namespace Drupal\Tests\quiz_long_answer\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;

/**
 * Unit tests for the long_answer Module.
 *
 * @group Quiz
 */
class LongAnswerTestCase extends QuizQuestionTestBase {

  use StringTranslationTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['quiz_long_answer'];

  /**
   * {@inheritdoc}
   */
  public function getQuestionType(): string {
    return 'long_answer';
  }

  /**
   * Test manually graded questions.
   *
   * Also test feedback here instead of its own test case.
   *
   * Note: we use two questions here to make sure the grading form is handled
   * correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testGradeAnswerManualFeedback(): void {
    $this->drupalLogin($this->admin);

    $question1 = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question1);

    $question2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question2, $quiz);

    // Test correct.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'the answer is the zero one infinity rule',
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 'the number two really is ridiculous',
    ], $this->t('Finish'));
    $this->assertSession()->pageTextContains('Your score: 0%');
    // Strange behavior - extra spacing in the HTML.
    // $this->assertSession()->pageTextContains('Score ? of 10');.
    $this->assertSession()->pageTextContains('This answer has not yet been scored.');
    $this->assertSession()->fieldNotExists('question[0][score]');
    $this->assertSession()->fieldNotExists('question[1][score]');
    $url_of_result = $this->getUrl();

    // Test grading the question.
    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/quiz/reports/unevaluated');
    $this->clickLink($this->t('Score'));
    $this->submitForm([
      "question[1][score]" => 3,
      "question[2][score]" => 7,
      "question[1][answer_feedback][value]" => 'Feedback for answer 1.',
      "question[2][answer_feedback][value]" => 'Feedback for answer 2.',
      "question[1][answer_feedback][format]" => 'basic_html',
      "question[2][answer_feedback][format]" => 'basic_html',
    ], $this->t('Save score'));
    $this->assertSession()->pageTextContains('The scoring data you provided has been saved.');

    // Test the score and feedback are visible to the user.
    $this->drupalLogin($this->user);
    $this->drupalGet($url_of_result);
    $this->assertSession()->pageTextContains('You got 10 of 20 possible points.');
    $this->assertSession()->pageTextContains('Your score: 50%');
    $this->assertSession()->pageTextContains('Feedback for answer 1.');
    $this->assertSession()->pageTextContains('Feedback for answer 2.');
  }

  /**
   * Test adding and taking a long answer question.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function testCreateQuizQuestion($settings = []) {
    if (!$settings) {
      $settings = [
        'long_answer_rubric' => [
          'value' => 'LA 1 rubric.',
          'format' => 'plain_text',
        ],
        'answer_text_processing' => 0,
      ];
    }

    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    $question = QuizQuestion::create([
      'type' => 'long_answer',
      'title' => 'LA 1 title',
      'body' => 'LA 1 body text.',
    ] + $settings);
    $question->save();

    return $question;
  }

  /**
   * Test that rubric and answer filter settings are respected.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testFilterFormats() {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    // Question that has no filtering, for rubric or answer.
    $question1 = QuizQuestion::create([
      'type' => 'long_answer',
      'title' => 'LA 1 title',
      'body' => 'LA 1 body text.',
      'long_answer_rubric' => [
        'value' => 'Rubric for LA 1, you will see the next tag <img alt="test" src="https://httpbin.org/image/png?findmeRubricPlaintext">',
        'format' => 'restricted_html',
      ],
      'answer_text_processing' => 0,
    ]);
    $question1->save();

    // Question that has filtering, for rubric and answer.
    $question2 = QuizQuestion::create([
      'type' => 'long_answer',
      'title' => 'LA 2 title',
      'body' => 'LA 2 body text.',
      'long_answer_rubric' => [
        'value' => 'Rubric for LA 2, you will not see the next tag <img alt="test" src="https://httpbin.org/image/png?findmeRubricFiltered">',
        'format' => 'full_html',
      ],
      'answer_text_processing' => 1,
    ]);
    $question2->save();

    $quiz = $this->linkQuestionToQuiz($question1);
    $this->linkQuestionToQuiz($question2, $quiz);

    // Login as a user and take the quiz.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    // Post plaintext answer.
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'plaintext answer, you will see the next tag: <img alt="test" src="https://httpbin.org/image/png?findmeAnswerPlaintext">',
    ], $this->t('Next'));
    // Post rich text answer.
    $this->submitForm([
      "question[{$question2->id()}][answer][value]" => 'filtered answer, you will see not see the next tag: <img alt="test" src="https://httpbin.org/image/png?findmeAnswerFiltered">',
      "question[{$question2->id()}][answer][format]" => 'basic_html',
    ], $this->t('Finish'));

    // Login as a user and check the result.
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/{$quiz->id()}/result/1");
    $this->assertSession()->pageTextContains('<img alt="test" src');
    $this->assertSession()->pageTextNotContains('findmeRubricFiltered');
    $this->assertSession()->pageTextContains('findmeAnswerPlaintext');
    $this->assertSession()->pageTextNotContains('findmeAnswerFiltered');
  }

  /**
   * Test that the question response can be edited.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testEditQuestionResponse() {
    $this->drupalLogin($this->admin);

    // Create & link a question.
    $question1 = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question1);
    $question2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question2, $quiz);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'um some rule, I forget',
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->assertSession()->responseContains('um some rule, I forget');
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'um some rule, I forget',
    ], $this->t('Next'));
  }

  /**
   * Test that the question response can be exported.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testViews() {
    // Create & link a question.
    $question1 = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question1);

    // Login as non-admin.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'um some rule, I forget',
    ], $this->t('Finish'));

    $this->drupalGet("quiz/{$quiz->id()}/quiz-result-export-test");
    $this->assertSession()->pageTextContains('um some rule, I forget');
  }

}
