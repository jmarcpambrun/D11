<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\QuizQuestion;

/**
 * Test quiz repeat until correct.
 *
 * @group Quiz
 */
class QuizRepeatUntilCorrectTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['quiz_multichoice'];

  /**
   * Test the repeat until correct behavior.
   *
   * We also test that the answer is passed to feedback before being discarded.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testQuestionRepeatUntilCorrect() {
    $this->drupalLogin($this->admin);
    $quiz = $this->createQuiz([
      'repeat_until_correct' => 1,
      'review_options' => ['question' => ['answer_feedback' => 'answer_feedback']],
    ]);

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
      'multichoice_score_chosen' => 0,
      'multichoice_score_not_chosen' => 0,
    ]);
    $b->save();

    $question = QuizQuestion::create([
      'title' => 'MCQ 1 Title',
      'type' => 'multichoice',
      'choice_multi' => 0,
      'choice_random' => 0,
      'choice_boolean' => 0,
      'body' => 'MCQ 1 body text',
    ]);

    $question->get('alternatives')->appendItem($a);
    $question->get('alternatives')->appendItem($b);

    $question->save();

    $this->linkQuestionToQuiz($question, $quiz);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => '2',
    ], (string) $this->t('Finish'));
    $this->assertSession()->pageTextContains('The answer was incorrect. Please try again.');
    $this->assertSession()->pageTextContains('You chose B');

    // Check that we are still on the question.
    $this->assertSession()->addressEquals("quiz/{$quiz->id()}/take/1");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => '1',
    ], (string) $this->t('Finish'));
    $this->assertSession()->pageTextNotContains('The answer was incorrect. Please try again.');
  }

}
