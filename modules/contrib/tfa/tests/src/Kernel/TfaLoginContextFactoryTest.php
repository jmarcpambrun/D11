<?php

namespace Drupal\Tests\tfa\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;

/**
 * @covers \Drupal\tfa\TfaLoginContextFactory
 *
 * @group tfa
 */
class TfaLoginContextFactoryTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['tfa', 'user'];

  /**
   * Test the LoginContextFactory.
   */
  public function testLoginContextFactory(): void {
    $user_mock = $this->createMock(UserInterface::class);
    $this->assertTrue($this->container->has('tfa.login_context_factory'));
    /** @var \Drupal\tfa\TfaLoginContextFactory $factory */
    $factory = $this->container->get('tfa.login_context_factory');
    $login_context = $factory->createContextFromUser($user_mock);
    $this->assertEquals($user_mock, $login_context->getUser());
  }

}
