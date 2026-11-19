<?php

namespace Drupal\Tests\upgrade_status\Functional;

use Composer\InstalledVersions;
use Drupal\Tests\BrowserTestBase;

/**
 * Defines shared functions used by some of the functional tests.
 */
abstract class UpgradeStatusTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'upgrade_status',
    'upgrade_status_test_error',
    'upgrade_status_test_fatal',
    'upgrade_status_test_12_compatible',
    'upgrade_status_test_13_compatible',
    'upgrade_status_test_submodules_a',
    'upgrade_status_test_submodules_with_error',
    'upgrade_status_test_contrib_error',
    'upgrade_status_test_contrib_12_compatible',
    'upgrade_status_test_core_constants',
    'upgrade_status_test_dialog',
    'upgrade_status_test_removed_api',
    'upgrade_status_test_twig',
    'upgrade_status_test_library',
    'upgrade_status_test_library_exception',
    'upgrade_status_test_deprecated',
    'upgrade_status_test_deprecated_hook',
    'upgrade_status_test_hook_info',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container->get('theme_installer')->install(['upgrade_status_test_theme']);

    // These modules can't be installed on later major versions of core.
    if ($this->getDrupalCoreMajorVersion() < 11) {
      $this->container->get('module_installer')->install(['upgrade_status_test_10_compatible']);
    }
    if ($this->getDrupalCoreMajorVersion() < 12) {
      $this->container->get('module_installer')->install(['upgrade_status_test_11_compatible']);
    }
  }

  /**
   * Scans the test modules that the UI tests use.
   *
   * Each analyzer has its own kernel test, so only a few modules are scanned:
   * custom and contributed modules with problems, a compatible module, and a
   * module with a submodule.
   */
  protected function runFullScan() {
    $edit = [
      'scan[data][list][upgrade_status_test_error]' => TRUE,
      'scan[data][list][' . $this->getModuleWithoutNextMajorSupport() . ']' => TRUE,
      'scan[data][list][upgrade_status_test_13_compatible]' => TRUE,
      'scan[data][list][upgrade_status_test_submodules]' => TRUE,
      'collaborate[data][list][upgrade_status_test_contrib_error]' => TRUE,
    ];
    $this->drupalGet('admin/reports/upgrade-status');
    $this->submitForm($edit, 'Analyze selected');
  }

  /**
   * Returns a test module that needs an info file change for the next major.
   *
   * @return string
   *   The module name.
   */
  protected function getModuleWithoutNextMajorSupport(): string {
    return 'upgrade_status_test_' . $this->getDrupalCoreMajorVersion() . '_compatible';
  }

  /**
   * Returns current core's major version.
   *
   * @return int
   *   Version converted to int.
   */
  protected function getDrupalCoreMajorVersion(): int {
    return (int) \Drupal::VERSION;
  }

  /**
   * Stores a newer release of PHPStan, like the update check does.
   *
   * @return string|null
   *   The release, or NULL if a development version of PHPStan is installed.
   */
  protected function addPhpStanRelease(): ?string {
    $installed = (string) InstalledVersions::getPrettyVersion('phpstan/phpstan');
    if (str_contains($installed, 'dev')) {
      return NULL;
    }
    $release = (int) ltrim($installed, 'v') . '.999.0';
    $releases = ['phpstan/phpstan' => [['version' => $release, 'php' => NULL]]];
    \Drupal::keyValue('upgrade_status_tool_updates')->set('releases', $releases);
    return $release;
  }

}
