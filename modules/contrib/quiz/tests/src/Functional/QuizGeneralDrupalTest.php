<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Tests for general Drupal functionality.
 *
 * @group Quiz
 */
class QuizGeneralDrupalTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  public function setUp($admin_permissions = [], $user_permissions = []): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Basic page 404',
    ]);
    // Set the front page to the test page.
    $this->config('system.site')->set('page.404', '/node/1')->save();
  }

  /**
   * Test 404 functionality still works.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testDrupal404(): void {
    $this->drupalGet('/trigger_404');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('Basic page 404');

    // Unset config 404.
    $this->config('system.site')->set('page.404', '')->save();

    $this->drupalGet('/trigger_404');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('Page not found');
  }

}
