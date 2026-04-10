<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Form\TfaLoginFormHelper;
use Drupal\tfa\TfaLoginContext;
use Drupal\tfa\TfaLoginContextFactory;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Test the TfaLoginFormHelper class.
 *
 * @covers \Drupal\tfa\Form\TfaLoginFormHelper
 *
 * @group tfa
 */
class TfaLoginFormHelperTest extends UnitTestCase {

  /**
   * The Tfa Login Context Mock.
   */
  protected TfaLoginContext&MockObject $loginContextMock;

  /**
   * The Cache Backend Mock.
   */
  protected CacheBackendInterface&MockObject $memoryCacheMock;

  /**
   * The Session service mock.
   */
  protected SessionInterface&MockObject $sessionServiceMock;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->loginContextMock = $this->createMock(TfaLoginContext::class);
    $this->memoryCacheMock = $this->createMock(CacheBackendInterface::class);
    $this->sessionServiceMock = $this->createMock(SessionInterface::class);
  }

  /**
   * Helper method to instantiate the test fixture.
   */
  public function getFixture(): TfaLoginFormHelper {

    $request_stack_mock = $this->createMock(RequestStack::class);
    $request_stack_mock->method('getCurrentRequest')->willReturn(new Request());
    $messenger_mock = $this->createMock(MessengerInterface::class);

    $logger_factory_mock = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory_mock
      ->method('get')
      ->willReturn($this->createMock(LoggerChannelInterface::class));

    $global_config = [
      'tfa.settings' => [
        'users_without_tfa_redirect' => FALSE,
      ],
    ];

    $user_mock = $this->createMock(UserInterface::class);
    $user_mock->method('id')->willReturn('3');
    $user_storage_mock = $this->createMock(UserStorageInterface::class);
    $user_storage_mock->method('load')->with($this->identicalTo(3))->willReturn($user_mock);
    $entity_type_manager_mock = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager_mock->method('getStorage')->with('user')->willReturn($user_storage_mock);
    // Our services.
    $destination_mock = $this->createMock(RedirectDestinationInterface::class);
    $login_context_factory = $this->createMock(TfaLoginContextFactory::class);
    $login_context_factory->method('createContextFromUser')->willReturn($this->loginContextMock);

    $temp_store_mock = $this->createMock(PrivateTempStore::class);
    $temp_store_factory_mock = $this->createMock(PrivateTempStoreFactory::class);
    $temp_store_factory_mock->method('get')->with('tfa')->willReturn($temp_store_mock);

    $this->loginContextMock->method('getUser')->willReturn($user_mock);

    $private_key_mock = $this->createMock(PrivateKey::class);
    $private_key_mock->method('get')->willReturn('private_key');

    new Settings(['hash_salt' => 'site_hash']);

    return new TfaLoginFormHelper(
      $this->getConfigFactoryStub($global_config),
      $entity_type_manager_mock,
      $login_context_factory,
      $this->memoryCacheMock,
      $this->getStringTranslationStub(),
      $messenger_mock,
      $request_stack_mock,
      $temp_store_factory_mock,
      $private_key_mock,
      $this->sessionServiceMock,
      $destination_mock,
    );
  }

  /**
   * Ensures that the TFA Complete flag is set.
   *
   * @param bool $tfa_entry_required
   *   Will user be sent to TFA Entry form.
   * @param array $login_context_values
   *   Array of method/value returns for loginContextMock.
   *
   * @dataProvider providerTfaLoginContextScenarios
   */
  public function testSetCompleteFlag(bool $tfa_entry_required, array $login_context_values): void {

    $expected_memcache_calls = $this->once();

    if ($tfa_entry_required) {
      $expected_memcache_calls = $this->never();
    }

    $this->memoryCacheMock
      ->method('get')
      ->with('tfa_complete')
      ->willReturn(FALSE);

    $this->memoryCacheMock
      ->expects($expected_memcache_calls)
      ->method('set')
      ->with(
        self::identicalTo('tfa_complete'),
        self::identicalTo(3),
        Cache::PERMANENT,
        [],
      );

    foreach ($login_context_values as $key => $value) {
      $this->loginContextMock->method($key)->willReturn($value);
    }

    $fixture = $this->getFixture();
    $form_state = new FormState();
    $form = [];
    $form_state->set('uid', 3);
    $fixture->tfaValidateSubmit($form, $form_state);
  }

  /**
   * Validate protected against session fixation attack against entry form.
   *
   * @param bool $tfa_entry_required
   *   Will user be sent to TFA Entry form.
   * @param array $login_context_values
   *   Array of method/value returns for loginContextMock.
   *
   * @dataProvider providerTfaLoginContextScenarios
   */
  public function testSessionMigrated(bool $tfa_entry_required, array $login_context_values): void {

    $expected_call_count = $this->never();

    if ($tfa_entry_required) {
      $expected_call_count = $this->once();
    }

    $this->memoryCacheMock
      ->method('get')
      ->with('tfa_complete')
      ->willReturn(FALSE);

    $this->sessionServiceMock
      ->expects($expected_call_count)
      ->method('migrate');

    foreach ($login_context_values as $key => $value) {
      $this->loginContextMock->method($key)->willReturn($value);
    }

    $fixture = $this->getFixture();
    $form_state = new FormState();
    $form = [];
    $form_state->set('uid', 3);
    $fixture->tfaValidateSubmit($form, $form_state);
  }

  /**
   * Provider for testSessionMigrated()
   *
   * Validate that session is migrated when users are redirected to entry form.
   */
  public static function providerTfaLoginContextScenarios(): \Generator {
    yield 'TFA disabled' => [
      FALSE,
      [
        'isTfaDisabled' => TRUE,
        'isReady' => FALSE,
        'canLoginWithoutTfa' => FALSE,
        'pluginAllowsLogin' => FALSE,
      ],
    ];

    yield 'Can login-in without tfa' => [
      FALSE,
      [
        'isTfaDisabled' => FALSE,
        'isReady' => FALSE,
        'canLoginWithoutTfa' => TRUE,
        'pluginAllowsLogin' => FALSE,
      ],
    ];

    yield 'TFA Required, Login plugin allowed' => [
      FALSE,
      [
        'isTfaDisabled' => FALSE,
        'isReady' => TRUE,
        'canLoginWithoutTfa' => FALSE,
        'pluginAllowsLogin' => TRUE,
      ],
    ];

    yield 'TFA Required, Redirected to entry form' => [
      TRUE,
      [
        'isTfaDisabled' => FALSE,
        'isReady' => TRUE,
        'canLoginWithoutTfa' => FALSE,
        'pluginAllowsLogin' => FALSE,
      ],
    ];
  }

  /**
   * Validate early return when tfa_complete set for user.
   */
  public function testValidationAlreadyComplete(): void {

    // First run will match user, second run will continue due to UID
    // mismatch.
    $this->memoryCacheMock
      ->method('get')
      ->with('tfa_complete')
      ->willReturnOnConsecutiveCalls((object) ['data' => 3], (object) ['data' => 4]);

    // Use login context to detect if early return occurred.
    // Only a single run should reach a context check.
    $this->loginContextMock
      ->expects(self::once())
      ->method('isTfaDisabled')
      ->willReturn(TRUE);

    $fixture = $this->getFixture();
    $form_state = new FormState();
    $form = [];
    $form_state->set('uid', 3);
    $fixture->tfaValidateSubmit($form, $form_state);

    $form_state = new FormState();
    $form = [];
    $form_state->set('uid', 3);
    $fixture->tfaValidateSubmit($form, $form_state);
  }

}
