<?php

namespace Drupal\Tests\tfa\Unit\Plugin\Tfa;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Plugin\Tfa\TfaRecoveryCode;
use Drupal\user\UserDataInterface;

/**
 * @coversDefaultClass \Drupal\tfa\Plugin\Tfa\TfaRecoveryCode
 *
 * @group tfa
 */
class TfaRecoveryCodeTest extends UnitTestCase {

  /**
   * Mocked user data service.
   *
   * @var \Drupal\user\UserDataInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $userData;

  /**
   * Mocked encryption profile manager.
   *
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $encryptionProfileManager;

  /**
   * The mocked encryption service.
   *
   * @var \Drupal\encrypt\EncryptServiceInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $encryptionService;

  /**
   * The mocked TFA settings.
   *
   * @var array<string, int|bool|string>
   */
  protected $tfaSettings;

  /**
   * A mocked encryption profile.
   *
   * @var \Drupal\encrypt\EncryptionProfileInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $encryptionProfile;

  /**
   * A mocked current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * Default configuration for the plugin.
   *
   * @var array
   */
  protected $configuration = [
    'uid' => 3,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Stub out default mocked services. These can be overridden prior to
    // calling ::getFixture().
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->encryptionProfileManager = $this->createMock(EncryptionProfileManagerInterface::class);
    $this->encryptionService = $this->createMock(EncryptServiceInterface::class);
    $this->tfaSettings = [];
    $this->encryptionProfile = $this->createMock(EncryptionProfileInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
  }

  /**
   * Helper method to construct the test fixture.
   *
   * @return \Drupal\tfa\Plugin\Tfa\TfaRecoveryCode
   *   Recovery code.
   *
   * @throws \Exception
   */
  protected function getFixture(): TfaRecoveryCode {
    // The plugin calls out to the global \Drupal object, so mock that here.
    $config_factory = $this->getConfigFactoryStub(['tfa.settings' => $this->tfaSettings]);
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $lock_mock = $this->createMock(LockBackendInterface::class);
    $lock_mock->method('acquire')->willReturn(TRUE);

    return new TfaRecoveryCode(
      $this->configuration,
      'tfa_recovery_code',
      [],
      $this->userData,
      $this->encryptionProfileManager,
      $this->encryptionService,
      $container->get('config.factory'),
      $this->currentUser,
      $lock_mock
    );
  }

  /**
   * @covers ::ready
   * @covers ::getCodes
   * @covers ::validate
   */
  public function testReadyCodesValidate(): void {
    $this->tfaSettings = [
      'encryption' => 'foo',
      'default_validation_plugin' => 'bar',
    ];
    $this->encryptionProfileManager->method('getEncryptionProfile')->with('foo')->willReturn($this->encryptionProfile);

    // No codes, means it isn't ready.
    $fixture = $this->getFixture();
    $this->assertFalse($fixture->ready());

    // Fake some codes for user 3.
    $this->userData->method('get')->with('tfa', 3, 'tfa_recovery_code')
      ->willReturn(['foo', 'bar']);
    $this->encryptionService->method('decrypt')->willReturnMap(
      [
        ['foo', $this->encryptionProfile, 'foo_decrypted'],
        ['bar', $this->encryptionProfile, 'bar_decrypted'],
      ]
    );
    $this->tfaSettings = [
      'validation_plugin_settings.tfa_recovery_code.recovery_codes_amount' => 10,
      'encryption' => 'foo',
      'default_validation_plugin' => 'bar',
    ];
    $fixture = $this->getFixture();
    $this->assertTrue($fixture->ready());

    $this->assertEquals([
      'foo_decrypted',
      'bar_decrypted',
    ], $fixture->getCodes());

    // Validate with a bad code.
    $this->userData->expects($this->atLeastOnce())->method('delete')->with('tfa', 3, 'tfa_recovery_code');
    $fixture = $this->getFixture();
    $form_state = new FormState();
    $form_state->setValues(['code' => 'bad_code']);
    $this->assertFalse($fixture->validateForm([], $form_state));
    $this->assertCount(1, $fixture->getErrorMessages());

    // Validate with a good code. This will remove the code and re-encrypt the
    // remaining code 'bar_decrypted'.
    $this->encryptionService->method('encrypt')->with('bar_decrypted', $this->encryptionProfile)->willReturn('bar');
    $this->userData->expects($this->atLeastOnce())->method('set')->with('tfa', 3, 'tfa_recovery_code', [1 => 'bar']);
    $fixture = $this->getFixture();
    $form_state->setValues(['code' => 'foo_decrypted']);
    $this->assertTrue($fixture->validateForm([], $form_state));
    $this->assertEmpty($fixture->getErrorMessages());
  }

}
