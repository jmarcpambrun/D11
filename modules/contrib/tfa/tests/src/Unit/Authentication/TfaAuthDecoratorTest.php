<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Authentication\Provider\TfaAuthDecorator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the TfaAuthDecorator class.
 *
 * @covers \Drupal\tfa\Authentication\Provider\TfaAuthDecorator
 *
 * @group tfa
 */
class TfaAuthDecoratorTest extends UnitTestCase {

  /**
   * Mock of the inner service.
   *
   * @var \Drupal\Core\Authentication\AuthenticationProviderInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AuthenticationProviderInterface&MockObject $innerMock;

  /**
   * Mock of the memory cache backend.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface&MockObject $memoryMock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->innerMock = $this->createMock(AuthenticationProviderInterface::class);
    $this->memoryMock = $this->createMock(CacheBackendInterface::class);

  }

  /**
   * Get a test fixture.
   */
  public function getFixture(): TfaAuthDecorator {
    return new TfaAuthDecorator($this->innerMock, $this->memoryMock);
  }

  /**
   * Tests the applies() method returns inner result.
   */
  public function testApplies(): void {
    $request_mock = new Request();
    $this->innerMock
      ->expects(self::exactly(2))
      ->method('applies')
      ->with($request_mock)
      ->willReturnOnConsecutiveCalls(
        TRUE,
        FALSE,
      );

    $this->memoryMock
      ->expects(self::never())
      ->method('set');

    $fixture = $this->getFixture();

    $this->assertTrue($fixture->applies($request_mock));
    $this->assertFalse($fixture->applies($request_mock));
  }

  /**
   * Tests the authenticate() method.
   *
   * @dataProvider providerTestAuthenticate
   */
  public function testAuthenticate(bool $return_user, int $expect_cache_call): void {

    if ($return_user) {
      $inner_return = $this->createMock(AccountInterface::class);
      $inner_return->method('id')->willReturn('10');
    }
    else {
      $inner_return = NULL;
    }

    $request_mock = new Request();

    $this->innerMock
      ->expects(self::once())
      ->method('authenticate')
      ->with($request_mock)
      ->willReturn($inner_return);

    $this->memoryMock
      ->expects(self::exactly($expect_cache_call))
      ->method('set')
      ->with('tfa_auth_method_bypass_approved', $this->identicalTo(10));

    $fixture = $this->getFixture();

    $this->assertSame($inner_return, $fixture->authenticate($request_mock));
  }

  /**
   * Provides test data for testAuthenticate().
   *
   * @return \Generator
   *   Test scenarios
   */
  public static function providerTestAuthenticate(): \Generator {

    yield 'Inner Success' => [
      TRUE,
      1,
    ];
    yield 'Inner no match found' => [
      FALSE,
      0,
    ];
  }

}
