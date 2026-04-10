<?php

namespace Drupal\Tests\tfa\Functional\UpdateTests;

use Drupal\Core\Site\Settings;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Base class for functional E2E update hook tests.
 */
abstract class UpdateTestBase extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // We use the encrypt_test module.
    $settings = Settings::getAll();
    $settings['extension_discovery_scan_tests'] = TRUE;
    new Settings($settings);
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/tfa_base_setup.php',
    ];
  }

}
