<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * @coversDefaultClass \Drupal\ip2country\Drush\Commands\Ip2CountryDrushCommands
 *
 * @group ip2country
 */
class Ip2CountryDrushCommandsTest extends BrowserTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::state()->set('ip2country_populate_database_on_install', FALSE);
    \Drupal::service('module_installer')->install(['ip2country'], FALSE);
    \Drupal::service('module_installer')->install(['ip2country_test_fetch'], FALSE);
    $this->resetAll();
    $this->assertSame(5, \Drupal::service('ip2country.manager')->updateDatabase('all', TRUE));
  }

  /**
   * Tests the Drush ip2country:status command.
   */
  public function testStatusCommand(): void {
    // Checks the status of the database. In a test case, this will be loaded
    // during the test.
    $this->drush('ip2country:status');

    $update_time = \Drupal::service('state')->get('ip2country_last_update');
    $this->assertNotEmpty($update_time);
    $date = \Drupal::service('date.formatter')->format($update_time, 'ip2country_date');
    $time = \Drupal::service('date.formatter')->format($update_time, 'ip2country_time');
    $expected_output = "Database last updated on $date at $time from ALL server.";
    $this->assertEquals($expected_output, $this->getOutput());
  }

  /**
   * Tests the Drush ip2country:lookup command.
   */
  public function testLookupCommand(): void {
    // Looks up a synthetic allocation in a reserved documentation range.
    // Output defaults to table format.
    $this->drush('ip2country:lookup', ['192.0.2.32']);

    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $expected_output =
      "------------ --------------- -------------- \n" .
      "  IP address   Country         Country code  \n" .
      " ------------ --------------- -------------- \n" .
      "  192.0.2.32   United States   US            \n" .
      " ------------ --------------- --------------";
    // phpcs:enable Drupal.Strings.UnnecessaryStringConcat.Found
    $this->assertEquals($expected_output, $this->getOutput());

    // Looks up the same synthetic allocation.
    // Specify YAML format output.
    $this->drush('ip2country:lookup', ['192.0.2.32'], ['format' => 'yaml']);

    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $expected_output =
      "192.0.2.32:\n" .
      "  ip_address: 192.0.2.32\n" .
      "  name: 'United States'\n" .
      "  country_code_iso2: US";
    // phpcs:enable Drupal.Strings.UnnecessaryStringConcat.Found
    $this->assertEquals($expected_output, $this->getOutput());
  }

  /**
   * Tests the Drush ip2country:update command.
   */
  public function testUpdateCommand(): void {
    // Updates the database from 'all' the registries then checks the results.
    $this->drush('ip2country:update', [], ['registry' => 'all']);

    $rows = \Drupal::service('ip2country.manager')->getRowCount();
    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $expected_output =
      "Updating ... Completed.\n" .
      "Database updated from ALL server. Table contains $rows rows.";
    // phpcs:enable Drupal.Strings.UnnecessaryStringConcat.Found
    $this->assertEquals($expected_output, $this->getOutput());
  }

}
