<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Compiler\TfaAuthDecoratorCompiler;
use Drupal\tfa\TfaServiceProvider;

/**
 * Tests the TfaServiceProvider class.
 *
 * @covers \Drupal\tfa\TfaServiceProvider
 *
 * @group tfa
 */
class TfaServiceProviderTest extends UnitTestCase {

  /**
   * Tests the register method.
   */
  public function testRegister(): void {
    $provider = new TfaServiceProvider();
    $container_mock = $this->createMock(ContainerBuilder::class);
    $container_mock
      ->expects($this->once())
      ->method('addCompilerPass')
      ->with($this->equalTo(new TfaAuthDecoratorCompiler()));

    $provider->register($container_mock);

  }

}
