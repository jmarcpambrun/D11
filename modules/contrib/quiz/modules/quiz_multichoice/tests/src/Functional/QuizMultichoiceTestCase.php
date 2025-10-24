<?php

namespace Drupal\Tests\quiz_multichoice\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResult;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;

/**
 * Test multiple choice questions.
 *
 * @group Quiz
 */
class QuizMultichoiceTestCase extends QuizQuestionTestBase {

  use StringTranslationTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['quiz', 'quiz_multichoice'];

  /**
   * Create a default MCQ with default settings.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function testCreateQuizQuestion($settings = []) {

    // Set up some alternatives.
    $a = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_correct' => 1,
      'multichoice_answer' => 'Alternative A',
      'multichoice_feedback_chosen' => 'You chose A',
      'multichoice_feedback_not_chosen' => 'You did not choose A',
      'multichoice_score_chosen' => 1,
      'multichoice_score_not_chosen' => 0,
    ]);
    $a->save();

    $b = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_answer' => 'Alternative B',
      'multichoice_feedback_chosen' => 'You chose B',
      'multichoice_feedback_not_chosen' => 'You did not choose B',
      'multichoice_score_chosen' => -1,
      'multichoice_score_not_chosen' => 0,
    ]);
    $b->save();

    $c = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_answer' => 'Alternative C',
      'multichoice_feedback_chosen' => 'You chose C',
      'multichoice_feedback_not_chosen' => 'You did not choose C',
      'multichoice_score_chosen' => -1,
      'multichoice_score_not_chosen' => 0,
    ]);
    $c->save();

    $question = QuizQuestion::create($settings + [
      'title' => 'MCQ 1 Title',
      'type' => 'multichoice',
      'choice_multi' => 0,
      'choice_random' => 0,
      'choice_boolean' => 0,
      'body' => 'MCQ 1 body text',
    ]);

    $question->get('alternatives')->appendItem($a);
    $question->get('alternatives')->appendItem($b);
    $question->get('alternatives')->appendItem($c);

    $question->save();

    return $question;
  }

  /**
   * Test question feedback.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testQuestionFeedback() {
    $this->drupalLogin($this->admin);

    $question = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Test incorrect question. Feedback, answer.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => 2,
    ], $this->t('Finish'));
    $this->assertSession()->responseMatches('/quiz-score-icon selected/');
    $this->assertSession()->responseMatches('/quiz-score-icon should/');
    $this->assertSession()->responseMatches('/quiz-score-icon incorrect/');
    $this->assertSession()->pageTextContains('You did not choose A');
    $this->assertSession()->pageTextContains('You chose B');
    $this->assertSession()->pageTextContains('You did not choose C');
  }

  /**
   * Test multiple answers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testMultipleAnswers() {
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(['choice_multi' => 1]);
    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      "question[{$question->id()}][answer][user_answer][3]" => 3,
    ], $this->t('Finish'));
    // 0 of 1, because user picked a correct answer and an incorrect answer.
    $this->assertSession()->pageTextContains('You got 0 of 1 possible points.');
    $this->assertSession()->pageTextContains('Your score: 0%');

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer][1]" => 1,
    ], $this->t('Finish'));
    // 1 of 1, because user picked a correct answer and not an incorrect answer.
    $this->assertSession()->pageTextContains('You got 1 of 1 possible points.');
    $this->assertSession()->pageTextContains('Your score: 100%');
  }

  /**
   * Test restoring a multiple choice answer.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAnswerMultiRestore() {
    // Checkboxes.
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(['choice_multi' => 1]);
    $question2 = $this->testCreateQuizQuestion(['choice_multi' => 1]);
    $quiz = $this->linkQuestionToQuiz($question);
    $this->linkQuestionToQuiz($question2, $quiz);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer][1]" => 1,
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->assertSession()->checkboxChecked('edit-question-1-answer-user-answer-1');
  }

  /**
   * Test restoring a single choice answer.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAnswerSingleRestore() {
    // Radio buttons.
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(['choice_multi' => 0]);
    $question2 = $this->testCreateQuizQuestion(['choice_multi' => 0]);
    $quiz = $this->linkQuestionToQuiz($question);
    $this->linkQuestionToQuiz($question2, $quiz);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => 1,
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->assertSession()->checkboxChecked('edit-question-1-answer-user-answer-1');
  }

  /**
   * Test random order of choices.
   *
   * @todo I don't know how we would test random questions.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRandomOrder() {
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(['choice_random' => 1]);
    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->user);

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => 1,
    ], $this->t('Finish'));
  }

  /**
   * Test simple scoring.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testSimpleScoring() {
    $this->drupalLogin($this->admin);

    // Set up some alternatives. Two of the answers are correct.
    $a = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_correct' => 1,
      'multichoice_answer' => 'Alternative A',
      'multichoice_score_chosen' => 1,
      'multichoice_score_not_chosen' => 0,
    ]);
    $a->save();

    $b = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_correct' => 1,
      'multichoice_answer' => 'Alternative B',
      'multichoice_score_chosen' => 1,
      'multichoice_score_not_chosen' => 0,
    ]);
    $b->save();

    $c = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_correct' => 0,
      'multichoice_answer' => 'Alternative C',
      'multichoice_score_chosen' => 0,
      'multichoice_score_not_chosen' => 0,
    ]);
    $c->save();

    // Set simple scoring so choosing both answers is required.
    $question = QuizQuestion::create([
      'title' => 'MCQ 1 Title',
      'type' => 'multichoice',
      'choice_multi' => 1,
      'choice_random' => 0,
      'choice_boolean' => 1,
      'body' => 'MCQ 1 body text',
    ]);

    $question->get('alternatives')->appendItem($a);
    $question->get('alternatives')->appendItem($b);
    $question->get('alternatives')->appendItem($c);

    $question->save();

    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->user);

    // Test selecting a wrong answer, incorrect.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      "question[{$question->id()}][answer][user_answer][3]" => 3,
    ], $this->t('Finish'));
    $this->assertSession()->pageTextContains('You got 0 of 1 possible points.');
    $this->assertSession()->pageTextContains('Your score: 0%');
    // Get the last result and make sure it is 0%.
    $efq = \Drupal::entityQuery('quiz_result');
    $result = $efq->range(0, 1)
      ->accessCheck(FALSE)
      ->condition('qid', $quiz->id())
      ->condition('uid', $this->user->id())
      ->sort('result_id', 'desc')
      ->execute();
    $keys = array_keys($result);
    $existing = QuizResult::load(reset($keys));
    $this->assertEquals(0, $existing->get('score')->value, 'Score is 0%');

    // Test selecting all answers, which would be incorrect.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      "question[{$question->id()}][answer][user_answer][2]" => 1,
      "question[{$question->id()}][answer][user_answer][3]" => 1,
    ], $this->t('Finish'));
    $this->assertSession()->pageTextContains('You got 0 of 1 possible points.');
    $this->assertSession()->pageTextContains('Your score: 0%');
    // Get the last result and make sure it is 0%.
    $efq = \Drupal::entityQuery('quiz_result');
    $result = $efq->range(0, 1)
      ->accessCheck(FALSE)
      ->condition('qid', $quiz->id())
      ->condition('uid', $this->user->id())
      ->sort('result_id', 'desc')
      ->execute();
    $keys = array_keys($result);
    $existing = QuizResult::load(reset($keys));
    $this->assertEquals(0, $existing->get('score')->value, 'Score is 0%');

    // Correct.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      "question[{$question->id()}][answer][user_answer][2]" => 1,
    ], $this->t('Finish'));
    $this->assertSession()->pageTextContains('You got 1 of 1 possible points.');
    $this->assertSession()->pageTextContains('Your score: 100%');

    // Get the last result and make sure it is 100%, not 200%.
    $efq = \Drupal::entityQuery('quiz_result');
    $result = $efq->range(0, 1)
      ->accessCheck(FALSE)
      ->condition('qid', $quiz->id())
      ->condition('uid', $this->user->id())
      ->sort('result_id', 'desc')
      ->execute();
    $keys = array_keys($result);
    $existing = QuizResult::load(reset($keys));
    $this->assertEquals(100, $existing->get('score')->value, 'Score is 100%');
  }

  /**
   * Test that the question response can be edited.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEditQuestionResponse() {
    // Create & link a question.
    $question = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question);

    $question2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question2, $quiz);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Take the quiz.
    $this->drupalGet("quiz/{$quiz->id()}/take");

    // Test editing a question.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => 1,
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => 2,
    ], $this->t('Next'));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestionType(): string {
    return 'multichoice';
  }

}
