<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit;

use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Authentication\Provider\TfaChallengeAuthDecorator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 *
 * @covers \Drupal\tfa\Authentication\Provider\TfaChallengeAuthDecorator
 *
 * @group tfa
 */
class TfaChallengeAuthDecoratorTest extends UnitTestCase {

  /**
   * Mock of the inner service.
   *
   * @var \Drupal\Core\Authentication\AuthenticationProviderInterface&\Drupal\Core\Authentication\AuthenticationProviderChallengeInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AuthenticationProviderInterface&AuthenticationProviderChallengeInterface&MockObject $innerMock;

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
    $this->innerMock = $this->createMock(MockAuthenticatorInterface::class);
    $this->memoryMock = $this->createMock(CacheBackendInterface::class);

  }

  /**
   * Get a test fixture.
   */
  public function getFixture(): TfaChallengeAuthDecorator {
    return new TfaChallengeAuthDecorator($this->innerMock, $this->memoryMock);
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
   *   Test scenarios.
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

  /**
   * Tests the authenticate() method.
   *
   * @dataProvider providerTestChallengeException
   */
  public function testChallengeException(?HttpExceptionInterface $inner_return): void {
    $request_mock = new Request();
    $exception_mock = new \Exception();

    $this->innerMock
      ->expects(self::once())
      ->method('challengeException')
      ->with($request_mock, $exception_mock)
      ->willReturn($inner_return);

    $this->memoryMock
      ->expects(self::never())
      ->method('set');

    $fixture = $this->getFixture();

    $this->assertSame($inner_return, $fixture->challengeException($request_mock, $exception_mock));
  }

  /**
   * Provides test data for testAuthenticate().
   *
   * @return \Generator
   *   Test scenarios
   */
  public static function providerTestChallengeException(): \Generator {

    yield 'Inner Success' => [
      new HttpException(404),
    ];
    yield 'Inner no match found' => [
      NULL,
    ];
  }

}

/**
 * Helper interface as PHPUnit does not allow mocking multiple interfaces.
 */
interface MockAuthenticatorInterface extends AuthenticationProviderInterface, AuthenticationProviderChallengeInterface {
}
