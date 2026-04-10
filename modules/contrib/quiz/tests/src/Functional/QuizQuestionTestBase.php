<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @file
 * Unit tests for the quiz question Module.
 */

/**
 * Base test class for Quiz questions.
 */
abstract class QuizQuestionTestBase extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * Set up a question test case.
   *
   * @param array $admin_permissions
   *   Array of admin permissions to add.
   * @param array $user_permissions
   *   Array of user permissions to add.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp($admin_permissions = [], $user_permissions = []): void {
    $admin_permissions[] = "create {$this->getQuestionType()} quiz_question";
    $admin_permissions[] = "update any {$this->getQuestionType()} quiz_question";

    parent::setUp($admin_permissions, $user_permissions);
  }

  /**
   * Subclasses must provide the Question Type.
   */
  abstract public function getQuestionType();

  /**
   * Test the subclass's quiz question implementation.
   */
  public function testQuizQuestionImplementation() {
    $qq = \Drupal::service('plugin.manager.quiz.question')->getDefinitions();
    $this->assertTrue(isset($qq[$this->getQuestionType()]), $this->t('Check that the question implementation is correct.'));
  }

}
