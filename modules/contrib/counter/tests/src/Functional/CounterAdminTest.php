<?php

namespace Drupal\Tests\counter\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that Counter configuration UI functionality.
 *
 * @group counter
 */
class CounterAdminTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'counter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Verify the visitor-facility functionality works.
   */
  public function testSiteAfterInstall() {
    // Test if the home page is working after enabling Counter.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the counter configuration page works.
   */
  public function testCounterConfigurationPages() {
    // Create and log in a user with administer counter permission.
    $admin_user = $this->drupalCreateUser([
      'administer counter',
    ]);
    $this->drupalLogin($admin_user);

    // Test if the basic configuration page is working.
    $this->drupalGet('/admin/config/counter/basic');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm the expected fields are present.
    $this->assertSession()->fieldExists('counter_show_site_counter');
    $this->assertSession()->fieldExists('counter_show_unique_visitor');
    $this->assertSession()->fieldExists('counter_registered_user');
    $this->assertSession()->fieldExists('counter_unregistered_user');
    $this->assertSession()->fieldExists('counter_blocked_user');
    $this->assertSession()->fieldExists('counter_published_node');
    $this->assertSession()->fieldExists('counter_unpublished_node');
    $this->assertSession()->fieldExists('counter_show_server_ip');
    $this->assertSession()->fieldExists('counter_show_ip');
    $this->assertSession()->fieldExists('counter_show_counter_since');
    $this->assertSession()->fieldExists('counter_statistic_today');
    $this->assertSession()->fieldExists('counter_statistic_week');
    $this->assertSession()->fieldExists('counter_statistic_month');
    $this->assertSession()->fieldExists('counter_statistic_year');

    // Test if the advanced configuration page is working.
    $this->drupalGet('/admin/config/counter/advanced');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm the expected fields are present.
    $this->assertSession()->fieldExists('counter_skip_admin');
    $this->assertSession()->fieldExists('counter_refresh_on_cron');
    $this->assertSession()->fieldExists('counter_refresh_on_cron');

    // Test if the initial value configuration page is working.
    $this->drupalGet('/admin/config/counter/initial');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm the expected fields are present.
    $this->assertSession()->fieldExists('counter_initial_counter');
    $this->assertSession()->fieldExists('counter_initial_unique_visitor');
    $this->assertSession()->fieldExists('counter_initial_since');
  }

}
