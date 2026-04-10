<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit\Plugin\Tfa;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Plugin\Tfa\TfaHotp;
use Drupal\user\UserDataInterface;
use Drupal\user\UserStorageInterface;
use OTPHP\HOTP;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test the TfaHotp Plugin.
 *
 * @coversDefaultClass \Drupal\tfa\Plugin\Tfa\TfaHotp
 * @codeCoverageIgnore
 *
 * @group tfa
 */
final class TfaHotpTest extends UnitTestCase {

  /**
   * Mocked user data service.
   *
   * @var \Drupal\user\UserDataInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UserDataInterface&MockObject $userDataMock;

  /**
   * The mocked encryption service.
   *
   * @var \Drupal\encrypt\EncryptServiceInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EncryptServiceInterface&MockObject $encryptionService;

  /**
   * The mocked TFA settings.
   *
   * @var array
   */
  protected array $tfaSettings;

  /**
   * A mocked encryption profile.
   *
   * @var \Drupal\encrypt\EncryptionProfileInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EncryptionProfileInterface&MockObject $encryptionProfile;

  /**
   * A mocked current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AccountProxyInterface&MockObject $currentUser;

  /**
   * Default configuration for the plugin.
   *
   * @var array
   */
  protected array $configuration = [
    'uid' => 3,
  ];

  /**
   * Mock of the lockBackend service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LockBackendInterface&MockObject $lockMock;

  /**
   * Seed to be used in tests.
   */
  // cSpell:disable-next-line
  const SEED = 'ACCZUIQHH4QARV3IYLXQGCEI4NX6BVYRMJVDMMAAX3ILECU4WW6UKBDVDX7N5OQU2WWFRNN2XOH4RYEI3RZVGCB7RO7VHM3GT53GEDQ';

  /**
   * Time to simulate request occurred.
   */
  const SIMULATED_REQUEST_TIME = 1600000000;

  /**
   * Counter to use for current request.
   */
  const NEXT_COUNTER = 55;

  /**
   * Was 'user.data' service called to store a token counter.
   *
   * @var bool
   */
  protected bool $calledCallbackStoreCounter = FALSE;

  /**
   * Value called to store counter.
   *
   * @var int
   */
  protected int $calledCallbackStoreCounterValue = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Stub out default mocked services. These can be overridden prior to
    // calling ::getFixture().
    $site_settings['hash_salt'] = $this->randomMachineName();
    new Settings($site_settings);

    $this->tfaSettings = [
      'encryption' => 'foo',
      'default_validation_plugin' => 'bar',
    ];

    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->currentUser->method('getAccountName')->willReturn('UnitTestUser');
    // This should be an int, however core often returns as a string.
    $this->currentUser->method('id')->willReturn('3');

    $this->encryptionProfile = $this->createMock(EncryptionProfileInterface::class);
    $this->encryptionService = $this->createMock(EncryptServiceInterface::class);
    $this->encryptionService->method('decrypt')->with($this->anything(), $this->encryptionProfile)->willReturnArgument(0);

    $this->lockMock = $this->createMock(LockBackendInterface::class);

    $this->userDataMock = $this->createMock(UserDataInterface::class);
    $this->replaceUserDataMock(
        [
          ['tfa', 3, 'tfa_hotp_seed', ['seed' => base64_encode(self::SEED)]],
          ['tfa', 3, 'tfa_hotp_counter', (string) self::NEXT_COUNTER],
        ]
      );

  }

  /**
   * Helper method to construct the test fixture.
   *
   * @return \Drupal\tfa\Plugin\Tfa\TfaHotp
   *   TfaTotp plugin.
   *
   * @throws \Exception
   */
  protected function getFixture(): TfaHotp {

    $config_factory = $this->getConfigFactoryStub(
      [
        'tfa.settings' => $this->tfaSettings,
        'system.site' => ['name' => 'Unit Test Site'],
      ]
    );
    $user_storage_mock = $this->createMock(UserStorageInterface::class);
    $user_storage_mock->method('load')->with(3)->willReturn($this->currentUser);

    $time_mock = $this->createMock(TimeInterface::class);
    $time_mock->method('getRequestTime')->willReturn(self::SIMULATED_REQUEST_TIME);

    $encryption_profile_manager_mock = $this->createMock(EncryptionProfileManagerInterface::class);
    $encryption_profile_manager_mock->method('getEncryptionProfile')->with('foo')->willReturn($this->encryptionProfile);

    // The plugin calls out to the global \Drupal object, so mock that here.
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('datetime.time', $time_mock);
    \Drupal::setContainer($container);

    return new TfaHotp(
      $this->configuration,
      'tfa_hotp',
      [
        'helpLinks' => [],
      ],
      $this->userDataMock,
      $encryption_profile_manager_mock,
      $this->encryptionService,
      $config_factory,
      $time_mock,
      $user_storage_mock,
      $this->lockMock,
    );
  }

  /**
   * Test that validateRequests properly validates codes.
   *
   * @covers ::validateRequest
   * @covers ::validate
   *
   * @dataProvider providerValidate()
   */
  public function testValidateRequest(bool $expected_result, string $submitted_code, ?callable $setup = NULL, int $expected_counter = self::NEXT_COUNTER + 1): void {
    $this->lockMock->expects($this->exactly(2))->method('acquire')->with('tfa_validation_hotp_3', $this->anything())->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $this->lockMock->expects($this->once())->method('release')->with('tfa_validation_hotp_3');

    if ($setup !== NULL) {
      $setup($this);
    }

    $this->addUserDataSetCallbackChecks();

    $plugin = $this->getFixture();
    $validation_result = $plugin->validateRequest($submitted_code);
    $this->assertSame($expected_result, $validation_result);
    $this->assertSame($this->calledCallbackStoreCounter, $validation_result);
    if ($expected_result) {
      $expected_store_code = preg_replace('/\s+/', '', $submitted_code);
      assert(is_string($expected_store_code));
      $this->assertSame($this->calledCallbackStoreCounterValue, $expected_counter);
    }
  }

  /**
   * Tests that validateForm() properly validates codes.
   *
   * @covers ::validateForm
   * @covers ::validate
   *
   * @dataProvider providerValidate()
   */
  public function testValidateForm(bool $expected_result, string $submitted_code, ?callable $setup = NULL, int $expected_counter = self::NEXT_COUNTER + 1): void {
    $this->lockMock->expects($this->exactly(2))->method('acquire')->with('tfa_validation_hotp_3', $this->anything())->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $this->lockMock->expects($this->once())->method('release')->with('tfa_validation_hotp_3');

    if ($setup !== NULL) {
      $setup($this);
    }

    $this->addUserDataSetCallbackChecks();

    $form_state = new FormState();
    $form_state->setValue('code', $submitted_code);

    $plugin = $this->getFixture();
    $validation_result = $plugin->validateForm([], $form_state);
    $this->assertSame($expected_result, $validation_result);
    $this->assertSame($this->calledCallbackStoreCounter, $validation_result);
    if ($expected_result) {
      $expected_store_code = preg_replace('/\s+/', '', $submitted_code);
      assert(is_string($expected_store_code));
      $this->assertSame($this->calledCallbackStoreCounterValue, $expected_counter);
    }
  }

  /**
   * Generator for testing login code scenarios.
   *
   * @return \Generator
   *   The test data.
   */
  public static function providerValidate(): \Generator {

    yield 'Successful validation' => [
      'expected_result' => TRUE,
      'submitted_code' => HOTP::createFromSecret(self::SEED)->at(self::NEXT_COUNTER),
    ];

    yield 'Last valid token later in skew window' => [
      'expected_result' => TRUE,
      'submitted_code' => HOTP::createFromSecret(self::SEED)->at(self::NEXT_COUNTER + 10),
      'setup' => NULL,
      'expected_counter' => self::NEXT_COUNTER + 11,
    ];

    yield 'Token before current counter' => [
      'expected_result' => FALSE,
      'submitted_code' => HOTP::createFromSecret(self::SEED)->at(self::NEXT_COUNTER - 1),
    ];

    yield 'Token after last counter window' => [
      'expected_result' => FALSE,
      // Ensure we are skew + 1 time windows in the future.
      'submitted_code' => HOTP::createFromSecret(self::SEED)->at(self::NEXT_COUNTER + 11),
    ];

    yield 'Increase skew window check future' => [
      'expected_result' => TRUE,
      'submitted_code' => HOTP::createFromSecret(self::SEED)->at(self::NEXT_COUNTER + 11),
      'setup' => function (self $context) {
        $context->tfaSettings['validation_plugin_settings']['tfa_hotp']['counter_window'] = 11;
      },
      'expected_counter' => self::NEXT_COUNTER + 12,
    ];

    yield 'Successful validation with whitespace' => [
      'expected_result' => TRUE,
      'submitted_code' => ' ' . HOTP::createFromSecret(self::SEED)->at(self::NEXT_COUNTER) . ' ',
    ];

    yield 'Empty validation fails' => [
      'expected_result' => FALSE,
      'submitted_code' => '',
    ];

    yield 'Whitespace only validation fails' => [
      'expected_result' => FALSE,
      'submitted_code' => ' ',
    ];

    yield 'User has no seed configured' => [
      'expected_result' => FALSE,
      'submitted_code' => HOTP::createFromSecret(self::SEED)->at(self::NEXT_COUNTER),
      'setup' => function (self $context) {
        $context->replaceUserDataMock(
            [
              ['tfa', 3, 'tfa_hotp_seed', ['seed' => base64_encode(self::SEED)]],
              ['tfa', 3, 'tfa_hotp_counter', '0'],
            ]
        );
      },
    ];

    yield 'Using a seed from the previous library (Otp\Otp)' => [
      'expected_result' => TRUE,
      'submitted_code' => '278967',
      'setup' => function (self $context) {
        $context->replaceUserDataMock(
          [
            // cSpell:disable-next-line
            ['tfa', 3, 'tfa_hotp_seed', ['seed' => base64_encode('YPW44GO532UJLCPQ')]],
            ['tfa', 3, 'tfa_hotp_counter', (string) self::NEXT_COUNTER],
          ]
        );
      },
    ];
  }

  /**
   * Ensure the setup form can validate tokens.
   *
   * @covers ::validateSetupForm
   * @covers ::validate
   */
  public function testValidateSetupFormSeedValidation(): void {
    $this->replaceUserDataMock([]);
    $this->addUserDataSetCallbackChecks();

    $form_state = new FormState();
    $plugin = $this->getFixture();
    $form = $plugin->getSetupForm([], $form_state);
    $seed = $form['seed']['#value'];
    $token = HOTP::createFromSecret($seed);
    $form_state->setValue('code', $token->at(100));

    $this->assertFalse($plugin->validateSetupForm($form, $form_state));
    $this->assertFalse($this->calledCallbackStoreCounter);

    // Test with an valid code.
    $this->replaceUserDataMock([]);
    $this->addUserDataSetCallbackChecks();

    $form_state = new FormState();
    $plugin = $this->getFixture();
    $form = $plugin->getSetupForm([], $form_state);
    $seed = $form['seed']['#value'];
    $token = HOTP::createFromSecret($seed);
    $form_state->setValue('code', $token->at(0));

    $this->assertTrue($plugin->validateSetupForm($form, $form_state));
    $this->assertTrue($this->calledCallbackStoreCounter);
    $this->assertSame($this->calledCallbackStoreCounterValue, 1);
  }

  /**
   * Replace $this->userDataMock with a new return map.
   *
   * @param array $data
   *   Passed directly to willReturnMap() on method get().
   */
  protected function replaceUserDataMock(array $data): void {
    $this->userDataMock = $this->createMock(UserDataInterface::class);
    $this->userDataMock
      ->method('get')
      ->willReturnMap($data);
  }

  /**
   * Sets calledCallback* properties for validating plugin calls user.storage.
   */
  protected function addUserDataSetCallbackChecks(): void {
    $this->userDataMock->method('set')->with('tfa', 3, $this->anything(), $this->anything())->willReturnCallback(
      function () {
        $arguments = func_get_args();
        $key = $arguments[2];
        $value = $arguments[3];

        assert(is_string($key));

        if ($key == 'tfa_hotp_counter') {
            $this->calledCallbackStoreCounter = TRUE;
            assert(is_int($value));
            $this->calledCallbackStoreCounterValue = $value;
          return;
        }
      }
    );
  }

}
