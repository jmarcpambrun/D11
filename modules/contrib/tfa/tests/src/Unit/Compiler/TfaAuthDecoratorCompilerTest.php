<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Unit\Compiler;

use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tfa\Authentication\Provider\TfaAuthDecorator;
use Drupal\tfa\Authentication\Provider\TfaChallengeAuthDecorator;
use Drupal\tfa\Compiler\TfaAuthDecoratorCompiler;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Tests the TfaAuthDecoratorCompiler class.
 *
 * @covers \Drupal\tfa\Compiler\TfaAuthDecoratorCompiler
 *
 * @group tfa
 */
class TfaAuthDecoratorCompilerTest extends UnitTestCase {

  /**
   * The mock of the container builder.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ContainerBuilder&MockObject $containerBuilderMock;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->containerBuilderMock = $this->createMock(ContainerBuilder::class);
  }

  /**
   * Tests the process method.
   *
   * @dataProvider providerProcess
   */
  public function testProcess(mixed $bypass_list, mixed $priority, callable $setup): void {
    $provider = new TfaAuthDecoratorCompiler();
    $this->containerBuilderMock
      ->method('getParameter')
      ->willReturnMap(
        [
          ['tfa.auth_provider_bypass_priority', $priority],
          ['tfa.auth_provider_bypass', $bypass_list],
        ]
      );
    $this->containerBuilderMock
      ->method('findTaggedServiceIds')
      ->with($this->identicalTo('authentication_provider'))
      ->willReturn(
        [
          'basic_auth.authentication.basic_auth' => [
            [
              'provider_id' => 'cookie',
              'priority' => 100,
              'global' => TRUE,
            ],
          ],

          'user.authentication.cookie' => [
            [
              'provider_id' => 'cookie',
              'priority' => 0,
              'global' => TRUE,
            ],
          ],

          'tfa.unit_test.provider_1' => [
            [
              'provider_id' => 'tfa_unit_1',
              'priority' => 0,
            ],
          ],

          'tfa.unit_test.provider_2' => [
            [
              'provider_id' => 'tfa_unit_2',
              'priority' => 0,
            ],
          ],

        ]
      );

    $this->containerBuilderMock->method('getDefinition')->willReturnMap(
      [
        ['tfa.unit_test.provider_1', new Definition(TfaUnitTestProvider::class)],
        ['tfa.unit_test.provider_2', new Definition(TfaUnitTestChallengeProvider::class)],
      ]
    );

    $setup($this);
    $provider->process($this->containerBuilderMock);

  }

  /**
   * Provides test data for testProcess().
   *
   * @return \Generator
   *   Test Data.
   */
  public static function providerProcess(): \Generator {

    yield 'test priority not int' => [
      [],
      '10',
      function (self $context) {
        $context->expectException(\InvalidArgumentException::class);
        $context->expectExceptionMessage('tfa.auth_provider_bypass_priority must be an integer');
      },
    ];

    yield 'test bypass not array' => [
      'string',
      50,
      function (self $context) {
        $context->expectException(\InvalidArgumentException::class);
        $context->expectExceptionMessage('tfa.auth_provider_bypass must be an array');
      },
    ];

    yield 'Bypass only has excluded entries' => [
      ['cookie', 'http_basic'],
      50,
      function (self $context) {
        $context->containerBuilderMock->expects($context->never())->method('addDefinitions');
      },
    ];

    yield 'Decorates multiple services' => [
      ['cookie', 'http_basic', 'tfa_unit_1', 'tfa_unit_2'],
      50,
      function (self $context) {
        $context->containerBuilderMock
          ->expects($context->once())
          ->method('addDefinitions')
          ->with(
            self::equalTo(
              [
                'tfa_auth_decorate_tfa.unit_test.provider_1' => (new Definition(TfaAuthDecorator::class))
                  ->setAutowired(TRUE)
                  ->setDecoratedService('tfa.unit_test.provider_1', 'tfa.unit_test.provider_1.inner', 50),

                'tfa_auth_decorate_tfa.unit_test.provider_2' => (new Definition(TfaChallengeAuthDecorator::class))
                  ->setAutowired(TRUE)
                  ->setDecoratedService('tfa.unit_test.provider_2', 'tfa.unit_test.provider_2.inner', 50),
              ]
            )
          );
      },
    ];

  }

}

/**
 * Non-functional provider for testing TfaAuthDecoratorCompiler.
 */
class TfaUnitTestProvider implements AuthenticationProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): BOOL {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): ?AccountInterface {
    return NULL;
  }

}

/**
 * Non-functional provider for testing TfaAuthDecoratorCompiler.
 */
class TfaUnitTestChallengeProvider implements AuthenticationProviderInterface, AuthenticationProviderChallengeInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): BOOL {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): ?AccountInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function challengeException(Request $request, \Exception $previous): ?HttpExceptionInterface {
    return NULL;
  }

}
