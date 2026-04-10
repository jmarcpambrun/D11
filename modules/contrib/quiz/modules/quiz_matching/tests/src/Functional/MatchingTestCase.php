<?php

namespace Drupal\Tests\quiz_matching\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;

/**
 * Test class for matching questions.
 *
 * @group Quiz
 */
class MatchingTestCase extends QuizQuestionTestBase {

  use StringTranslationTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['quiz_matching'];

  /**
   * Test adding and taking a matching question.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function testCreateQuizQuestion($settings = []): QuizQuestion {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    $question_node = $this->createQuestion($settings + [
      'type' => 'matching',
      'title' => 'MA 1 title',
      'body' => 'MA 1 body text',
      'choice_penalty' => 0,
    ]);

    for ($i = 0; $i <= 2; $i++) {
      // Set up some alternatives.
      $a = Paragraph::create([
        'type' => 'quiz_matching',
        'matching_question' => "MAQ " . ($i + 1),
        'matching_answer' => "MAA " . ($i + 1),
      ]);
      $a->save();
      $question_node->get('quiz_matching')->appendItem($a);
    }

    $question_node->save();

    return $question_node;
  }

  /**
   * Test using a matching question inside a quiz.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testTakeQuestion() {
    $quiz_node = $this->createQuiz([
      'review_options' => [
        'end' => array_combine([
          'answer_feedback',
          'score',
        ], ['answer_feedback', 'score']),
      ],
    ]);

    $question_node = $this->testCreateQuizQuestion();

    // Link the question.
    $this->linkQuestionToQuiz($question_node, $quiz_node);

    // Test that question appears in lists.
    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $this->assertSession()->pageTextContains('MA 1 title');

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Take the quiz.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertSession()->pageTextNotContains('MA 1 title');
    $this->assertSession()->pageTextContains('MA 1 body text');
    $this->assertSession()->pageTextContains('MAQ 1');
    $this->assertSession()->pageTextContains('MAQ 2');
    $this->assertSession()->pageTextContains('MAQ 3');
    $this->assertSession()->pageTextContains('MAA 1');
    $this->assertSession()->pageTextContains('MAA 2');
    $this->assertSession()->pageTextContains('MAA 3');

    // Test validation.
    $this->submitForm([], $this->t('Finish'));
    $this->assertSession()->pageTextContains('You need to match at least one of the items.');

    // Test correct question.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[1][answer][user_answer][1]" => 1,
      "question[1][answer][user_answer][2]" => 2,
      "question[1][answer][user_answer][3]" => 3,
    ], $this->t('Finish'));
    // We may not have MCQ feedback, since it always displays.
    // Question feedback can be used.
    // $this->assertSession()->pageTextContains('MAF 1');
    // $this->assertSession()->pageTextContains('MAF 2');
    // $this->assertSession()->pageTextContains('MAF 3');.
    $this->assertSession()->pageTextContains('You got 3 of 3 possible points.');

    // Test incorrect question.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[1][answer][user_answer][1]" => 1,
      "question[1][answer][user_answer][2]" => 2,
      "question[1][answer][user_answer][3]" => 2,
    ], $this->t('Finish'));
    // We may not have MCQ feedback, since it always displays.
    // Question feedback can be used.
    $this->assertSession()->pageTextContains('You got 2 of 3 possible points.');
    $this->assertSession()->pageTextContains('Score: 2 of 3');
  }

  /**
   * Test if the penalty system for guessing wrong work.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ElementTextException
   */
  public function testChoicePenalty() {
    $quiz_node = $this->createQuiz([
      'review_options' => ['end' => array_combine(['answer_feedback', 'score'], ['answer_feedback', 'score'])],
    ]);

    $question_node = $this->testCreateQuizQuestion([
      'choice_penalty' => 1,
    ]);

    // Link the question.
    $this->linkQuestionToQuiz($question_node, $quiz_node);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Test penalty.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question_node->id()}][answer][user_answer][1]" => 1,
      "question[{$question_node->id()}][answer][user_answer][2]" => 1,
      "question[{$question_node->id()}][answer][user_answer][3]" => 3,
    ], $this->t('Finish'));
    $this->assertSession()->pageTextContains('You got 1 of 3 possible points.');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[2]/td[@class="quiz-result-cell-score"]', '0');
  }

  /**
   * Test that the question response is prefilled and can be edited.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEditQuestionResponse() {
    // Create & link a question.
    $question_node = $this->testCreateQuizQuestion();
    $quiz_node = $this->linkQuestionToQuiz($question_node);

    $question_node2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question_node2, $quiz_node);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Take the quiz.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");

    // Test editing a question.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question_node->id()}][answer][user_answer][1]" => 1,
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question_node->id()}][answer][user_answer][1]" => 2,
      "question[{$question_node->id()}][answer][user_answer][2]" => 3,
    ], $this->t('Next'));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestionType(): string {
    return 'matching';
  }

  /**
   * Test matching shuffle.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMatchingShuffle() {
    $config = \Drupal::configFactory()->getEditable('quiz_matching.settings');
    $config->set('shuffle', 1)->save();
    $quiz = $this->createQuiz();
    $question = $this->testCreateQuizQuestion();
    $quiz->addQuestion($question);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // We have to do this a few times to get a random result.
    for ($i = 1; $i <= 5; $i++) {
      // Take the quiz.
      $this->drupalGet("quiz/{$quiz->id()}/take");
      $content = $this->getSession()->getPage()->getContent();

      $one = strpos($content, 'MAQ 1');
      $two = strpos($content, 'MAQ 2');
      $three = strpos($content, 'MAQ 3');

      $result[] = ($one < $two && $two < $three);
    }
    // Assert that one of the results is different.
    $this->assertNotEquals(count($result), count(array_filter($result)), 'Matching questions were shuffled.');
  }

}
