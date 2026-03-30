<?php

namespace Drupal\Tests\book\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests access to the Book settings page.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookSettingsAccessTest extends BrowserTestBase {

  /**
   * The modules to enable.
   *
   * @var array
   */
  protected static $modules = ['book', 'node', 'user'];

  /**
   * The default theme used during testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Verifies access control for the Book settings page.
   */
  public function testBookSettingsAccess(): void {
    // Create a user with the 'administer book settings' permission.
    $user_with_permission = $this->drupalCreateUser([
      'administer book settings',
    ]);
    $this->drupalLogin($user_with_permission);
    $this->drupalGet('/admin/structure/book/settings');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogout();

    // Create and log in a user without the permission.
    $user_without_permission = $this->drupalCreateUser([]);
    $this->drupalLogin($user_without_permission);
    $this->drupalGet('/admin/structure/book/settings');
    $this->assertSession()->statusCodeEquals(403);
  }

}
