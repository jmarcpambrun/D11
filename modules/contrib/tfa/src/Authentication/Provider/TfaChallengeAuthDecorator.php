<?php

namespace Drupal\tfa\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Decorates authentication_provider services to allow bypassing TFA.
 *
 * This provides a method to exempt authentication providers that do not
 *  utilize the 'user.auth' service.
 *
 * This class can only decorate services implementing only
 * AuthenticationProviderInterface and
 * AuthenticationProviderChallengeInterface.
 *
 * @internal
 */
final class TfaChallengeAuthDecorator implements AuthenticationProviderInterface, AuthenticationProviderChallengeInterface {

  /**
   * Construct a new TfaChallengeAuthDecorator object.
   */
  public function __construct(
    #[AutowireDecorated] protected AuthenticationProviderInterface&AuthenticationProviderChallengeInterface $inner,
    #[Autowire(service: 'cache.tfa_memcache')] protected CacheBackendInterface $memoryCache,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function challengeException(Request $request, \Exception $previous): ?HttpExceptionInterface {
    return $this->inner->challengeException($request, $previous);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): bool {
    return $this->inner->applies($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): ?AccountInterface {
    $result = $this->inner->authenticate($request);
    if ($result !== NULL) {
      $this->memoryCache->set('tfa_auth_method_bypass_approved', (int) $result->id());
    }
    return $result;
  }

}
