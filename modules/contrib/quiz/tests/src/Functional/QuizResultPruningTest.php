<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use function count;

/**
 * Test quiz result pruning behavior.
 *
 * @group Quiz
 */
class QuizResultPruningTest extends QuizTestBase {

  use CronRunTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['quiz_truefalse'];

  /**
   * Test the all, best, and last quiz result pruning.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testResultPruning() {
    $this->drupalLogin($this->admin);

    $quiz_node = $this->createQuiz([
      'keep_results' => Quiz::KEEP_ALL,
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);
    $question4 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question4, $quiz_node);

    $this->drupalLogin($this->user);

    // Create 2 100% results.
    for ($i = 1; $i <= 2; $i++) {
      $this->drupalGet("quiz/{$quiz_node->id()}/take");
      $this->submitForm([
        "question[{$question1->id()}][answer]" => '1',
      ], (string) $this->t('Next'));
      $this->submitForm([
        "question[{$question2->id()}][answer]" => '1',
      ], (string) $this->t('Next'));
      $this->submitForm([
        "question[{$question3->id()}][answer]" => '1',
      ], (string) $this->t('Next'));
      $this->submitForm([
        "question[{$question4->id()}][answer]" => '1',
      ], (string) $this->t('Finish'));
    }

    // Storing all results.
    $results = QuizResult::loadMultiple();
    $this->assertEquals(2, count($results), 'Found 2 quiz results.');

    $quiz_node->keep_results = Quiz::KEEP_LATEST;
    $quiz_node->save();

    // Create a 50% result.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => '0',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => '0',
    ], (string) $this->t('Finish'));

    // We should now have 2 invalid results.
    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEquals(2, count($results), 'Found 2 invalid quiz results');

    // We should only have one valid 50% result.
    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertEquals(1, count($results), 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEquals(50, $quiz_result->get('score')->value, 'Quiz result was 50%');

    $quiz_node->keep_results = Quiz::KEEP_BEST;
    $quiz_node->save();

    // Create a 25% result.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => '0',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => '0',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => '0',
    ], (string) $this->t('Finish'));

    $this->assertSession()->pageTextContains('Your previous score on this Quiz was equal or better. This result will not be saved.');

    // We should now have 3 invalid results.
    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEquals(3, count($results), 'Found 3 invalid quiz results');

    // And since we failed we should still have a valid 50% result.
    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertTrue(count($results) == 1, 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEquals(50, $quiz_result->get('score')->value, 'Quiz score was 50%');

    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => '0',
    ], (string) $this->t('Finish'));

    // We should now have 4 invalid results.
    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEquals(4, count($results), 'Found 4 invalid quiz results');

    // And we should have one valid 75% result.
    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertEquals(1, count($results), 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEquals(75, $quiz_result->get('score')->value, 'Quiz score was 75%');

    $config = \Drupal::service('config.factory')->getEditable('quiz.settings');

    // Set quiz_remove_invalid_quiz_record to the default value of a single day
    // and trigger a cron run. Since we haven't passed a day we should still
    // have 4 invalid results and one valid result with a score of 75%.
    $config->set('remove_invalid_quiz_record', 86400)->save();
    $this->cronRun();

    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEquals(4, count($results), 'Found 4 invalid quiz results');
    $results = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertEquals(1, count($results), 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEquals(75, $quiz_result->get('score')->value, 'Quiz score was 75%');

    // Set quiz_remove_invalid_quiz_record with a negative value to ensure
    // purging invalid results and run the cron itself. After this purge we
    // should only have one valid result left with a score of 75%.
    $config->set('remove_invalid_quiz_record', -86400)->save();
    $this->cronRun();

    $results = QuizResult::loadMultiple();
    $this->assertEquals(1, count($results), 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEquals(75, $quiz_result->get('score')->value, 'Quiz score was 75%');
    $this->assertEquals(0, $quiz_result->get('is_invalid')->value, 'Quiz score was valid');
  }

}
