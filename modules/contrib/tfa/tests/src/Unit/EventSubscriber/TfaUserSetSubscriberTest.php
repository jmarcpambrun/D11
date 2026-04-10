<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Session\AccountEvents;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSetEvent;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\EventSubscriber\TfaUserSetSubscriber;
use Drupal\tfa\Exceptions\TfaAccessDenied;
use Drupal\tfa\TfaLoginContext;
use Drupal\tfa\TfaLoginContextFactory;
use Drupal\user\Entity\User;
use Drupal\user\UserStorage;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 *
 * @covers \Drupal\tfa\EventSubscriber\TfaUserSetSubscriber
 *
 * @group tfa
 */
class TfaUserSetSubscriberTest extends UnitTestCase {

  /**
   * TFA Logger Channel mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerChannelInterface&MockObject $loggerMock;

  /**
   * The Session mock.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected SessionInterface&MockObject $sessionMock;

  /**
   * The Memory Cache mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $memoryCacheMock;

  /**
   * The User Account mock.
   *
   * @var \Drupal\Core\Session\AccountInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AccountInterface&MockObject $accountMock;

  /**
   * Mock of the TFA Login Context.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\tfa\TfaLoginContext
   */
  protected TfaLoginContext&MockObject $loginContextMock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sessionMock = $this->createMock(SessionInterface::class);
    $this->loggerMock = $this->createMock(LoggerChannelInterface::class);
    $this->memoryCacheMock = $this->createMock(CacheBackendInterface::class);
    $this->accountMock = $this->createMock(AccountInterface::class);
    $this->accountMock->method('id')->willReturn('10');
    $this->loginContextMock = $this->createMock(TfaLoginContext::class);

  }

  /**
   * Get the subscriber.
   *
   * @return \Drupal\tfa\EventSubscriber\TfaUserSetSubscriber
   *   The Event Subscriber.
   */
  private function getFixture(): TfaUserSetSubscriber {
    $config_factory = $this->getConfigFactoryStub(
      [
        'tfa.settings' => [
          'help_text' => 'help text',
        ],
      ]
    );

    $bare_render = $this->createMock(BareHtmlPageRendererInterface::class);
    $bare_render->method('renderBarePage')
      ->with(
        self::anything(),
        $this->identicalTo('Unexpected Access Fault'),
        $this->identicalTo('maintenance_page')
      )
      ->willReturnCallback(
        function (array $markup, string $title, string $theme) {
          $message = 'An unexpected fault occurred with your account authentication. help text';
          self::assertEquals(
            $markup,
            [
              '#cache' => [
                'max-age' => 0,
              ],
              '#markup' => $message,
            ]
          );

          return new HtmlResponse($message);
        }
      );

    $login_context_factory_mock = $this->createMock(TfaLoginContextFactory::class);
    $login_context_factory_mock->method('createContextFromUser')->willReturnReference($this->loginContextMock);

    $user_mock = $this->createMock(User::class);
    $user_mock->method('id')->willReturn($this->accountMock->id());

    $user_storage_mock = $this->createMock(UserStorage::class);
    $user_storage_mock->method('load')->willReturnCallback(
      function (int|string $id) use ($user_mock) {
        switch ($id) {
          case 10:
            return $user_mock;

          default:
            return NULL;
        }
      }
    );
    $entity_type_manager_mock = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager_mock->method('getStorage')->with('user')->willReturn($user_storage_mock);

    return new TfaUserSetSubscriber(
      $this->sessionMock,
      $this->loggerMock,
      $config_factory,
      $this->memoryCacheMock,
      $this->getStringTranslationStub(),
      $bare_render,
      $login_context_factory_mock,
      $entity_type_manager_mock,
    );
  }

  /**
   * Test that the event handler is subscribed.
   */
  public function testSubscribedEventsHandling(): void {
    $events = TfaUserSetSubscriber::getSubscribedEvents();
    $this->assertArrayHasKey(AccountEvents::SET_USER, $events);
    $this->assertIsArray($events[AccountEvents::SET_USER]);
    $this->assertNotFalse(array_search(['rejectUserIfTfaBypassed', 50], $events[AccountEvents::SET_USER], TRUE));
    $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
    $this->assertIsArray($events[KernelEvents::EXCEPTION]);
    $this->assertNotFalse(array_search(['onException', 100], $events[KernelEvents::EXCEPTION], TRUE));
  }

  /**
   * Test processing AccountEvents::SET_USER events.
   *
   * @dataProvider providerRejectUserIfTfaBypassed
   */
  public function testRejectUserIfTfaBypassed(
    \stdClass|false $bypass_result,
    \stdClass|false $switcher_result,
    \stdClass|false $user_auth_complete_result,
    int|string|NULL $session_complete_result,
    bool $expect_exception,
    ?callable $setup = NULL,
  ): void {

    $this->memoryCacheMock
      ->method('get')
      ->willReturnMap(
        [
          ['tfa_auth_method_bypass_approved', FALSE, $bypass_result],
          ['tfa_account_switcher_bypass', FALSE, $switcher_result],
          ['tfa_complete', FALSE, $user_auth_complete_result],
        ]
      );

    switch ($session_complete_result) {
      case NULL:
        $this->sessionMock
          ->method('has')
          ->with($this->identicalTo('tfa_complete'))
          ->willReturn(FALSE);
        break;

      default:
        $this->sessionMock
          ->method('has')
          ->with($this->identicalTo('tfa_complete'))
          ->willReturn(TRUE);
        $this->sessionMock
          ->method('get')
          ->with($this->identicalTo('tfa_complete'))
          ->willReturn($session_complete_result);
    }

    if ($setup !== NULL) {
      $setup($this);
    }

    $fixture = $this->getFixture();
    $event = new AccountSetEvent($this->accountMock);

    if ($expect_exception) {
      $this->expectException(TfaAccessDenied::class);
      $this->expectExceptionMessage('Request does not have a valid TFA verification.');
      $this->sessionMock->expects($this->once())->method('invalidate');
    }
    $fixture->rejectUserIfTfaBypassed($event);
    if ($expect_exception) {
      $this->fail('Expected exception not thrown');
    }
    $this->sessionMock->expects($this->never())->method('invalidate');

  }

  /**
   * Generator for testRejectUserIfTfaBypassed()
   *
   * @return \Generator
   *   Test Data.
   */
  public static function providerRejectUserIfTfaBypassed(): \Generator {

    yield 'Request failed to authenticate' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Allow anonymous users' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => FALSE,
      'setup' => function (self $context) {
        $context->accountMock = $context->createMock(AccountInterface::class);
        $context->accountMock
          ->method('isAnonymous')
          ->willReturn(TRUE);
      },
    ];

    yield 'Exempt auth method used' => [
      'bypass_result' => (object) ['data' => 10],
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => FALSE,
      'setup' => function (self $context) {
        $context->memoryCacheMock
          ->expects($context->once())
          ->method('delete')
          ->with(self::identicalTo('tfa_auth_method_bypass_approved'));
      },
    ];

    yield 'Exempt auth method UID does not match' => [
      'bypass_result' => (object) ['data' => 20],
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Exempt auth method UID does is not int' => [
      'bypass_result' => (object) ['data' => '10'],
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Account Switcher allowed' => [
      'bypass_result' => FALSE,
      'switcher_result' => (object) ['data' => 10],
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => FALSE,
      'setup' => function (self $context) {
        $context->memoryCacheMock
          ->expects($context->once())
          ->method('delete')
          ->with(self::identicalTo('tfa_account_switcher_bypass'));
      },
    ];

    yield 'Account Switcher uid mismatch' => [
      'bypass_result' => FALSE,
      'switcher_result' => (object) ['data' => 20],
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Account Switcher bypass is not int' => [
      'bypass_result' => FALSE,
      'switcher_result' => (object) ['data' => '10'],
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Authenticated session matches user' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => 10,
      'expect_exception' => FALSE,
      'setup' => NULL,
    ];

    yield 'Authenticated session does not match user' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => 20,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Authenticated session is not integer' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => '10',
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Authenticated this request matches user' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => (object) ['data' => 10],
      'session_complete_result' => NULL,
      'expect_exception' => FALSE,
      'setup' => NULL,
    ];

    yield 'Authenticated this request does not match user' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => (object) ['data' => 20],
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'Authenticated this request is not integer' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => (object) ['data' => '10'],
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => NULL,
    ];

    yield 'TFA is disabled, allow' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => FALSE,
      'setup' => function (self $context) {
        $context->loginContextMock
          ->method('isTfaDisabled')
          ->willReturn(TRUE);
        $context->memoryCacheMock
          ->expects($context->once())
          ->method('set')
          ->with(self::identicalTo('tfa_complete'), self::identicalTo(10));
      },
    ];

    yield 'User default plugin is ready' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => function (self $context) {
        $context->loginContextMock
          ->method('isTfaDisabled')
          ->willReturn(FALSE);
        $context->loginContextMock
          ->method('isReady')
          ->willReturn(TRUE);
      },
    ];

    yield 'Default plugin not ready, Can not login without TFA' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => TRUE,
      'setup' => function (self $context) {
        $context->loginContextMock
          ->method('isTfaDisabled')
          ->willReturn(FALSE);
        $context->loginContextMock
          ->method('isReady')
          ->willReturn(FALSE);
        $context->loginContextMock
          ->method('canLoginWithoutTfa')
          ->willReturn(FALSE);
      },
    ];

    yield 'Default plugin not ready, can login without TFA' => [
      'bypass_result' => FALSE,
      'switcher_result' => FALSE,
      'user_auth_complete_result' => FALSE,
      'session_complete_result' => NULL,
      'expect_exception' => FALSE,
      'setup' => function (self $context) {
        $context->loginContextMock
          ->method('isTfaDisabled')
          ->willReturn(FALSE);
        $context->loginContextMock
          ->method('isReady')
          ->willReturn(FALSE);
        $context->loginContextMock
          ->method('canLoginWithoutTfa')
          ->willReturn(TRUE);
        $context->loginContextMock
          ->expects($context->once())
          ->method('hasSkipped');
        $context->memoryCacheMock
          ->expects($context->once())
          ->method('set')
          ->with(self::identicalTo('tfa_complete'), self::identicalTo(10));
      },
    ];

  }

  /**
   * Tests the Exception event handler for TfaAccessDenied()
   *
   * @dataProvider providerTestOnException
   */
  public function testOnException(\Throwable $exception, callable $test): void {
    $fixture = $this->getFixture();
    $kernel_mock = $this->createMock(HttpKernelInterface::class);
    $request_mock = $this->createMock(Request::class);

    $event = new ExceptionEvent($kernel_mock, $request_mock, HttpKernelInterface::MAIN_REQUEST, $exception);
    $fixture->onException($event);
    $test($event);
  }

  /**
   * Generator for testOnException()
   *
   * @return \Generator
   *   Test Data.
   */
  public static function providerTestOnException(): \Generator {
    yield 'TfaAccessDenied captured' => [
      new TfaAccessDenied('Request does not have a valid TFA verification.'),
      function (ExceptionEvent $event) {
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('An unexpected fault occurred with your account authentication. help text', $response->getContent());
      },
    ];

    yield 'Does not capture non TFA exception' => [
      new AccessDeniedHttpException(),
      function (ExceptionEvent $event) {
        $response = $event->getResponse();
        self::assertNull($response);
      },
    ];

  }

}
