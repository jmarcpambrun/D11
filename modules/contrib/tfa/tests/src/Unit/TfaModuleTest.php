<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

require_once __DIR__ . '/../../../tfa.module';

/**
 * Test the 'tfa.module' file.
 *
 * @group tfa
 */
class TfaModuleTest extends UnitTestCase {

  /**
   * Test tfa_user_login() adds TFA Session.
   *
   * @covers ::tfa_user_login()
   *
   * @dataProvider providerTfaUserLoginSetTfaComplete
   */
  public function testTfaUserLoginSetTfaComplete(
    \stdClass|FALSE $memory_cache_return,
    int $set_call_count,
  ): void {

    $memory_cache_mock = $this->createMock(CacheBackendInterface::class);
    $memory_cache_mock->expects(self::once())
      ->method('get')
      ->with($this->identicalTo('tfa_complete'))
      ->willReturn($memory_cache_return);

    $session_mock = $this->createMock(SessionInterface::class);
    $session_mock
      ->expects($this->exactly($set_call_count))
      ->method('set')
      ->with($this->identicalTo('tfa_complete'), $this->identicalTo(10));

    $container = new ContainerBuilder();
    $container->set('session', $session_mock);
    $container->set('cache.tfa_memcache', $memory_cache_mock);
    \Drupal::setContainer($container);

    $account_mock = $this->createMock(UserInterface::class);
    $account_mock->method('id')->willReturn('10');
    tfa_user_login($account_mock);

  }

  /**
   * Provider for testTfaUserLoginSetTfaComplete()
   *
   * @return \Generator
   *   Test Data.
   */
  public static function providerTfaUserLoginSetTfaComplete(): \Generator {

    yield 'UserAuth UID Matches' => [
      (object) ['data' => 10],
      1,
    ];

    yield 'UserAuth UID mismatch' => [
      (object) ['data' => 20],
      0,
    ];

  }

  /**
   * Test tfa_user_logout() deletes session.
   *
   * @covers ::tfa_user_logout()
   */
  public function testTfaUserLogoutRemoveTfaComplete(): void {
    $session_mock = $this->createMock(SessionInterface::class);
    $container = new ContainerBuilder();
    $container->set('session', $session_mock);
    \Drupal::setContainer($container);

    $session_mock
      ->expects(self::once())
      ->method('remove')
      ->with($this->identicalTo('tfa_complete'));

    $account_mock = $this->createMock(AccountInterface::class);
    tfa_user_logout($account_mock);

  }

}
