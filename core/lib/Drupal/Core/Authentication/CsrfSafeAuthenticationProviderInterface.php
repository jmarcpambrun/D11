<?php

namespace Drupal\Core\Authentication;

/**
 * Interface for authentication providers which safely disable CSRF checks.
 */
interface CsrfSafeAuthenticationProviderInterface extends AuthenticationProviderInterface {

  /**
   * Returns cache contexts for which the provider's safety varies.
   *
   * For instance, a Bearer token authentication provider would return
   * ['headers:authorization'].
   *
   * @return string[]
   */
  public function getCacheContexts(): array;

}
