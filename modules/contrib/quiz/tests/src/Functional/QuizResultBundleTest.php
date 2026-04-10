<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\quiz\Entity\QuizResultType;

/**
 * Test quiz result bundle and fields behavior.
 *
 * @group Quiz
 */
class QuizResultBundleTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['quiz_truefalse'];

  /**
   * Test fieldable Quiz results.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testFieldableResults() {
    // Add a field to quiz result and make it required for starting.
    $field_storage = FieldStorageConfig::create([
      'id' => 'quiz_result.quiz_result_field_a',
      'field_name' => 'quiz_result_field_a',
      'entity_type' => 'quiz_result',
      'type' => 'string',
      'module' => 'core',
    ]);
    $field_storage->save();
    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'quiz_result',
      'label' => 'Result field A',
      'required' => TRUE,
      'field_name' => 'quiz_result_field_a',
      'entity_type' => 'quiz_result',
      'third_party_settings' =>
        [
          'quiz' => ['show_field' => TRUE],
        ],
    ]);
    $instance->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('quiz_result', 'quiz_result', 'default')
      ->setComponent('quiz_result_field_a', [
        'type' => 'text_textfield',
      ])
      ->save();

    $quizNodeA = $this->createQuiz();
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Q1Feedback',
    ]);
    $this->linkQuestionToQuiz($question1, $quizNodeA);
    $this->drupalLogin($this->user);

    // Check if field shows up and user is not yet started.
    $this->drupalGet("quiz/{$quizNodeA->id()}/take");
    $this->assertSession()->fieldExists('edit-quiz-result-field-a-0-value');

    // We haven't submitted the form, so we should not have a Quiz result yet.
    $quiz_result = $quizNodeA->getResumeableResult($this->user);
    $this->assertNull($quiz_result, 'Quiz result does not yet exist.');

    // Submit the form.
    $this->submitForm([], (string) $this->t('Start Quiz'));
    // Check that we hooked into Form API correctly.
    $this->assertSession()->pageTextContains('field is required');

    // SUbmit the form with data.
    $this->submitForm(['quiz_result_field_a[0][value]' => 'test 123'], (string) $this->t('Start Quiz'));
    $this->assertNotEmpty($quizNodeA->getResumeableResult($this->user), $this->t('Found quiz result.'));
    // Check the result exists now.
    $this->assertSession()->pageTextContains('Question 1');
  }

  /**
   * Test quiz result bundles.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testQuizResultBundles() {
    QuizResultType::create([
      'id' => 'type_a',
      'label' => $this->t('Bundle type A'),
    ])->save();

    QuizResultType::create([
      'id' => 'type_b',
      'label' => $this->t('Bundle type B'),
    ])->save();

    // Add a field to quiz result and make it required for starting.
    $field_storage_a = FieldStorageConfig::create([
      'id' => 'quiz_result.result_field_a',
      'field_name' => 'result_field_a',
      'entity_type' => 'quiz_result',
      'type' => 'string',
      'module' => 'core',
    ]);
    $field_storage_a->save();
    $instance_a = FieldConfig::create([
      'field_storage' => $field_storage_a,
      'bundle' => 'type_a',
      'label' => 'Result field A',
      'required' => TRUE,
      'field_name' => 'result_field_a',
      'entity_type' => 'quiz_result',
      'third_party_settings' =>
        [
          'quiz' => ['show_field' => TRUE],
        ],
    ]);
    $instance_a->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('quiz_result', 'type_a', 'default')
      ->setComponent('result_field_a', [
        'type' => 'text_textfield',
      ])
      ->save();

    // Add a field to quiz result and make it required for starting.
    $field_storage_b = FieldStorageConfig::create([
      'id' => 'quiz_result.result_field_b',
      'field_name' => 'result_field_b',
      'entity_type' => 'quiz_result',
      'type' => 'string',
      'module' => 'core',
    ]);
    $field_storage_b->save();
    $instance_b = FieldConfig::create([
      'field_storage' => $field_storage_b,
      'bundle' => 'type_b',
      'label' => 'Result field B',
      'required' => TRUE,
      'field_name' => 'result_field_b',
      'entity_type' => 'quiz_result',
      'third_party_settings' =>
        [
          'quiz' => ['show_field' => TRUE],
        ],
    ]);
    $instance_b->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('quiz_result', 'type_b', 'default')
      ->setComponent('result_field_b', [
        'type' => 'text_textfield',
      ])
      ->save();

    $quizNodeA = $this->createQuiz(['result_type' => 'type_a']);
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quizNodeA);

    $quizNodeB = $this->createQuiz(['result_type' => 'type_b']);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quizNodeB);

    $this->drupalLogin($this->user);

    // Check if field shows up and user is not yet started.
    $this->drupalGet("quiz/{$quizNodeA->id()}/take");
    $this->assertSession()->fieldExists('edit-result-field-a-0-value');
    $this->assertSession()->fieldNotExists('edit-result-field-b-0-value');
    $results = \Drupal::entityQuery('quiz_result')
      ->accessCheck(FALSE)
      ->condition('qid', $quizNodeA->id())
      ->condition('uid', $this->user->id())
      ->execute();
    $this->assertEmpty($results);

    $this->submitForm([], (string) $this->t('Start Quiz'));

    // Check that form API is working.
    $this->assertSession()->pageTextContains('field is required');
    $this->submitForm(['result_field_a[0][value]' => 'test 123'], (string) $this->t('Start Quiz'));

    // Check that a different field is on quiz B.
    $this->drupalGet("quiz/{$quizNodeB->id()}/take");
    $this->assertSession()->fieldExists('edit-result-field-b-0-value');
    $this->assertSession()->fieldNotExists('edit-result-field-a-0-value');

    // Mark field B to not show on result.
    $instance_b->setThirdPartySetting('quiz', 'show_field', FALSE);
    $instance_b->save();
    $this->drupalGet("quiz/{$quizNodeB->id()}/take");
    $this->assertSession()->fieldNotExists('edit-result-field-a-0-value');
    $this->assertSession()->fieldNotExists('edit-result-field-b-0-value');
  }

}
