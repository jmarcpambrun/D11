<?php

namespace Drupal\Tests\counter\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that Counter block functionality.
 *
 * @group counter
 */
class CounterBlockTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'block',
    'counter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);
    $this->drupalPlaceBlock('counter_block');
  }

  /**
   * Test the block and it's content.
   */
  public function testCounterBlock() {
    $this->drupalGet('<front>');
    $this->assertSession()->elementExists('css', '#counter');
    $this->assertSession()->pageTextContains(t('Site Counter:'));
  }

}
