<?php

declare(strict_types=1);

namespace Drupal\tfa;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\tfa\Compiler\TfaAuthDecoratorCompiler;

/**
 * Modifies services for TFA.
 *
 * @internal
 */
final class TfaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->addCompilerPass(new TfaAuthDecoratorCompiler());
  }

}
