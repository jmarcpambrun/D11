<?php

declare(strict_types=1);

namespace Drupal\tfa;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserDataInterface;

/**
 * Tfa User Auth service decorator.
 *
 * @internal
 *   This class is publicly called as a decorator for the 'user.auth'
 *   service. The internal operations are subject to change at any time.
 */
final class TfaUserAuth implements UserAuthInterface {

  /**
   * Constructs a TfaUserAuth object.
   */
  public function __construct(
    protected UserAuthInterface $inner,
    protected TfaLoginContextFactory $tfaLoginContextFactory,
    protected CacheBackendInterface $memoryCache,
    protected TfaPluginManager $tfaPluginManager,
    protected ConfigFactoryInterface $configFactory,
    protected UserDataInterface $userData,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate($username, $password): int|FALSE {

    // We require a username however its theoretically possible another
    // service doesn't.
    // We proceed on empty password to prevent another service from bypassing
    // TFA.
    if (empty($username)) {
      return $this->innerAuthenticate($username, $password);
    }

    /** @var \Drupal\user\UserInterface[] $account_search */
    $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]);

    if ($account = reset($account_search)) {
      $login_context = $this->tfaLoginContextFactory->createContextFromUser($account);

      // Is TFA bypass requested, usually for TfaLoginForm which validates.
      $bypass_tfa = FALSE;
      /** @var false|\stdClass{'data': mixed} $bypass_data */
      $bypass_data = $this->memoryCache->get('bypass_tfa_auth_for_user', FALSE);
      if ($bypass_data) {
        $bypass_tfa = $account->getAccountName() === $bypass_data->data;
      }

      if ($bypass_tfa) {
        // Set a flag to indicate TFA has been bypassed in user.auth stage.
        $this->memoryCache->set('tfa_user_auth_bypassed', TRUE);
        return $this->innerAuthenticate($username, $password);
      }

      if ($login_context->isTfaDisabled()) {
        // Return inner auth when validation is disabled.
        $result = $this->innerAuthenticate($username, $password);
        $this->processInnerResult($result);
        return $result;
      }

      if (!$login_context->isReady()) {
        // TFA is enabled but not yet configured for the user.
        if (!$login_context->canLoginWithoutTfa()) {
          // TFA is required, reject the login attempt.
          return FALSE;
        }

        $response = $this->innerAuthenticate($username, $password);
        if ($response !== FALSE) {
          // Inner Authentication was successful, Record a skip.
          $login_context->hasSkipped();
        }
        $this->processInnerResult($response);
        return $response;
      }

      $validation_plugin = $this->configFactory->get('tfa.settings')->get('default_validation_plugin');
      $validation_plugin = is_string($validation_plugin) ? $validation_plugin : '';

      // Attempt to validate with the default plugin.
      try {
        /** @var \Drupal\tfa\Plugin\TfaValidationInterface $tfa_validation_plugin */
        $tfa_validation_plugin = $this->tfaPluginManager->createInstance($validation_plugin, ['uid' => $account->id()]);
      }
      catch (PluginException) {
        return FALSE;
      }
      $result = $this->validateWithValidationPlugin($tfa_validation_plugin, $username, $password);

      if ($result) {
        return $result;
      }

      // Attempt using the other plugins.
      $user_settings = $this->userData->get('tfa', (int) $account->id(), 'tfa_user_settings');
      assert(is_array($user_settings) && array_key_exists('data', $user_settings));
      $user_enabled_validation_plugins = $user_settings['data']['plugins'] ?? [];
      $additional_plugins = [];
      foreach ($this->tfaPluginManager->getValidationDefinitions() as $plugin_id => $definition) {
        if (array_key_exists($plugin_id, $user_enabled_validation_plugins) && $plugin_id != $validation_plugin) {
          try {
            $plugin = $this->tfaPluginManager->createInstance($plugin_id, ['uid' => $account->id()]);
          }
          catch (PluginException) {
            continue;
          }
          if ($plugin instanceof TfaValidationInterface) {
            $additional_plugins[] = $plugin;
          }
        }
      }

      foreach ($additional_plugins as $plugin) {
        $result = $this->validateWithValidationPlugin($plugin, $username, $password);
        if ($result) {
          return $result;
        }
      }

      // AllowedLogin needs to be last attempted in order to ensure that a
      // submitted token is marked as used.
      if ($login_context->pluginAllowsLogin()) {
        $result = $this->innerAuthenticate($username, $password);
        $this->processInnerResult($result);
        return $result;
      }

      // TFA required but no validation success.
      return FALSE;
    }

    // User did not exist.
    return FALSE;
  }

  /**
   * Validate a password+token combo contains a valid token.
   *
   * @param \Drupal\tfa\Plugin\TfaValidationInterface $plugin
   *   The plugin to test with.
   * @param string $username
   *   The username to validate against.
   * @param string $password
   *   The submitted password.
   *
   * @return int|false
   *   False if login fails, otherwise the UID of the user.
   */
  private function validateWithValidationPlugin(TfaValidationInterface $plugin, string $username, #[\SensitiveParameter] string $password): int|FALSE {
    $token_length = $plugin->tokenLength($password);

    // Password is too short to be a valid pass+token combo.
    if (strlen($password) <= $token_length || $token_length === 0) {
      return FALSE;
    }

    $code = substr($password, -$token_length, $token_length);

    if ($plugin->validateRequest($code)) {
      $result = $this->innerAuthenticate($username, substr($password, 0, -$token_length));
      $this->processInnerResult($result);
      return $result;
    }
    return FALSE;
  }

  /**
   * Wrapper for authenticating against the inner service.
   */
  private function innerAuthenticate(string $username, #[\SensitiveParameter] string $password): int|FALSE {
    /** @var int|string|FALSE $result */
    $result = $this->inner->authenticate($username, $password);

    if ($result === FALSE || (int) $result === 0) {
      return FALSE;
    }

    return (int) $result;
  }

  /**
   * Processes Inner result to set necessary completion flags.
   *
   * @param int|false $result
   *   Result from innerAuthenticate to process.
   */
  private function processInnerResult(int|false $result): void {
    if ($result !== FALSE) {
      $this->memoryCache->set('tfa_complete', $result);
    }
  }

}
