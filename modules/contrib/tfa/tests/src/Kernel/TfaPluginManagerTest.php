<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\tfa\TfaPluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test description.
 *
 * @group tfa
 *
 * @covers \Drupal\tfa\TfaPluginManager
 */
#[Group('tfa')]
#[CoversClass(TfaPluginManager::class)]
final class TfaPluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tfa', 'tfa_test_plugins', 'user'];

  /**
   * Test obtain plugin definitions.
   */
  public function testGetPluginDefinitions(): void {
    $this->installSchema('tfa', []);
    $this->installSchema('tfa_test_plugins', []);
    /** @var \Drupal\tfa\TfaPluginManager $tfa_pm */
    $tfa_pm = $this->container->get('plugin.manager.tfa');
    $this->assertArrayHasKey('tfa_test_plugins_validation_false', $tfa_pm->getValidationDefinitions(FALSE));
    $this->assertArrayNotHasKey('tfa_test_plugins_validation_false', $tfa_pm->getValidationDefinitions(TRUE));
    $this->config('tfa.settings')
      ->set('allowed_validation_plugins', ['tfa_test_plugins_validation_false' => 'tfa_test_plugins_validation_false'])
      ->save();
    $this->assertArrayHasKey('tfa_test_plugins_validation_false', $tfa_pm->getValidationDefinitions(TRUE));

    $this->assertArrayHasKey('tfa_test_login_plugin', $tfa_pm->getLoginDefinitions(FALSE));
    $this->assertArrayNotHasKey('tfa_test_login_plugin', $tfa_pm->getLoginDefinitions(TRUE));
    $this->config('tfa.settings')
      ->set('login_plugins', ['tfa_test_login_plugin' => 'tfa_test_login_plugin'])
      ->save();
    $this->assertArrayHasKey('tfa_test_login_plugin', $tfa_pm->getLoginDefinitions(TRUE));

    $this->assertArrayHasKey('tfa_test_plugins_send_false', $tfa_pm->getSendDefinitions(FALSE));
    $this->assertArrayNotHasKey('tfa_test_plugins_send_false', $tfa_pm->getSendDefinitions(TRUE));
    $this->config('tfa.settings')
      ->set('send_plugins', ['tfa_test_plugins_send_false' => 'tfa_test_plugins_send_false'])
      ->save();
    $this->assertArrayHasKey('tfa_test_plugins_send_false', $tfa_pm->getSendDefinitions(TRUE));

  }

}
