<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\TfaAccountSwitcher;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the TfaAccountSwitcher class.
 *
 * @covers \Drupal\tfa\TfaAccountSwitcher
 *
 * @group tfa
 */
class TfaAccountSwitcherTest extends UnitTestCase {

  /**
   * The Inner Account Switcher mock.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AccountSwitcherInterface&MockObject $innerMock;

  /**
   * The Memory Cache mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $memoryCacheMock;

  /**
   * CurrentUser service mock.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AccountProxyInterface&MockObject $currentUserMock;

  /**
   * An array of memory cache calls.
   *
   * @var array
   */
  protected array $memoryCacheCallbackStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->innerMock = $this->createMock(AccountSwitcherInterface::class);
    $this->memoryCacheMock = $this->createMock(CacheBackendInterface::class);
    $this->currentUserMock = $this->createMock(AccountProxyInterface::class);

    $this->memoryCacheCallbackStack = [];

    $this->memoryCacheMock
      ->method('set')
      ->willReturnCallback(
        function (string $cache_name, mixed $value, int $expire, array $tags) {
          $this->assertSame(Cache::PERMANENT, $expire);
          $this->assertEmpty($tags);
          $this->memoryCacheCallbackStack[] = ['set', $cache_name, $value];
        }
      );

    $this->memoryCacheMock
      ->method('delete')
      ->willReturnCallback(
        function (string $cache_name) {
          $this->memoryCacheCallbackStack[] = ['delete', $cache_name];
        }
      );

  }

  /**
   * Helper method to instantiate the test fixture.
   *
   * @return \Drupal\tfa\TfaAccountSwitcher
   *   The TFA Account Switcher.
   */
  public function getFixture(): TfaAccountSwitcher {
    return new TfaAccountSwitcher($this->innerMock, $this->currentUserMock, $this->memoryCacheMock);
  }

  /**
   * Tests the switchTo()/switchBack() methods.
   */
  public function testSwitchToAndBack(): void {

    $this->currentUserMock->method('id')->willReturn('10');

    $account_mock = $this->createMock(AccountInterface::class);
    $account_mock->method('id')->willReturn('20');

    $this->innerMock
      ->expects(self::once())
      ->method('switchTo')
      ->with($this->identicalTo($account_mock));
    $this->innerMock
      ->expects(self::once())
      ->method('switchBack');

    $fixture = $this->getFixture();

    // Populate the stack.
    $fixture->switchTo($account_mock);
    // Switch Back.
    $fixture->switchBack();

    $expected_callback_stack = [
      ['set', 'tfa_account_switcher_bypass', 20],
      ['delete', 'tfa_account_switcher_bypass'],
      ['set', 'tfa_account_switcher_bypass', 10],
      ['delete', 'tfa_account_switcher_bypass'],
    ];
    $this->assertSame($expected_callback_stack, $this->memoryCacheCallbackStack);

  }

  /**
   * Tests calling switchTo()/switchBack() multiple times.
   */
  public function testSwitchMultipleDeep(): void {

    $account_mock = $this->createMock(AccountInterface::class);
    $account_mock->method('id')->willReturnOnConsecutiveCalls('20', '30');

    $this->currentUserMock->method('id')->willReturnOnConsecutiveCalls('10', '20');

    $this->innerMock
      ->expects(self::exactly(2))
      ->method('switchTo')
      ->with($this->identicalTo($account_mock));
    $this->innerMock
      ->expects(self::exactly(2))
      ->method('switchBack');

    $fixture = $this->getFixture();

    // Populate the stack.
    $fixture->switchTo($account_mock);
    $fixture->switchTo($account_mock);

    // Switch Back.
    $fixture->switchBack();
    $fixture->switchBack();

    $expected_callback_stack = [
      ['set', 'tfa_account_switcher_bypass', 20],
      ['delete', 'tfa_account_switcher_bypass'],
      ['set', 'tfa_account_switcher_bypass', 30],
      ['delete', 'tfa_account_switcher_bypass'],
      ['set', 'tfa_account_switcher_bypass', 20],
      ['delete', 'tfa_account_switcher_bypass'],
      ['set', 'tfa_account_switcher_bypass', 10],
      ['delete', 'tfa_account_switcher_bypass'],
    ];
    $this->assertSame($expected_callback_stack, $this->memoryCacheCallbackStack);

  }

  /**
   * Tests calling switchBack() more times than switchTo().
   */
  public function testSwitchBackExtra(): void {

    $account_mock = $this->createMock(AccountInterface::class);
    $account_mock->method('id')->willReturnOnConsecutiveCalls('20');

    $this->currentUserMock->method('id')->willReturnOnConsecutiveCalls('10');

    $this->innerMock
      ->expects(self::exactly(1))
      ->method('switchTo')
      ->with($this->identicalTo($account_mock));
    $this->innerMock
      ->expects(self::exactly(2))
      ->method('switchBack');

    $fixture = $this->getFixture();

    // Populate the stack.
    $fixture->switchTo($account_mock);

    // Switch Back.
    $fixture->switchBack();
    $fixture->switchBack();

    $expected_callback_stack = [
      ['set', 'tfa_account_switcher_bypass', 20],
      ['delete', 'tfa_account_switcher_bypass'],
      ['set', 'tfa_account_switcher_bypass', 10],
      ['delete', 'tfa_account_switcher_bypass'],
      ['delete', 'tfa_account_switcher_bypass'],
    ];
    $this->assertSame($expected_callback_stack, $this->memoryCacheCallbackStack);

  }

}
