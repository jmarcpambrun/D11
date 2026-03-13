<?php

namespace Drupal\Tests\quiz_page\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizResult;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;

/**
 * Test quiz page behavior.
 *
 * @group Quiz
 */
class QuizPageTestCase extends QuizQuestionTestBase {

  use StringTranslationTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['quiz_page', 'quiz_truefalse'];

  /**
   * {@inheritdoc}
   */
  public function getQuestionType(): string {
    return 'page';
  }

  /**
   * Test that question parentage saves.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testQuizPageParentage() {
    $this->drupalLogin($this->admin);

    // Create Quiz with review of score.
    $quiz_node = $this->createQuiz();

    // Create the questions.
    $question_node1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 1 body text',
    ]);
    // QNR ID 1.
    $this->linkQuestionToQuiz($question_node1, $quiz_node);
    $question_node2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 2 body text',
    ]);
    // QNR ID 2.
    $this->linkQuestionToQuiz($question_node2, $quiz_node);
    $question_node3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 3 body text',
    ]);
    // QNR ID 3.
    $this->linkQuestionToQuiz($question_node3, $quiz_node);
    // Create the pages.
    $page_node1 = $this->createQuestion(['type' => 'page']);
    // QNR ID 4.
    $this->linkQuestionToQuiz($page_node1, $quiz_node);
    $page_node2 = $this->createQuestion(['type' => 'page']);
    // QNR ID 5.
    $this->linkQuestionToQuiz($page_node2, $quiz_node);
    // Go to the manage questions form.
    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $post = [
      // Make the questions have parents.
      "question_list[{$question_node1->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question_node2->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question_node3->getRevisionId()}][qqr_pid]" => 5,
      // Mirror what JS would have done by adjusting the weights.
      "question_list[{$page_node1->getRevisionId()}][weight]" => 2,
      "question_list[{$question_node1->getRevisionId()}][weight]" => 3,
      "question_list[{$question_node2->getRevisionId()}][weight]" => 4,
      "question_list[{$page_node2->getRevisionId()}][weight]" => 3,
      "question_list[{$question_node3->getRevisionId()}][weight]" => 4,
    ];
    $this->submitForm($post, $this->t('Submit'));

    $sql = "SELECT * FROM {quiz_question_relationship}";
    $data = \Drupal::database()->query($sql)->fetchAllAssoc('qqr_id');
    // Check the relationships properly saved.
    foreach ($data as $qnr_id => $rel) {
      switch ($qnr_id) {
        case 1:
        case 2:
          $this->assertEquals(4, $rel->qqr_pid);
          break;

        case 3:
          $this->assertEquals(5, $rel->qqr_pid);
          break;

        case 4:
        case 5:
          $this->assertNull($rel->qqr_pid);
          break;
      }
    }

    // Take the quiz. Ensure the pages are correct.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    // Questions 1 and 2 are present. Question 3 is hidden.
    $this->assertSession()->fieldExists("question[{$question_node1->id()}][answer]");
    $this->assertSession()->fieldExists("question[{$question_node2->id()}][answer]");
    $this->assertSession()->fieldNotExists("question[{$question_node3->id()}][answer]");
    $this->submitForm([
      "question[{$question_node1->id()}][answer]" => 1,
      "question[{$question_node2->id()}][answer]" => 1,
    ], $this->t('Next'));
    // Questions 1 and 2 are gone. Question 3 is present.
    $this->assertSession()->fieldNotExists("question[{$question_node1->id()}][answer]");
    $this->assertSession()->fieldNotExists("question[{$question_node2->id()}][answer]");
    $this->assertSession()->fieldExists("question[{$question_node3->id()}][answer]");
    $this->submitForm([
      "question[{$question_node3->id()}][answer]" => 1,
    ], $this->t('Finish'));

    // Check that the results page contains all the questions.
    $this->assertSession()->pageTextContains('You got 3 of 3 possible points.');
    $this->assertSession()->pageTextContains('TF 1 body text');
    $this->assertSession()->pageTextContains('TF 2 body text');
    $this->assertSession()->pageTextContains('TF 3 body text');

    foreach (QuizResult::loadMultiple() as $quiz_result) {
      $quiz_result->delete();
    }

    // Check to make sure that saving a new revision of the Quiz does not affect
    // the parentage.
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/{$quiz_node->id()}/edit");
    $this->submitForm(['revision' => 1], $this->t('Save'));

    // Take the quiz. Ensure the pages are correct.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    // Questions 1 and 2 are present. Question 3 is hidden.
    $this->assertSession()->pageTextContains("Page 1 of 2");
    $this->assertSession()->fieldExists("question[{$question_node1->id()}][answer]");
    $this->assertSession()->fieldExists("question[{$question_node2->id()}][answer]");
    $this->assertSession()->fieldNotExists("question[{$question_node3->id()}][answer]");
    $this->submitForm([
      "question[{$question_node1->id()}][answer]" => 1,
      "question[{$question_node2->id()}][answer]" => 1,
    ], $this->t('Next'));

    // Questions 1 and 2 are gone. Question 3 is present.
    $this->assertSession()->pageTextContains("Page 2 of 2");
    $this->assertSession()->fieldNotExists("question[{$question_node1->id()}][answer]");
    $this->assertSession()->fieldNotExists("question[{$question_node2->id()}][answer]");
    $this->assertSession()->fieldExists("question[{$question_node3->id()}][answer]");

    // Test backwards navigation.
    $this->submitForm([], $this->t('Back'));
    $this->assertSession()->pageTextContains("Page 1 of 2");
    $this->submitForm([], $this->t('Next'));

    $this->submitForm([
      "question[{$question_node3->id()}][answer]" => 1,
    ], $this->t('Finish'));
  }

  /**
   * Test page feedback.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testPageFeedback() {
    $this->drupalLogin($this->admin);

    $quiz_node = $this->createQuiz(
      [
        'review_options' => ['question' => ['question_feedback' => 'question_feedback']],
      ]
    );

    // Create the questions.
    $question_node1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 1 body text',
      'feedback' => 'This is the feedback for question 1.',
    ]);
    // QNR ID 1.
    $this->linkQuestionToQuiz($question_node1, $quiz_node);
    $question_node2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 2 body text',
      'feedback' => 'This is the feedback for question 2.',
    ]);
    // QNR ID 2.
    $this->linkQuestionToQuiz($question_node2, $quiz_node);
    $question_node3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 3 body text',
      'feedback' => 'This is the feedback for question 3.',
    ]);
    // QNR ID 3.
    $this->linkQuestionToQuiz($question_node3, $quiz_node);
    //
    // Create the page.
    $page_node1 = $this->createQuestion([
      'type' => 'page',
      'body' => 'PG 1 body text',
    ]);
    // QNR ID 4.
    $this->linkQuestionToQuiz($page_node1, $quiz_node);
    // Go to the manage questions form.
    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $post = [
      // Make the questions have parents.
      "question_list[{$question_node1->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question_node2->getRevisionId()}][qqr_pid]" => 4,
      // Mirror what JS would have done by adjusting the weights.
      "question_list[{$page_node1->getRevisionId()}][weight]" => 1,
      "question_list[{$question_node1->getRevisionId()}][weight]" => 2,
      "question_list[{$question_node2->getRevisionId()}][weight]" => 3,
      "question_list[{$question_node3->getRevisionId()}][weight]" => 4,
    ];
    $this->submitForm($post, $this->t('Submit'));

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");

    $this->submitForm([
      "question[{$question_node1->id()}][answer]" => 1,
      "question[{$question_node2->id()}][answer]" => 1,
    ], $this->t('Next'));

    $this->assertSession()->pageTextContains('This is the feedback for question 1.');
    $this->assertSession()->pageTextContains('This is the feedback for question 2.');
    $this->assertSession()->pageTextNotContains('This is the feedback for question 3.');
  }

}
