<?php

namespace Drupal\Tests\tfa\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Plugin\TfaLoginInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaLoginContext;
use Drupal\tfa\TfaLoginContextFactory;
use Drupal\tfa\TfaPluginManager;
use Drupal\tfa\TfaUserAuth;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;

/**
 *
 * @covers  \Drupal\tfa\TfaUserAuth
 *
 * @group tfa
 *
 * cSpell:ignore abcxyz
 */
class TfaUserAuthTest extends UnitTestCase {

  /**
   * Mock of the user storage.
   *
   * @var \Drupal\user\UserStorageInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UserStorageInterface&MockObject $userStorageMock;

  /**
   * Mock of the Tfa plugin manager.
   *
   * @var \Drupal\tfa\TfaPluginManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaPluginManager&MockObject $tfaPluginManagerMock;

  /**
   * Tfa settings to be provided to configFactoryMock.
   *
   * @var array
   */
  protected array $tfaSettings;

  /**
   * Mock entity for the user that is attempting to login.
   *
   * @var \Drupal\user\UserInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UserInterface&MockObject $userMock;

  /**
   * Mock user data service.
   *
   * @var \Drupal\user\UserDataInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UserDataInterface&MockObject $userDataMock;

  /**
   * Mock of the inner user.auth service.
   *
   * @var \Drupal\user\UserAuthInterface&\PHPUnit\Framework\MockObject\MockObject
   */

  protected UserAuthInterface&MockObject $innerUserAuthMock;

  /**
   * Mock of the EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityTypeManagerInterface&MockObject $entityTypeManagerMock;

  /**
   * Mock of the MemoryCache service.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MemoryCacheInterface&MockObject $memoryCacheMock;

  /**
   * Mock validation plugin.
   *
   * @var \Drupal\tfa\Plugin\TfaValidationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaValidationInterface&MockObject $validationPluginMock;

  /**
   * Additional mock validation plugin.
   *
   * @var \Drupal\tfa\Plugin\TfaValidationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaValidationInterface&MockObject $alternateValidationPluginMock;

  /**
   * Mock login plugin.
   *
   * @var \Drupal\tfa\Plugin\TfaLoginInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaLoginInterface&MockObject $loginPluginMock;

  /**
   * TfaLoginContext mock.
   *
   * @var \Drupal\tfa\TfaLoginContext&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaLoginContext&MockObject $loginContextMock;

  /**
   * TfaLoginContextFactory mock.
   *
   * @var \Drupal\tfa\TfaLoginContextFactory&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaLoginContextFactory&MockObject $loginContextFactoryMock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup default mocked services. These can be overridden by
    // re-instantiating them as needed prior to calling ::getFixture().
    $this->innerUserAuthMock = $this->createMock(UserAuthInterface::class);
    $this->validationPluginMock = $this->createMock(TfaValidationInterface::class);
    $this->validationPluginMock->method('tokenLength')->willReturn(6);
    $this->alternateValidationPluginMock = $this->createMock(TfaValidationInterface::class);
    $this->alternateValidationPluginMock->method('tokenLength')->willReturn(6);

    $this->tfaPluginManagerMock = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManagerMock
      ->method('getValidationDefinitions')
      ->willReturn(
        [
          'always_fail_validation' => 'always_fail_validation',
          'create_exception' => 'create_exception',
          'foo' => 'foo',
          'baz' => 'baz',
          'foobar' => 'foobar',
        ]
      );
    $this->tfaPluginManagerMock->method('getLoginDefinitions')->willReturn(['bar' => 'bar']);
    $tfa_plugin_manager_callback = function ($plugin_id, $options) {
      self::assertSame(['uid' => '3'], $options);

      $alternate_validation_plugin_no_validate_request = $this->createMock(TfaValidationInterface::class);
      $alternate_validation_plugin_no_validate_request->method('tokenLength')->willReturn(6);

      $always_fail_validation = $this->createMock(TfaValidationInterface::class);
      $always_fail_validation->method('tokenLength')->willReturn(6);
      $always_fail_validation->method('validateRequest')->willReturn(FALSE);

      return match ($plugin_id) {
        'foo' => $this->validationPluginMock,
        'bar' => $this->loginPluginMock,
        'baz' => $alternate_validation_plugin_no_validate_request,
        'foobar' => $this->alternateValidationPluginMock,
        'always_fail_validation' => $always_fail_validation,
        default => throw new PluginException(),
      };
    };

    $this->tfaPluginManagerMock->method('createInstance')->willReturnCallback($tfa_plugin_manager_callback);
    $this->tfaSettings = [
      'enabled' => TRUE,
      'default_validation_plugin' => 'foo',
    ];

    $this->userMock = $this->createMock(UserInterface::class);
    $this->userMock->method('id')->willReturn('3');
    $this->userMock->method('getRoles')->willReturn(['other_tfa_role' => 'other_tfa_role']);
    $this->userMock->method('getAccountName')->willReturn('tfa_user');

    $this->userStorageMock = $this->createMock(UserStorageInterface::class);
    $this->userStorageMock->method('load')->with(3)->willReturn($this->userMock);
    $this->userStorageMock->method('loadByProperties')->with(['name' => 'tfa_user'])->willReturn([$this->userMock]);

    $this->userDataMock = $this->createMock(UserDataInterface::class);
    $user_data['status'] = 1;
    $user_data['data']['plugins'] = [
      'create_exception' => 'create_exception',
      'always_fail_validation' => 'always_fail_validation',
      'foo' => 'foo',
      'bar' => 'bar',
      'baz' => 'baz',
    ];
    $this->userDataMock->method('get')
      ->with('tfa', $this->identicalTo(3), 'tfa_user_settings')
      ->willReturn($user_data);

    $this->entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManagerMock->method('getStorage')->with('user')->willReturnReference($this->userStorageMock);

    $this->memoryCacheMock = $this->createMock(MemoryCacheInterface::class);

    $this->loginContextMock = $this->createMock(TfaLoginContext::class);
    $this->loginContextFactoryMock = $this->createMock(TfaLoginContextFactory::class);
    $this->loginContextFactoryMock->method('createContextFromUser')->with($this->userMock)->willReturnReference($this->loginContextMock);

    $time_mock = $this->createMock(TimeInterface::class);
    $time_mock->method('getRequestTime')->willReturn('160000000');
    $container = new ContainerBuilder();
    $container->set('datetime.time', $time_mock);
    \Drupal::setContainer($container);

  }

  /**
   * Get a test fixture.
   */
  public function getFixture(): TfaUserAuth {
    $config_factory_mock = $this->getConfigFactoryStub(['tfa.settings' => $this->tfaSettings]);
    return new TfaUserAuth($this->innerUserAuthMock, $this->loginContextFactoryMock, $this->memoryCacheMock, $this->tfaPluginManagerMock, $config_factory_mock, $this->userDataMock, $this->entityTypeManagerMock);
  }

  /**
   * Tests the authenticate() method handles too short a password.
   */
  public function testPasswordShorterOrEqualTokenLength(): void {
    $this->innerUserAuthMock->expects($this->never())->method('authenticate');
    $this->memoryCacheMock
      ->expects($this->never())
      ->method('set')
      ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    $this->validationPluginMock->method('ready')->willReturn(TRUE);
    $this->validationPluginMock->expects($this->never())->method('validateRequest');
    $this->loginContextMock->method('isReady')->willReturn(TRUE);

    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', '123456');
    $this->assertFalse($result, 'Reject when password is same length as token');
    $result = $tfa_user_auth->authenticate('tfa_user', '12345');
    $this->assertFalse($result, 'Reject when password is shorter than token');
  }

  /**
   * Tests the authenticate() method passes through on empty user.
   */
  public function testEmptyUser(): void {
    $this->innerUserAuthMock->method('authenticate')->willReturnOnConsecutiveCalls(3, FALSE);
    $this->memoryCacheMock
      ->expects($this->never())
      ->method('set')
      ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);

    $tfa_user_auth = $this->getFixture();
    $this->assertEquals(3, $tfa_user_auth->authenticate('', 'some_password'));
    $this->assertFalse($tfa_user_auth->authenticate('', 'some_password'));
  }

  /**
   * Tests the authenticate() method passes through on empty user.
   */
  public function testUserDoesNotExist(): void {
    $this->memoryCacheMock
      ->expects($this->never())
      ->method('set')
      ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);

    $this->userStorageMock = $this->createMock(UserStorageInterface::class);
    $this->userStorageMock->method('loadByProperties')->with(['name' => 'tfa_user'])->willReturn([]);

    $this->loginContextFactoryMock = $this->createMock(TfaLoginContextFactory::class);
    $this->loginContextFactoryMock->expects($this->never())->method('createContextFromUser');
    $this->innerUserAuthMock->expects($this->never())->method('authenticate');

    $tfa_user_auth = $this->getFixture();
    $this->assertFalse($tfa_user_auth->authenticate('tfa_user', 'some_password'));
  }

  /**
   * Test when login context has TFA Disabled.
   *
   * @dataProvider providerTfaDisabled
   */
  public function testTfaDisabled(int|false $inner_result): void {
    $this->loginContextMock->method('isTfaDisabled')->willReturn(TRUE);
    $this->innerUserAuthMock->expects($this->atMost(1))->method('authenticate')->willReturn($inner_result);

    if ($inner_result === FALSE) {
      $this->memoryCacheMock
        ->expects($this->never())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }
    else {
      $this->memoryCacheMock
        ->expects($this->once())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }

    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', 'short');
    $this->assertSame($inner_result, $result);

  }

  /**
   * DataProvider for testTfaDisabled()
   */
  public static function providerTfaDisabled(): \Generator {

    yield 'inner fail' => [
      FALSE,
    ];

    yield 'inner success' => [
      3,
    ];

  }

  /**
   * Test login skipped.
   *
   * @dataProvider providerLoginSkipped
   */
  public function testLoginSkipped(int|false $expected_result, bool $can_login_without_tfa, int|false $inner_result): void {

    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(FALSE);
    $this->loginContextMock->method('canLoginWithoutTfa')->willReturn($can_login_without_tfa);
    $this->innerUserAuthMock->expects($this->atMost(1))->method('authenticate')->willReturn($inner_result);

    if ($expected_result == FALSE) {
      $this->loginContextMock->expects($this->never())->method('hasSkipped');
      $this->memoryCacheMock
        ->expects($this->never())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }
    else {
      $this->loginContextMock->expects($this->once())->method('hasSkipped');
      $this->memoryCacheMock
        ->expects($this->once())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }

    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', 'short');
    $this->assertSame($expected_result, $result);
  }

  /**
   * DataProvider for the LoginSkipped() test.
   */
  public static function providerLoginSkipped(): \Generator {

    yield 'Skip Prohibited' => [
      FALSE,
      FALSE,
      3,
    ];

    yield 'Skip allowed, inner fail' => [
      FALSE,
      TRUE,
      FALSE,
    ];

    yield 'Skip allowed, inner success' => [
      3,
      TRUE,
      3,
    ];
  }

  /**
   * Test that pluginAllowsLogin is honored.
   *
   * @dataProvider providerLoginPluginAllows
   */
  public function testLoginPluginAllows(int|false $expected_result, int|false $inner_result): void {

    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(TRUE);
    $this->loginContextMock->method('pluginAllowsLogin')->willReturn(TRUE);
    $this->innerUserAuthMock->expects($this->atMost(1))->method('authenticate')->willReturn($inner_result);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');
    $this->loginContextMock->expects($this->never())->method('canLoginWithoutTfa');
    if ($expected_result === FALSE) {
      $this->memoryCacheMock
        ->expects($this->never())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }
    else {
      $this->memoryCacheMock
        ->expects($this->once())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }
    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', 'short');
    $this->assertSame($expected_result, $result);
  }

  /**
   * DataProvider for testLoginPluginAllows().
   */
  public static function providerLoginPluginAllows(): \Generator {
    yield 'Plugin allows Login, inner fail' => [
      FALSE,
      FALSE,
    ];

    yield 'Plugin allows Login, inner success' => [
      3,
      3,
    ];

  }

  /**
   * Test Default plugin authentication.
   *
   * @dataProvider providerValidatedPluginAuthentication
   */
  public function testValidatedPluginAuthentication(int|false $expected_result, string $default_plugin, int|string|false $inner_result): void {

    $this->tfaSettings = [
      'enabled' => TRUE,
      'default_validation_plugin' => $default_plugin,
    ];
    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(TRUE);
    $this->loginContextMock->method('pluginAllowsLogin')->willReturn(FALSE);
    $this->innerUserAuthMock->expects($this->atMost(1))->method('authenticate')->with('tfa_user', 'abcxyz')->willReturn($inner_result);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');
    $this->validationPluginMock->method('ready')->willReturn(TRUE);
    $this->validationPluginMock->method('validateRequest')->with('123456')->willReturn(TRUE);
    if ($expected_result === FALSE) {
      $this->memoryCacheMock
        ->expects($this->never())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }
    else {
      $this->memoryCacheMock
        ->expects($this->once())
        ->method('set')
        ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    }
    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', 'abcxyz123456');
    $this->assertSame($expected_result, $result);
  }

  /**
   * DataProvider for testValidatedPluginAuthentication().
   */
  public static function providerValidatedPluginAuthentication(): \Generator {

    yield 'Default plugin allows Login, inner fail' => [
      FALSE,
      'foo',
      FALSE,
    ];

    yield 'Default plugin allows Login, inner success' => [
      3,
      'foo',
      3,
    ];

    yield 'Default plugin allows Login, inner success as string' => [
      3,
      'foo',
      "3",
    ];

    yield 'Default plugin allows Login, inner fail uid 0 string' => [
      FALSE,
      'foo',
      "0",
    ];

    yield 'Default plugin creation exception' => [
      FALSE,
      'create_exception',
      3,
    ];

    yield 'Default plugin fail, alternate success, inner fail' => [
      FALSE,
      'always_fail_validation',
      FALSE,
    ];

    yield 'Default plugin fail, alternate success, inner success' => [
      3,
      'always_fail_validation',
      3,
    ];

  }

  /**
   * Ensure a Default Plugin exception causes a FALSE login.
   */
  public function testDefaultPluginException(): void {

    $this->tfaSettings = [
      'enabled' => TRUE,
      'default_validation_plugin' => 'create_exception',
    ];

    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(TRUE);
    $this->loginContextMock->method('pluginAllowsLogin')->willReturn(FALSE);
    $this->innerUserAuthMock->expects($this->atMost(1))->method('authenticate')->with('tfa_user', 'abcxyz')->willReturn(TRUE);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');
    $this->validationPluginMock->method('ready')->willReturn(TRUE);
    $this->validationPluginMock->method('validateRequest')->with('123456')->willReturn(TRUE);
    $this->memoryCacheMock
      ->expects($this->never())
      ->method('set')
      ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);
    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', 'abcxyz123456');
    $this->assertFalse($result);
  }

  /**
   * Test authentication bypass.
   *
   * @dataProvider providerBypass
   */
  public function testBypass(int|false $expected_result, InvokedCount $validate_request_constraint, InvokedCount $set_force_logout_constraint, array $callback_match, string $expected_password, int|false $inner_result, \stdClass|null $bypass_result): void {

    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(TRUE);
    $this->loginContextMock->method('pluginAllowsLogin')->willReturn(FALSE);
    $this->innerUserAuthMock->method('authenticate')->with('tfa_user', $expected_password)->willReturn($inner_result);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');
    $this->validationPluginMock->method('ready')->willReturn(TRUE);
    $this->validationPluginMock->expects($validate_request_constraint)->method('validateRequest')->with('123456')->willReturn(TRUE);

    $callback_list = [];
    $this->memoryCacheMock
      ->method('set')
      ->willReturnCallback(
        function (string $key, mixed $value, int $expire, array $tags) use (&$callback_list) {
          self::assertSame(Cache::PERMANENT, $expire);
          self::assertSame($tags, []);
          $callback_list[$key] = $value;
        }
      );

    $this->memoryCacheMock
      ->expects($this->once())
      ->method('get')
      ->with('bypass_tfa_auth_for_user', FALSE)
      ->willReturn($bypass_result);

    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', 'abcxyz123456');
    $this->assertSame($callback_match, $callback_list);
    $this->assertSame($expected_result, $result);
  }

  /**
   * DataProvider for testLoginPluginAllows().
   */
  public static function providerBypass(): \Generator {

    yield 'Bypass Not defined, inner fail' => [
      FALSE,
      new InvokedCount(1),
      new InvokedCount(0),
      [],
      'abcxyz',
      FALSE,
      NULL,
    ];

    yield 'Bypass not defined, inner success' => [
      3,
      new InvokedCount(1),
      new InvokedCount(0),
      ['tfa_complete' => 3],
      'abcxyz',
      3,
      NULL,
    ];

    yield 'Bypass wrong user, inner success' => [
      3,
      new InvokedCount(1),
      new InvokedCount(0),
      ['tfa_complete' => 3],
      'abcxyz',
      3,
      (object) ['data' => 'wrong_user'],
    ];

    yield 'Bypass wrong user, inner fail' => [
      FALSE,
      new InvokedCount(1),
      new InvokedCount(0),
      [],
      'abcxyz',
      FALSE,
      (object) ['data' => 'wrong_user'],
    ];

    yield 'Bypass correct user, inner success' => [
      3,
      new InvokedCount(0),
      new InvokedCount(1),
      ['tfa_user_auth_bypassed' => TRUE],
      'abcxyz123456',
      3,
      (object) ['data' => 'tfa_user'],
    ];

    yield 'Bypass correct user, inner fail' => [
      FALSE,
      new InvokedCount(0),
      new InvokedCount(1),
      ['tfa_user_auth_bypassed' => TRUE],
      'abcxyz123456',
      FALSE,
      (object) ['data' => 'tfa_user'],
    ];

  }

  /**
   * Tests Plugin returns no token length.
   */
  public function testTokenLengthZero(): void {
    $this->userDataMock = $this->createMock(UserDataInterface::class);
    $user_data['status'] = 1;
    $user_data['data']['plugins'] = [
      'foo' => 'foo',
    ];

    $this->userDataMock->method('get')
      ->with('tfa', $this->identicalTo(3), 'tfa_user_settings')
      ->willReturn($user_data);

    $this->innerUserAuthMock->expects($this->never())->method('authenticate');
    $this->loginContextMock->method('isReady')->willReturn(TRUE);
    $this->validationPluginMock = $this->createMock(TfaValidationInterface::class);
    $this->validationPluginMock->method('ready')->willReturn(TRUE);
    $this->validationPluginMock->expects($this->never())->method('validateRequest');
    $this->validationPluginMock->method('tokenLength')->willReturn(0);
    $this->memoryCacheMock
      ->expects($this->never())
      ->method('set')
      ->with('tfa_complete', $this->identicalTo(3), Cache::PERMANENT, []);

    $tfa_user_auth = $this->getFixture();
    $result = $tfa_user_auth->authenticate('tfa_user', 'abcxyz123456');
    $this->assertFalse($result);
  }

}
