<?php

namespace Drupal\Tests\tfa\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;

require __DIR__ . '/../../../../tfa_post_update.php';

/**
 * @covers ::tfa_post_update_user_data_0001_remove_sms()
 *
 * @group tfa
 */
class UserData0001RemoveSmsTest extends UnitTestCase {

  /**
   * Mock messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MessengerInterface&MockObject $messengerMock;

  /**
   * Mock of the user data service.
   *
   * @var \Drupal\user\UserDataInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UserDataInterface&MockObject $userDataMock;

  /**
   * Mock User Storage entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected QueryInterface&MockObject $userEntityQueryMock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->messengerMock = $this->createMock(MessengerInterface::class);
    $this->userDataMock = $this->createMock(UserDataInterface::class);

    $this->userEntityQueryMock = $this->createMock(QueryInterface::class);
    $user_storage_mock = $this->createMock(UserStorageInterface::class);
    $user_storage_mock->method('getQuery')->willReturnReference($this->userEntityQueryMock);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('user')->willReturn($user_storage_mock);

    $container = new ContainerBuilder();
    $container->set('messenger', $this->messengerMock);
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('user.data', $this->userDataMock);
    \Drupal::setContainer($container);
  }

  /**
   * Test when no users have TFA configured.
   */
  public function testNothingToUpdate(): void {
    $sandbox = [];
    $this->userEntityQueryMock->expects(self::once())->method('execute')->willReturnOnConsecutiveCalls([]);
    $this->userEntityQueryMock->method($this->anything())->willReturnSelf();
    $this->userDataMock->expects(self::never())->method(self::anything());
    $this->messengerMock->expects(self::never())->method(self::anything());
    tfa_post_update_user_data_0001_remove_sms($sandbox);
    $expected_sandbox_values = [
      '#finished' => 1,
      'total' => 0,
      'current' => 0,
    ];
    foreach ($expected_sandbox_values as $key => $value) {
      $this->assertArrayHasKey($key, $sandbox);
      $this->assertSame($value, $sandbox[$key]);
    }
  }

  /**
   * Test a single run successful update.
   */
  public function testSuccessfulUpdate(): void {
    $sandbox = [];
    $this->userEntityQueryMock->expects(self::exactly(2))->method('execute')->willReturn(['1', '1', '1']);
    $this->userEntityQueryMock->method($this->anything())->willReturnSelf();
    $this->userDataMock
      ->expects(self::exactly(3))
      ->method('get')
      ->with('tfa', '1', 'tfa_user_settings')
      ->willReturn(
        [
          'saved' => '1234556',
          'status' => '1',
          'data' => [
            'plugins' => 'test1',
            'sms' => '1234567',
          ],
          'validation_skipped' => '0',
        ]
      );
    $this->userDataMock
      ->expects(self::exactly(3))
      ->method('set')
      ->with(
        'tfa',
        1,
        'tfa_user_settings',
        [
          'saved' => '1234556',
          'status' => '1',
          'data' => [
            'plugins' => 'test1',
          ],
          'validation_skipped' => '0',
        ]
      );
    tfa_post_update_user_data_0001_remove_sms($sandbox);
    $expected_sandbox_values = [
      '#finished' => 1,
      'total' => 3,
      'current' => 3,
    ];
    foreach ($expected_sandbox_values as $key => $value) {
      $this->assertArrayHasKey($key, $sandbox);
      $this->assertSame($value, $sandbox[$key]);
    }
  }

  /**
   * Test the first run of a batch.
   */
  public function testBatchNeedsMultipleRuns(): void {
    $sandbox = [];
    $this->userEntityQueryMock->expects(self::exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
      range(1, 100),
      range(1, 25),
    );
    $this->userEntityQueryMock->method($this->anything())->willReturnSelf();
    $this->userDataMock
      ->expects(self::exactly(25))
      ->method('get')
      ->willReturn(
        [
          'saved' => '1234556',
          'status' => '1',
          'data' => [
            'plugins' => 'test1',
            'sms' => '1234567',
          ],
          'validation_skipped' => '0',
        ]
      );
    $this->userDataMock
      ->expects(self::exactly(25))
      ->method('set')
      ->with(
        'tfa',
        self::anything(),
        'tfa_user_settings',
        [
          'saved' => '1234556',
          'status' => '1',
          'data' => [
            'plugins' => 'test1',
          ],
          'validation_skipped' => '0',
        ]
      );

    tfa_post_update_user_data_0001_remove_sms($sandbox);
    $expected_sandbox_values = [
      '#finished' => .25,
      'total' => 100,
      'current' => 25,
    ];
    foreach ($expected_sandbox_values as $key => $value) {
      $this->assertArrayHasKey($key, $sandbox);
      $this->assertSame($value, $sandbox[$key]);
    }
  }

  /**
   * Test an additional run of a batch.
   */
  public function testAdditionalRun(): void {
    $sandbox = [
      '#finished' => .75,
      'total' => 100,
      'current' => 75,
    ];
    $this->userEntityQueryMock->expects(self::exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
      range(76, 100),
    );
    $this->userEntityQueryMock->method($this->anything())->willReturnSelf();
    $this->userDataMock
      ->expects(self::exactly(25))
      ->method('get')
      ->willReturn(
        [
          'saved' => '1234556',
          'status' => '1',
          'data' => [
            'plugins' => 'test1',
            'sms' => '1234567',
          ],
          'validation_skipped' => '0',
        ]
      );
    $this->userDataMock
      ->expects(self::exactly(25))
      ->method('set')
      ->with(
        'tfa',
        self::anything(),
        'tfa_user_settings',
        [
          'saved' => '1234556',
          'status' => '1',
          'data' => [
            'plugins' => 'test1',
          ],
          'validation_skipped' => '0',
        ]
      );

    tfa_post_update_user_data_0001_remove_sms($sandbox);
    $expected_sandbox_values = [
      '#finished' => 1,
      'total' => 100,
      'current' => 100,
    ];
    foreach ($expected_sandbox_values as $key => $value) {
      $this->assertArrayHasKey($key, $sandbox);
      $this->assertSame($value, $sandbox[$key]);
    }
  }

  /**
   * Test when user has no user data.
   */
  public function testUserHasNoTfaUserData(): void {
    $sandbox = [];
    $this->userEntityQueryMock->expects(self::exactly(2))->method('execute')->willReturn(['1']);
    $this->userEntityQueryMock->method($this->anything())->willReturnSelf();
    $this->userDataMock
      ->expects(self::once())
      ->method('get')
      ->willReturn(NULL);
    $this->userDataMock
      ->expects(self::never())
      ->method('set');
    tfa_post_update_user_data_0001_remove_sms($sandbox);
    $expected_sandbox_values = [
      '#finished' => 1,
      'total' => 1,
      'current' => 1,
    ];
    foreach ($expected_sandbox_values as $key => $value) {
      $this->assertArrayHasKey($key, $sandbox);
      $this->assertSame($value, $sandbox[$key]);
    }
  }

  /**
   * Test when user data is corrupt.
   */
  public function testUserHasCorruptTfaUserData(): void {
    $sandbox = [];
    $this->userEntityQueryMock->expects(self::exactly(2))->method('execute')->willReturn(['1']);
    $this->userEntityQueryMock->method($this->anything())->willReturnSelf();
    $this->userDataMock
      ->expects(self::once())
      ->method('get')
      ->willReturn(new \stdClass());
    $this->userDataMock
      ->expects(self::never())
      ->method('set');
    $this->messengerMock
      ->expects(self::once())
      ->method('addError')
      ->with(new TranslatableMarkup(
        "UID ':uid' has corrupt user data, not upgraded.",
        [':uid', 1]
      )
    );
    tfa_post_update_user_data_0001_remove_sms($sandbox);
    $expected_sandbox_values = [
      '#finished' => 1,
      'total' => 1,
      'current' => 1,
    ];
    foreach ($expected_sandbox_values as $key => $value) {
      $this->assertArrayHasKey($key, $sandbox);
      $this->assertSame($value, $sandbox[$key]);
    }
  }

}
