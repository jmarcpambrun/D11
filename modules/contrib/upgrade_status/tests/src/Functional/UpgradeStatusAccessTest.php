<?php

namespace Drupal\Tests\upgrade_status\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the accessibility of the deprecation dashboard.
 *
 * @group upgrade_status
 */
#[RunTestsInSeparateProcesses]
#[Group('upgrade_status')]
class UpgradeStatusAccessTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['upgrade_status'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests access without and with the permission.
   */
  public function testReportAccess(): void {
    $this->drupalGet(Url::fromRoute('upgrade_status.report'));
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer software updates']));
    $this->drupalGet(Url::fromRoute('upgrade_status.report'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
