<?php

namespace Drupal\Tests\tfa\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tfa\TfaUserAuth;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Test basic E2E operation of TFA Services.
 *
 * @group tfa
 *
 * @coversNothing
 */
class TfaServicesTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['tfa', 'user', 'system'];

  /**
   * Validate that tfa.user.auth decorates user.auth.
   *
   * It is security critical that the decorator is present.
   */
  public function testUserAuthDecorator(): void {
    // Validate tfa.user.auth decorates user.auth.
    $this->assertTrue($this->container->has('tfa.user.auth'));
    $tfa_user_auth = $this->container->get('tfa.user.auth');
    $this->assertInstanceOf(TfaUserAuth::class, $tfa_user_auth, 'tfa.user.auth service exists');
    $user_auth = $this->container->get('user.auth');
    $this->assertInstanceOf(TfaUserAuth::class, $user_auth, 'user.auth is decorated by tfa.user.auth');

    // Ensure the cache used is a memory cache as data should not persist
    // across multiple requests.
    // Due to KernelTestBase we can't use instanceOf() on the argument as
    // KernelTests always use the Memory backend.
    $definition = $this->container->getDefinition('tfa.user.auth');
    $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
    $this->assertSame('cache.tfa_memcache', (string) $definition->getArgument(2));

    // Setup for an E2E test of the decorator.
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $tfa_required_role = $this->createRole([]);
    $this->assertNotFalse($tfa_required_role);
    $user1 = $this->createUser();
    $this->assertNotFalse($user1);
    $this->config('tfa.settings')
      ->set('enabled', TRUE)
      ->set('required_roles', [$tfa_required_role])->save()
      ->set('default_validation_plugin', 'some_plugin')
      ->save();

    // Validate the E2E operation without mocks.
    $this->assertIsString($user1->__get('pass_raw'));
    $this->assertEquals($user1->id(), $user_auth->authenticate($user1->getAccountName(), $user1->__get('pass_raw')), 'authentication with tfa not required successful');
    $user1->addRole($tfa_required_role)->save();
    $this->assertFalse($user_auth->authenticate($user1->getAccountName(), $user1->__get('pass_raw')), 'authentication rejected when token not provided');
  }

}
