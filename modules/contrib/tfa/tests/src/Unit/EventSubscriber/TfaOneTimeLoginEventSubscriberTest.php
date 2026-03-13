<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\EventSubscriber\TfaOneTimeLoginEventSubscriber;
use Drupal\tfa\TfaLoginContext;
use Drupal\tfa\TfaLoginContextFactory;
use Drupal\tfa\TfaLoginTrait;
use Drupal\tfa\TfaPluginManager;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

// We need user_pass_rehash in the EventSubscriber.
require_once __DIR__ . '/../../../../../../../core/modules/user/user.module';

/**
 *
 * @covers  \Drupal\tfa\EventSubscriber\TfaOneTimeLoginEventSubscriber
 *
 * @group tfa
 */
final class TfaOneTimeLoginEventSubscriberTest extends UnitTestCase {
  use TfaLoginTrait;

  const EXPECTED_LINK_EXPIRED_MESSAGE = 'You have tried to use a one-time login link that has expired. Please request a new one using the form below.';
  const EXPECTED_LINK_INVALID_MESSAGE = 'You have tried to use a one-time login link that has either been used or is no longer valid. Please request a new one using the form below.';

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
   * Mock of the config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&MockObject
   */
  protected ConfigFactoryInterface&MockObject $configFactoryMock;

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
   * Mock of the RouteMatch service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected RouteMatchInterface&MockObject $routeMatchMock;

  /**
   * Mock of the DateTime service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TimeInterface&MockObject $timeMock;

  /**
   * TFA Logger Channel Mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerChannelInterface|MockObject $loggerMock;

  /**
   * Mock of the PrivateTempStoreFactory service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory&\PHPUnit\Framework\MockObject\MockObject
   */
  protected PrivateTempStoreFactory&MockObject $privateTempStoreFactoryMock;

  /**
   * Mock of the Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MessengerInterface&MockObject $messengerMock;

  /**
   * Mock of the TfaLoginContext.
   *
   * @var \Drupal\tfa\TfaLoginContext&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaLoginContext&MockObject $loginContextMock;

  /**
   * Mock of the TFA Memory Cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $memoryCacheMock;

  /**
   * Mock of the Drupal Session Manager.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected SessionManagerInterface $sessionManagerMock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup default mocked services. These can be overridden by
    // re-instantiating them as needed prior to calling ::getSubscriber().
    $this->routeMatchMock = $this->createMock(RouteMatchInterface::class);
    $this->routeMatchMock->method('getRawParameter')->willReturnMap(
      [
        ['uid', '3'],
        ['timestamp', '159999900'],
        // cSpell:disable-next-line
        ['hash', '1ZxoxR4gvaNyDjrbs5vztjhNBvhhRv_NXNAiOwTaQIo'],
      ]
    );

    $this->configFactoryMock = $this->getConfigFactoryStub(
      [
        'user.settings' => ['password_reset_timeout' => 100],
      ]
    );

    $this->userMock = $this->createMock(UserInterface::class);
    $this->userMock->method('id')->willReturn("3");
    $this->userMock->method('getAccountName')->willReturn('tfa_user');
    $this->userMock->method('isActive')->willReturn(TRUE);
    $this->userMock->method('isAuthenticated')->willReturn(TRUE);
    $this->userMock->method('getDisplayName')->willReturn('Tfa Unit Test User');
    $this->userMock->method('getPassword')->willReturn('abc123');

    $this->userStorageMock = $this->createMock(UserStorageInterface::class);
    $this->userStorageMock->method('load')->with(3)->willReturnReference($this->userMock);

    $this->userStorageMock->method('loadByProperties')->with(['name' => 'tfa_user'])->willReturn([$this->userMock]);

    $this->userDataMock = $this->createMock(UserDataInterface::class);

    $this->privateTempStoreFactoryMock = $this->createMock(PrivateTempStoreFactory::class);

    $this->loggerMock = $this->createMock(LoggerChannelInterface::class);

    $this->messengerMock = $this->createMock(MessengerInterface::class);

    $this->loginContextMock = $this->createMock(TfaLoginContext::class);

    $this->timeMock = $this->createMock(TimeInterface::class);
    $this->timeMock->method('getRequestTime')->willReturn('160000000');

    $this->memoryCacheMock = $this->createMock(CacheBackendInterface::class);

    $this->sessionManagerMock = $this->createMock(SessionManagerInterface::class);

    new Settings(['hash_salt' => 'site_hash']);

    $private_key_mock = $this->createMock(PrivateKey::class);
    $private_key_mock->method('get')->willReturn('private_key');
    $this->setPrivateKey($private_key_mock);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Get the subscriber.
   *
   * @return \Drupal\tfa\EventSubscriber\TfaOneTimeLoginEventSubscriber
   *   The Event Subscriber.
   */
  private function getSubscriber(): TfaOneTimeLoginEventSubscriber {

    $this->routeMatchMock->expects($this->atLeastOnce())->method('getRouteName')->willReturn('user.reset.login');

    $entity_type_manager_mock = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager_mock->method('getStorage')->with('user')->willReturnReference($this->userStorageMock);

    $logger_factory_mock = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory_mock->method('get')->with('tfa')->willReturnReference($this->loggerMock);

    $login_context_factory_mock = $this->createMock(TfaLoginContextFactory::class);
    $login_context_factory_mock->method('createContextFromUser')->with($this->userMock)->willReturn($this->loginContextMock);

    $url_generator_mock = $this->createMock(UrlGeneratorInterface::class);
    $url_generator_mock->method('generateFromRoute')->willReturnCallback(function ($name, $parameters, $options) {
      switch ($name) {
        case 'user.pass':
          return '/user/password';

        case 'tfa.entry':
          $hash = $this->getLoginHash($this->userMock);
          $this->assertArrayHasKey('uid', $parameters);
          $this->assertEquals(3, $parameters['uid']);
          $this->assertArrayHasKey('hash', $parameters);
          $this->assertEquals($hash, $parameters['hash']);
          $this->assertArrayHasKey('query', $options);
          $this->assertArrayHasKey('pass-reset-token', $options['query']);
          return "/tfa/3/{$hash}?pass-reset-token={$options['query']['pass-reset-token']}";

        default:
          throw new \InvalidArgumentException('un-mocked route provided to urlGenerator');
      }
    });

    return new TfaOneTimeLoginEventSubscriber(
      $this->routeMatchMock,
      $login_context_factory_mock,
      $this->configFactoryMock,
      $entity_type_manager_mock,
      $this->timeMock,
      $logger_factory_mock,
      $this->privateTempStoreFactoryMock,
      $url_generator_mock,
      $this->messengerMock,
      $this->getStringTranslationStub(),
      $this->memoryCacheMock,
      $this->sessionManagerMock,
      $this->getPrivateKey(),
    );
  }

  /**
   * Get a processed RequestEvent.
   *
   * @return \Symfony\Component\HttpKernel\Event\RequestEvent
   *   The processed event.
   */
  private function getEventFixture(): RequestEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/example', 'GET');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    $handler = $this->getSubscriber();
    $handler->redirectOneTimeLogin($event);
    return $event;
  }

  /**
   * Test that the event handler is subscribed.
   */
  public function testSubscribedEventsHandling(): void {
    $this->routeMatchMock = $this->createMock(RouteMatchInterface::class);
    $events = TfaOneTimeLoginEventSubscriber::getSubscribedEvents();
    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    $this->assertSame(['redirectOneTimeLogin', 31], $events[KernelEvents::REQUEST][0]);
  }

  /**
   * Test when user not exist.
   */
  public function testUserNotExist(): void {
    $this->userStorageMock = $this->createMock(UserStorageInterface::class);
    $this->userStorageMock->method('load')->with(3)->willReturn(NULL);
    $this->userStorageMock->method('loadByProperties')->with(['name' => 'tfa_user'])->willReturn([]);

    $this->memoryCacheMock->expects($this->never())->method('set');

    $event = $this->getEventFixture();
    $this->assertFalse($event->isPropagationStopped());
    $this->assertNull($event->getResponse());
  }

  /**
   * Test the user is inactive.
   */
  public function testInactiveUser(): void {

    $this->userMock = $this->createMock(UserInterface::class);
    $this->userMock->method('id')->willReturn("3");
    $this->userMock->method('isActive')->willReturn(FALSE);
    $this->userMock->method('isAuthenticated')->willReturn(TRUE);

    $this->memoryCacheMock->expects($this->never())->method('set');

    $event = $this->getEventFixture();
    $this->assertFalse($event->isPropagationStopped());
    $this->assertNull($event->getResponse());
  }

  /**
   * Test the user not support authentication.
   */
  public function testUserThatNotSupportAuthentication(): void {

    $this->userMock = $this->createMock(UserInterface::class);
    $this->userMock->method('id')->willReturn("3");
    $this->userMock->method('isActive')->willReturn(TRUE);
    $this->userMock->method('isAuthenticated')->willReturn(FALSE);

    $this->memoryCacheMock->expects($this->never())->method('set');

    $event = $this->getEventFixture();
    $this->assertFalse($event->isPropagationStopped());
    $this->assertNull($event->getResponse());
  }

  /**
   * Test that TFA is disabled.
   */
  public function testTfaIsDisabled(): void {
    $this->loginContextMock->method('isTfaDisabled')->willReturn(TRUE);
    $this->memoryCacheMock->expects($this->never())->method('set');
    $event = $this->getEventFixture();
    $this->assertFalse($event->isPropagationStopped());
    $this->assertNull($event->getResponse());
  }

  /**
   * TFA Enabled, user has no ready tokens, skip allowed.
   */
  public function testNotReadySkipAllowed(): void {
    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(FALSE);
    $this->loginContextMock->method('canLoginWithoutTfa')->willReturn(TRUE);
    $this->loginContextMock->expects($this->once())->method('hasSkipped');

    $this->memoryCacheMock->expects($this->once())->method('set')->with('tfa_complete', 3);

    $event = $this->getEventFixture();
    $this->assertFalse($event->isPropagationStopped());
    $this->assertNull($event->getResponse());
  }

  /**
   * TFA Enabled, user has no ready tokens, skip prohibited.
   */
  public function testNotReadySkipProhibited(): void {
    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(FALSE);
    $this->loginContextMock->method('canLoginWithoutTfa')->willReturn(FALSE);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');

    $this->memoryCacheMock->expects($this->never())->method('set');

    $event = $this->getEventFixture();
    $this->assertTrue($event->isPropagationStopped());
    $response = $event->getResponse();
    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertEquals('/user/password', $response->getTargetUrl());
  }

  /**
   * TFA Enabled, validation ready, login plugin permits bypass.
   */
  public function testPluginAllowsLogin(): void {
    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(TRUE);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');
    $this->loginContextMock->method('pluginAllowsLogin')->willReturn(TRUE);
    $this->messengerMock->expects($this->once())->method('addStatus')->with($this->equalTo('You have logged in on a trusted system.'));

    $this->memoryCacheMock->expects($this->once())->method('set')->with('tfa_complete', 3);

    $event = $this->getEventFixture();
    $this->assertFalse($event->isPropagationStopped());
    $this->assertNull($event->getResponse());
  }

  /**
   * TFA Enabled, validation not ready, login plugin permits bypass.
   *
   * If the plugins aren't ready TFA should be denied by the ready check.
   */
  public function testPluginAllowsLoginValidationNotReady(): void {
    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(FALSE);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');
    $this->loginContextMock->expects($this->never())->method('pluginAllowsLogin');

    $this->memoryCacheMock->expects($this->never())->method('set');

    $event = $this->getEventFixture();
    $this->assertTrue($event->isPropagationStopped());
    $response = $event->getResponse();
    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertEquals('/user/password', $response->getTargetUrl());
  }

  /**
   * Test link validation logic.
   *
   * @dataProvider providerTestLinkValidationLogic
   */
  public function testLinkValidationLogic(bool $expect_redirect_to_user_pass_page, int $request_time, int $last_login_time, string $link_generated_time, string $link_hash, string $expected_messenger_error): void {
    $this->timeMock = $this->createMock(TimeInterface::class);
    $this->timeMock->method('getRequestTime')->willReturn($request_time);
    $this->userMock->method('getLastLoginTime')->willReturn($last_login_time);
    // Hash differs because of getLastLoginTime change.
    $this->routeMatchMock = $this->createMock(RouteMatchInterface::class);
    $this->routeMatchMock->method('getRawParameter')->willReturnMap(
      [
        ['uid', '3'],
        ['timestamp', $link_generated_time],
        ['hash', $link_hash],
      ]
    );
    $this->loginContextMock->method('isTfaDisabled')->willReturn(FALSE);
    $this->loginContextMock->method('isReady')->willReturn(TRUE);
    $this->loginContextMock->expects($this->never())->method('hasSkipped');
    $this->loginContextMock->method('pluginAllowsLogin')->willReturn(FALSE);

    if ($expect_redirect_to_user_pass_page) {
      $this->privateTempStoreFactoryMock->expects($this->never())->method('get')->with('tfa');
      $this->messengerMock
        ->expects($this->once())
        ->method('addError')
        ->with(
          $this->equalTo($expected_messenger_error)
        );
    }
    else {
      $this->loggerMock
        ->expects($this->once())
        ->method('notice')
        ->with(
          'User %name used one-time login link at time %timestamp.',
          [
            '%name' => 'Tfa Unit Test User',
            '%timestamp' => $request_time,
          ]
        );
      $temp_store_mock = $this->createMock(PrivateTempStore::class);
      $temp_store_mock->expects($this->once())->method('set')->with('tfa-entry-uid', "3");
      $this->privateTempStoreFactoryMock->method('get')->with('tfa')->willReturn($temp_store_mock);
      $this->sessionManagerMock->expects($this->once())->method('regenerate');
    }

    $event = $this->getEventFixture();

    if ($expect_redirect_to_user_pass_page) {
      $this->assertTrue($event->isPropagationStopped());
      $response = $event->getResponse();
      $this->assertInstanceOf(RedirectResponse::class, $response);
      $this->assertEquals('/user/password', $response->getTargetUrl());

    }
    else {
      $response = $event->getResponse();
      $this->assertInstanceOf(RedirectResponse::class, $response);
      $session_reset_token = $event->getRequest()->getSession()->get('pass_reset_3');
      $this->assertIsString($session_reset_token);
      $this->assertEquals('74', strlen($session_reset_token), 'Session Token should be 55 bytes of  URL safe base64 encoded data');
      $this->assertEquals("/tfa/3/{$this->getLoginHash($this->userMock)}?pass-reset-token={$session_reset_token}", $response->getTargetUrl());
    }

  }

  /**
   * Data provider for testLinkValidationLogic.
   *
   * @return \Generator
   *   Test Data.
   *     bool $expect_redirect_to_user_pass_page,
   *     int $request_time,
   *     int $last_login_time,
   *     string $link_generated_time,
   *     string $link_hash,
   *     string $expected_messenger_error
   */
  public static function providerTestLinkValidationLogic(): \Generator {

    yield 'Link valid, redirect to entry page' => [
      FALSE,
      159999900,
      150000000,
      '159999900',
      // cSpell:disable-next-line
      '1ZxoxR4gvaNyDjrbs5vztjhNBvhhRv_NXNAiOwTaQIo',
      '',
    ];

    yield 'Hash does not match' => [
      TRUE,
      159999900,
      150000000,
      '159999900',
      // cSpell:disable-next-line
      '1Z0siHsXUF9RXBc67C80oQfDOCinz3zwaNg-apYt_6b',
      self::EXPECTED_LINK_INVALID_MESSAGE,
    ];

    yield 'Link has expired' => [
      TRUE,
      160000001,
      150000000,
      '159999900',
      // cSpell:disable-next-line
      '1Z0siHsXUF9RXBc67C80oQfDOCinz3zwaNg-apYt_6c',
      self::EXPECTED_LINK_EXPIRED_MESSAGE,
    ];

    yield 'User has never logged in, accept expired link' => [
      FALSE,
      160000001,
      0,
      '159999900',
      // cSpell:disable-next-line
      'rotqdI_pLcbSVlxlb7O1e57YJKYPlRbfYJVYugb1KIw',
      '',
    ];

    yield 'User has logged in since link generated' => [
      TRUE,
      160000001,
      160000000,
      '159999900',
      // cSpell:disable-next-line
      '3x--V-x7UJ791TguHtnqnDjVbFgZw5GXw6e6dFXaGFQ',
      self::EXPECTED_LINK_EXPIRED_MESSAGE,
    ];

    yield 'Link generated at same time as last login' => [
      FALSE,
      160000000,
      159999900,
      '159999900',
      // cSpell:disable-next-line
      '6O5LWMdBYkgqTfhlo1jcfnaATuUp2fywUtH_0XewUig',
      '',
    ];

    yield 'Link Generated in future' => [
      TRUE,
      159999899,
      150000000,
      '159999900',
      // cSpell:disable-next-line
      '1Z0siHsXUF9RXBc67C80oQfDOCinz3zwaNg-apYt_6c',
      self::EXPECTED_LINK_INVALID_MESSAGE,
    ];
  }

}
