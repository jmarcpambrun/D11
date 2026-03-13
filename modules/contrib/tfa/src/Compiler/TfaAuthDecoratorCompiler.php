<?php

declare(strict_types=1);

namespace Drupal\tfa\Compiler;

use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\tfa\Authentication\Provider\TfaAuthDecorator;
use Drupal\tfa\Authentication\Provider\TfaChallengeAuthDecorator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Dynamically add a decorator to authentication_providers exempt from TFA.
 *
 * Note: This currently only works for services implementing only
 * AuthenticationProviderInterface
 * or
 * AuthenticationProviderInterface&AuthenticationProviderChallengeInterface.
 *
 * Providers such as 'Cookie' which implement additional interfaces are not
 * supported.
 *
 * @internal
 */
final class TfaAuthDecoratorCompiler implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    // These auth providers do not permit bypass.
    $bypass_prohibited = [
      'cookie',
      'http_basic',
    ];

    $priority = $container->getParameter('tfa.auth_provider_bypass_priority');
    if (!is_int($priority)) {
      throw new \InvalidArgumentException('tfa.auth_provider_bypass_priority must be an integer');
    }
    $bypass_list = $container->getParameter('tfa.auth_provider_bypass');
    if (!is_array($bypass_list)) {
      throw new \InvalidArgumentException('tfa.auth_provider_bypass must be an array');
    }
    foreach ($bypass_list as $key => $provider_id) {
      if (in_array($provider_id, $bypass_prohibited)) {
        unset($bypass_list[$key]);
      }
    }

    $definitions = [];
    foreach ($container->findTaggedServiceIds('authentication_provider') as $id => $tags) {
      foreach ($tags as $tag) {
        if (isset($tag['provider_id']) && in_array($tag['provider_id'], $bypass_list)) {
          $parent = $container->getDefinition($id);
          $parent_class = $parent->getClass();
          if ($parent_class == NULL) {
            continue;
          }
          if (is_subclass_of($parent_class, AuthenticationProviderChallengeInterface::class)) {
            $definition = new Definition(TfaChallengeAuthDecorator::class);
          }
          else {
            $definition = new Definition(TfaAuthDecorator::class);
          }
          $definition->setDecoratedService($id, $id . '.inner', $priority);
          $definition->setAutowired(TRUE);
          $definitions['tfa_auth_decorate_' . $id] = $definition;
        }
      }
    }
    if (!empty($definitions)) {
      $container->addDefinitions($definitions);
    }
  }

}
