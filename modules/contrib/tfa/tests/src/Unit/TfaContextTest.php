<?php

namespace Drupal\Tests\tfa\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Plugin\TfaLoginInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaLoginContext;
use Drupal\tfa\TfaPluginManager;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\tfa\TfaLoginContext
 *
 * @group tfa
 */
class TfaContextTest extends UnitTestCase {

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Tfa plugin manager.
   *
   * @var \Drupal\tfa\TfaPluginManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $tfaPluginManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Entity for the user that is attempting to login.
   *
   * @var \Drupal\user\UserInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $user;

  /**
   * User data service.
   *
   * @var \Drupal\user\UserDataInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $userData;

  /**
   * The logger channel mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerChannelInterface&MockObject $loggerChannelMock;

  /**
   * The UrlGenerator mock.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UrlGeneratorInterface&MockObject $urlGeneratorMock;

  /**
   * The Messenger service mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MessengerInterface&MockObject $messengerMock;

  /**
   * Value that the validation plugin mock will return for ready().
   *
   * @var bool
   */
  protected bool $isValidationPluginReady;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup default mocked services. These can be overridden by
    // re-instantiating them as needed prior to calling ::getFixture().
    $this->isValidationPluginReady = FALSE;
    $validation_plugin = $this->createMock(TfaValidationInterface::class);
    $validation_plugin->method('ready')->willReturnReference($this->isValidationPluginReady);
    $login_plugin = $this->createMock(TfaLoginInterface::class);
    $this->tfaPluginManager = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn(['bar' => 'bar']);
    $this->tfaPluginManager->method('createInstance')->willReturnMap(
      [
        ['foo', ['uid' => "3"], $validation_plugin],
        ['bar', ['uid' => "3"], $login_plugin],
      ],
    );
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => []]);

    $this->user = $this->createMock(UserInterface::class);
    $this->user->method('id')->willReturn("3");
    $this->user->method('getAccountName')->willReturn('tfa_user');

    $this->userData = $this->createMock(UserDataInterface::class);
    $this->messengerMock = $this->createMock(MessengerInterface::class);
    $this->loggerChannelMock = $this->createMock(LoggerChannelInterface::class);
    $this->urlGeneratorMock = $this->createMock(UrlGeneratorInterface::class);
  }

  /**
   * Helper method to instantiate the test fixture.
   *
   * @return \Drupal\tfa\TfaLoginContext
   *   TFA context.
   */
  protected function getFixture(): TfaLoginContext {
    // Use simple anonymous class to add the TfaLoginContextTrait.
    return new TfaLoginContext(
      $this->user,
      $this->tfaPluginManager,
      $this->configFactory,
      $this->userData,
      $this->getStringTranslationStub(),
      $this->messengerMock,
      $this->loggerChannelMock,
      $this->urlGeneratorMock,
    );
  }

  /**
   * @covers ::getUser
   */
  public function testGetUser(): void {
    $fixture = $this->getFixture();
    $this->assertEquals(3, $fixture->getUser()->id());
  }

  /**
   * @covers ::isTfaDisabled
   */
  public function testIsTfaDisabled(): void {
    // Defaults to true with empty mocked services.
    $fixture = $this->getFixture();
    $this->assertTrue($fixture->isTfaDisabled());

    // User has setup TFA.
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn([
      'status' => 1,
      'saved' => FALSE,
      'data' => ['plugins' => ['foo']],
      'validation_skipped' => 1,
    ]);
    $settings = [
      'enabled' => TRUE,
      'default_validation_plugin' => 'foo',
    ];
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => $settings]);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->isTfaDisabled());

    // Not setup, no required roles matching the user.
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn([
      'status' => 0,
      'saved' => FALSE,
      'data' => ['plugins' => ['foo']],
      'validation_skipped' => 1,
    ]);
    $settings = [
      'enabled' => TRUE,
      'default_validation_plugin' => 'foo',
      'required_roles' => ['foo' => 'foo'],
    ];
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => $settings]);
    $this->user = $this->createMock(UserInterface::class);
    $this->user->method('id')->willReturn(3);
    $this->user->method('getRoles')->willReturn(['bar' => 'bar']);
    $fixture = $this->getFixture();
    $this->assertTrue($fixture->isTfaDisabled());

    // Setup, matching roles.
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn([
      'status' => 1,
      'saved' => FALSE,
      'data' => ['plugins' => ['foo']],
      'validation_skipped' => 1,
    ]);
    $this->user = $this->createMock(UserInterface::class);
    $this->user->method('id')->willReturn(3);
    $this->user->method('getRoles')->willReturn(['foo' => 'foo', 'bar' => 'bar']);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->isTfaDisabled());
  }

  /**
   * @covers ::isReady
   */
  public function testIsReady(): void {
    // Not ready.
    $settings = [
      'default_validation_plugin' => FALSE,
    ];
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => $settings]);

    $fixture = $this->getFixture();
    $this->assertFalse($fixture->isReady());

    // Is ready.
    $settings = [
      'default_validation_plugin' => 'foo',
      'allowed_validation_plugins' => ['foo' => 'foo'],
    ];
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => $settings]);
    $validator = $this->createMock(TfaValidationInterface::class);
    $validator->method('ready')->willReturn(TRUE);
    $this->tfaPluginManager = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManager->method('createInstance')->with('foo', ['uid' => 3])->willReturn($validator);
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn([]);
    $fixture = $this->getFixture();
    $this->assertTrue($fixture->isReady());

    // Plugin set, but not ready.
    $validator = $this->createMock(TfaValidationInterface::class);
    $validator->method('ready')->willReturn(FALSE);
    $this->tfaPluginManager = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManager->method('createInstance')->with('foo', ['uid' => 3])->willReturn($validator);
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn([]);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->isReady());

    // Exception creating plugin.
    $this->tfaPluginManager = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManager->method('createInstance')->with('foo', ['uid' => 3])->willReturn($this->throwException(new PluginException()));
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn([]);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->isReady());
  }

  /**
   * @covers ::remainingSkips
   */
  public function testRemainingSkips(): void {
    // No allowed skips.
    $settings = [
      'default_validation_plugin' => FALSE,
      'validation_skip' => 0,
    ];
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => $settings]);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->remainingSkips());

    // 3 allowed skips, user hasn't skipped any.
    $settings['validation_skip'] = 3;
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => $settings]);
    $fixture = $this->getFixture();
    $this->assertEquals(3, $fixture->remainingSkips());

    // 3 allowed skips, user has skipped 2.
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn([
      'status' => 1,
      'saved' => FALSE,
      'data' => ['plugins' => ['foo']],
      'validation_skipped' => 2,
    ]);
    $fixture = $this->getFixture();
    $this->assertEquals(1, $fixture->remainingSkips());

    // User has exceeded attempts, check for 0 return.
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn([
      'status' => 1,
      'saved' => FALSE,
      'data' => ['plugins' => ['foo']],
      'validation_skipped' => 9,
    ]);
    $fixture = $this->getFixture();
    $this->assertEquals(0, $fixture->remainingSkips());
  }

  /**
   * @covers ::hasSkipped
   */
  public function testHasSkipped(): void {
    $time_mock = $this->createMock(TimeInterface::class);
    $time_mock->method('getRequestTime')->willReturn('1600000000');
    $container = new ContainerBuilder();
    $container->set('datetime.time', $time_mock);
    \Drupal::setContainer($container);

    // Test Null..
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn(NULL);
    $this->userData
      ->expects($this->once())->method('set')
      ->with(
        'tfa',
        $this->identicalTo(3),
        'tfa_user_settings',
        [
          'saved' => '1600000000',
          'status' => 1,
          'data' => [
            'plugins' => [],
          ],
          'validation_skipped' => 1,
        ]
      );
    $fixture = $this->getFixture();
    $fixture->hasSkipped();

    // Test with 1 skip already present.
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn([
      'status' => 1,
      'saved' => FALSE,
      'data' => [],
      'validation_skipped' => 1,
    ]);
    $this->userData
      ->expects($this->once())->method('set')
      ->with(
        'tfa',
        $this->identicalTo(3),
        'tfa_user_settings',
        [
          'saved' => '1600000000',
          'status' => 1,
          'data' => [
            'plugins' => [],
          ],
          'validation_skipped' => 2,
        ]
      );
    $fixture = $this->getFixture();
    $fixture->hasSkipped();
  }

  /**
   * @covers ::pluginAllowsLogin
   */
  public function testPluginAllowsLogin(): void {
    // No enabled login plugins.
    $this->tfaPluginManager = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn([]);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->pluginAllowsLogin());

    // Plugin allows login.
    $validator = $this->createMock(TfaLoginInterface::class);
    $validator->method('loginAllowed')->willReturn(TRUE);
    $this->tfaPluginManager = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManager->method('createInstance')->with('foo', ['uid' => 3])->willReturn($validator);
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn(['foo' => 'foo']);
    $fixture = $this->getFixture();
    $this->assertTrue($fixture->pluginAllowsLogin());

    // Plugin does not allow login.
    $validator = $this->createMock(TfaLogiNInterface::class);
    $validator->method('loginAllowed')->willReturn(FALSE);
    $this->tfaPluginManager = $this->createMock(TfaPluginManager::class);
    $this->tfaPluginManager->method('createInstance')->with('foo', ['uid' => 3])->willReturn($validator);
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn(['foo' => 'foo']);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->pluginAllowsLogin());

    // Exception during login.
    $this->tfaPluginManager->method('createInstance')->with('foo', ['uid' => 3])->willReturn($this->throwException(new PluginException()));
    $this->tfaPluginManager->method('getLoginDefinitions')->willReturn(['foo' => 'foo']);
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->pluginAllowsLogin());
  }

  /**
   * @covers ::canLoginWithoutTfa
   *
   * @dataProvider providerCanLoginWithoutTfa
   */
  public function testCanLoginWithoutTfa(bool $expected_result, bool $has_permission_setup_own_tfa, int $skips_used, string $message_constraint, ?callable $setup = NULL): void {

    if ($setup !== NULL) {
      $setup($this);
    }

    $this->user->method('hasPermission')->with('setup own tfa')->willReturn($has_permission_setup_own_tfa);
    $this->urlGeneratorMock->method('generateFromRoute')->with('tfa.overview', ['user' => '3'])->willReturn('/user/3/security/tfa');
    $this->tfaPluginManager->method('getValidationDefinitions')->with(FALSE)->willReturn(['foo' => 'foo']);

    $settings = [
      'validation_skip' => 3,
      'help_text' => 'help text here',
    ];
    $this->configFactory = $this->getConfigFactoryStub(['tfa.settings' => $settings]);

    $this->userData = $this->createMock(UserDataInterface::class);
    $this->userData->method('get')->with('tfa', 3, 'tfa_user_settings')->willReturn([
      'status' => 1,
      'saved' => FALSE,
      'data' => ['plugins' => ['foo' => 'foo']],
      'validation_skipped' => $skips_used,
    ]);

    if ($skips_used >= 3) {
      $this->loggerChannelMock->expects($this->once())->method('notice')->with($this->stringContains('@name has no more remaining attempts'), ['@name' => 'tfa_user']);
    }

    $this->messengerMock->expects($this->once())->method('addError')->willReturn(
      function ($message) use ($message_constraint) {
        $this->assertStringContainsString((string) $message, $message_constraint);
      }
    );
    $fixture = $this->getFixture();

    $this->assertSame($expected_result, $fixture->canLoginWithoutTfa());

  }

  /**
   * Provider for canLoginWithoutTfa().
   */
  public static function providerCanLoginWithoutTfa() : \Generator {

    yield 'All skips remaining, can setup own TFA.' => [
      'expected_result' => TRUE,
      'has_permission_setup_own_tfa' => TRUE,
      'skips_used' => 0,
      'message_constraint' => 'You are required to <a href=""/user/3/security/tfa">setup two-factor authentication</a>. You have 2 attempts left. After this you will be unable to login.',
    ];

    yield 'User has configured a plugin' => [
      'expected_result' => FALSE,
      'has_permission_setup_own_tfa' => TRUE,
      'skips_used' => 0,
      'message_constraint' => 'help text here',
      'setup' => function (self $context) {
        $context->isValidationPluginReady = TRUE;
      },
    ];

    yield 'Can setup own tfa, 1 skip of 3 used' => [
      'expected_result' => TRUE,
      'has_permission_setup_own_tfa' => TRUE,
      'skips_used' => 1,
      'message_constraint' => 'You are required to <a href=""/user/3/security/tfa">setup two-factor authentication</a>. You have 1 attempt left. After this you will be unable to login.',
    ];

    yield 'Can setup own tfa, 2 skips of 3 used' => [
      'expected_result' => TRUE,
      'has_permission_setup_own_tfa' => TRUE,
      'skips_used' => 2,
      'message_constraint' => 'You have 0 attempts left. After this you will be unable to login.',
    ];

    yield 'Can not setup own tfa, 1 skip of 3 used' => [
      'expected_result' => TRUE,
      'has_permission_setup_own_tfa' => FALSE,
      'skips_used' => 1,
      'message_constraint' => 'You are required to setup two-factor authentication however your account does not have the necessary permissions.  Please contact an administrator. You have 1 attempts left.',
    ];

    yield 'Can not setup own tfa, 2 skips of 3 used' => [
      'expected_result' => TRUE,
      'has_permission_setup_own_tfa' => FALSE,
      'skips_used' => 2,
      'message_constraint' => 'You are required to setup two-factor authentication however your account does not have the necessary permissions.  Please contact an administrator. You have 0 attempts left.',
    ];

    yield 'No skips remaining 3 skips of 3 used' => [
      'expected_result' => FALSE,
      'has_permission_setup_own_tfa' => TRUE,
      'skips_used' => 3,
      'message_constraint' => 'help text here',
    ];
  }

}
