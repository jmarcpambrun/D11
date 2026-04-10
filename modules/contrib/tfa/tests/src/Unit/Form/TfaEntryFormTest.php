<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Form\EntryForm;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaPluginManager;
use Drupal\user\UserDataInterface;
use Drupal\user\UserFloodControlInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test the EntryForm class.
 *
 * @covers \Drupal\tfa\Form\EntryForm
 *
 * @group tfa
 */
class TfaEntryFormTest extends UnitTestCase {

  /**
   * The Memory Cache Backend Mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $memoryCacheMock;

  /**
   * The flood control mock.
   *
   * @var \Drupal\user\UserFloodControlInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UserFloodControlInterface&MockObject $floodControlMock;

  /**
   * Mock Validation Plugin.
   *
   * @var \Drupal\tfa\Plugin\TfaValidationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected TfaValidationInterface&MockObject $tfaValidationPluginMock;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->memoryCacheMock = $this->createMock(CacheBackendInterface::class);
    $this->floodControlMock = $this->createMock(UserFloodControlInterface::class);
    $this->tfaValidationPluginMock = $this->createMock(TfaValidationInterface::class);
  }

  /**
   * Helper method to instantiate the test fixture.
   *
   * @return \Drupal\tfa\Form\EntryForm
   *   The TFA Login Form.
   */
  public function getFixture(): EntryForm {
    $container = new ContainerBuilder();

    // Global container services.
    $request_stack_mock = $this->createMock(RequestStack::class);
    $request_stack_mock->method('getCurrentRequest')->willReturn(new Request());
    $messenger_mock = $this->createMock(MessengerInterface::class);

    $current_user_mock = $this->createMock(AccountProxyInterface::class);
    $current_user_mock->method('id')->willReturn(0);

    $global_config = [
      'tfa.settings' => [
        'default_validation_plugin' => 'unit_test_plugin',
        'allowed_validation_plugins' => 'unit_test_plugin',
        'users_without_tfa_redirect' => FALSE,
      ],
    ];

    $container->set('request_stack', $request_stack_mock);
    $container->set('config.factory', $this->getConfigFactoryStub($global_config));
    $container->set('messenger', $messenger_mock);
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('current_user', $current_user_mock);

    // Our services.
    $date_formatter_mock = $this->createMock(DateFormatterInterface::class);
    $user_data_mock = $this->createMock(UserDataInterface::class);

    $lock_mock = $this->createMock(LockBackendInterface::class);
    $lock_mock->method('acquire')->willReturnOnConsecutiveCalls(FALSE, TRUE);

    $plugin_manager_mock = $this->createMock(TfaPluginManager::class);
    $plugin_manager_mock
      ->method('createInstance')
      ->with($this->identicalTo('unit_test_plugin'))
      ->willReturn($this->tfaValidationPluginMock);

    $user_mock = $this->createMock(UserInterface::class);
    $user_mock->method('id')->willReturn('3');
    $user_storage_mock = $this->createMock(UserStorageInterface::class);
    $user_storage_mock->method('load')->with($this->identicalTo(3))->willReturn($user_mock);
    $entity_type_manager_mock = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager_mock->method('getStorage')->with('user')->willReturn($user_storage_mock);

    $container->set('plugin.manager.tfa', $plugin_manager_mock);
    $container->set('user.flood_control', $this->floodControlMock);
    $container->set('date.formatter', $date_formatter_mock);
    $container->set('user.data', $user_data_mock);
    $container->set('entity_type.manager', $entity_type_manager_mock);
    $container->set('lock', $lock_mock);
    $container->set('cache.tfa_memcache', $this->memoryCacheMock);

    // Set the global container.
    \Drupal::setContainer($container);

    return EntryForm::create($container);
  }

  /**
   * Ensures that the TFA Complete flag is set.
   *
   * @param int $count
   *   Number of times Memory Cache is expected to be called.
   * @param bool $validate_return
   *   Return value of plugin validateForm method.
   *
   * @dataProvider providerSetCompleteFlag
   */
  public function testSetCompleteFlag(int $count, bool $validate_return): void {
    $this->floodControlMock->method('isAllowed')->willReturn(TRUE);
    $this->tfaValidationPluginMock->method('ready')->willReturn(TRUE);
    $this->tfaValidationPluginMock->method('getForm')->willReturn([]);
    $this->tfaValidationPluginMock->method('validateForm')->willReturn($validate_return);

    $this->memoryCacheMock
      ->expects($this->exactly($count))
      ->method('set')
      ->with(
        self::identicalTo('tfa_complete'),
        self::identicalTo(3),
        Cache::PERMANENT,
        [],
      );

    $fixture = $this->getFixture();
    $form_state = new FormState();
    $form = $fixture->buildForm([], $form_state, 3);

    $user_entity_mock = $this->createMock(UserInterface::class);
    $user_entity_mock->method('id')->willReturn('3');

    $form_state->setValue('account', $user_entity_mock);
    $fixture->validateForm($form, $form_state);
  }

  /**
   * Provider for testSetCompleteFlag()
   *
   * @return \Generator
   *   Test data.
   */
  public static function providerSetCompleteFlag(): \Generator {

    yield 'Validation Success' => [
      1,
      TRUE,
    ];

    yield 'Validation Fail' => [
      0,
      FALSE,
    ];

  }

}

/**
 * Mock the 'user.module' functions for the core Form.
 */
namespace Drupal\user\Form;

use Drupal\user\UserInterface;

/**
 * Mock of user_login_finalize()
 */
function user_login_finalize(UserInterface $account): void {
}
