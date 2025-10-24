<?php

namespace Drupal\Tests\webform_quiz\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\webform\Entity\Webform;

/**
 * Webform quiz radios test class.
 */
class WebformQuizWebformRadiosTest extends WebformQuizFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->rootUser);

    // Create a test webform entity.
    $webform = Webform::create([
      'id' => 'quiz',
      'title' => 'Quiz',
    ]);
    $webform->setThirdPartySetting('webform_quiz', 'settings', ['is_this_a_quiz' => 1]);

    try {
      $webform->save();
    }
    catch (EntityStorageException $e) {
      $this->fail('Error saving webform: ' . $e->getMessage());
    }
  }

  /**
   * Webform radios visibility test.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testWebformRadiosVisibility() {
    $this->drupalGet('admin/structure/webform/manage/quiz');
    $this->assertSession()->pageTextContains('Quiz');
  }

}
