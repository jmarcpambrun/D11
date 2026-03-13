<?php

namespace Drupal\tfa;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\user\UserInterface;

/**
 * Provides methods for logging in users.
 *
 * @internal
 */
trait TfaLoginTrait {

  /**
   * The 'private_key' service.
   *
   * @var \Drupal\Core\PrivateKey|null
   */
  protected ?PrivateKey $privateKey = NULL;

  /**
   * Generate a hash that can uniquely identify an account's state.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account for which a hash is required.
   *
   * @return string
   *   The hash value representing the user.
   */
  protected function getLoginHash(UserInterface $account): string {
    // Using account login will mean this hash will become invalid once user has
    // authenticated via TFA.
    $data = implode(':', [
      $account->getAccountName(),
      $account->getPassword(),
      $account->getLastLoginTime(),
    ]);
    $key = $this->getPrivateKey()->get() . Settings::get('hash_salt');
    return Crypt::hmacBase64($data, $key);
  }

  /**
   * Get the `private_key` service.
   *
   * @return \Drupal\Core\PrivateKey
   *   The `private_key` service.
   *
   * @throws \RuntimeException
   *   When `private_key` service has not been set.
   */
  protected function getPrivateKey(): PrivateKey {
    if ($this->privateKey === NULL) {
      throw new \RuntimeException('The private_key service is not set');
    }
    return $this->privateKey;
  }

  /**
   * Set the `private_key` service.
   *
   * @param \Drupal\Core\PrivateKey $private_key
   *   The `private_key` service.
   */
  protected function setPrivateKey(PrivateKey $private_key): void {
    $this->privateKey = $private_key;
  }

}
