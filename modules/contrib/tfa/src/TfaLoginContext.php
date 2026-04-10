<?php

declare(strict_types=1);

namespace Drupal\tfa;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Provide context for the current login attempt.
 *
 * This class collects data needed to decide whether TFA is required and, if so,
 * whether it is successful. This includes configuration of the module, and the
 * user that is attempting to log in.
 *
 * @internal
 */
class TfaLoginContext {
  use StringTranslationTrait;
  use TfaUserDataTrait;

  /**
   * Construct a new TfaLoginContext class.
   */
  public function __construct(
    protected UserInterface $user,
    protected TfaPluginManager $tfaPluginManager,
    protected ConfigFactoryInterface $configFactory,
    protected UserDataInterface $userData,
    TranslationInterface $translation,
    protected MessengerInterface $messenger,
    protected LoggerChannelInterface $loggerChannel,
    protected UrlGeneratorInterface $urlGenerator,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * Get the user object.
   *
   * @return \Drupal\user\UserInterface
   *   The entity object of the user attempting to log in.
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

  /**
   * Is TFA enabled and configured?
   *
   * @return bool
   *   TRUE if TFA is disabled.
   */
  public function isTfaDisabled(): bool {
    // Global TFA settings take precedence.
    $config = $this->configFactory->get('tfa.settings');
    if (!($config->get('enabled')) || empty($config->get('default_validation_plugin'))) {
      return TRUE;
    }

    // Check if the user has enabled TFA.
    $user_tfa_data = $this->tfaGetTfaData((int) $this->user->id());
    if (!empty($user_tfa_data['status']) && !empty($user_tfa_data['data']['plugins'])) {
      return FALSE;
    }

    // TFA is not necessary if the user doesn't have one of the required roles.
    $required_roles = array_filter($config->get('required_roles'));
    return empty(array_intersect($required_roles, $this->user->getRoles()));
  }

  /**
   * Check whether the Validation Plugin is set and ready for use.
   *
   * @return bool
   *   TRUE if Validation Plugin exists and is ready for use.
   */
  public function isReady(): bool {
    $config = $this->configFactory->get('tfa.settings');
    // If possible, set up an instance of tfaValidationPlugin and the user's
    // list of plugins.
    $default_validation_plugin = $config->get('default_validation_plugin');
    if (!empty($default_validation_plugin)) {
      /** @var \Drupal\tfa\Plugin\TfaValidationInterface $validation_plugin */
      try {
        $validation_plugin = $this->tfaPluginManager->createInstance($default_validation_plugin, ['uid' => $this->user->id()]);
        if (isset($validation_plugin) && $validation_plugin->ready()) {
          return TRUE;
        }
      }
      catch (PluginException $e) {
        // No error as we want to try other plugins.
      }
    }

    // Check alternate plugins.
    /** @var string[] $enabled_plugins */
    $enabled_plugins = is_array($config->get('allowed_validation_plugins')) ? $config->get('allowed_validation_plugins') : [];
    foreach ($enabled_plugins as $plugin_name) {
      if ($plugin_name == $default_validation_plugin) {
        // We checked the default plugin already.
        continue;
      }
      try {
        /** @var \Drupal\tfa\Plugin\TfaValidationInterface $validation_plugin */
        $validation_plugin = $this->tfaPluginManager->createInstance($plugin_name, ['uid' => $this->user->id()]);
        if ($validation_plugin->ready()) {
          return TRUE;
        }
      }
      catch (PluginException $e) {
        // No error as we want to try other plugins.
      }
    }

    return FALSE;
  }

  /**
   * Remaining number of allowed logins without setting up TFA.
   *
   * @return int|false
   *   FALSE if users are never allowed to log in without setting up TFA.
   *   The remaining number of times user may log in without setting up TFA.
   */
  public function remainingSkips(): int|FALSE {
    $config = $this->configFactory->get('tfa.settings');
    $allowed_skips = (int) $config->get('validation_skip');
    // Skipping TFA setup is not allowed.
    if ($allowed_skips <= 0) {
      return FALSE;
    }

    $user_tfa_data = $this->tfaGetTfaData((int) $this->user->id());
    $validation_skipped = $user_tfa_data['validation_skipped'] ?? 0;
    return max(0, $allowed_skips - $validation_skipped);
  }

  /**
   * Increment the count of user logins without setting up TFA.
   */
  public function hasSkipped(): void {
    $user_tfa_data = $this->tfaGetTfaData((int) $this->user->id());
    $validation_skipped = $user_tfa_data['validation_skipped'] ?? 0;
    $user_tfa_data['validation_skipped'] = $validation_skipped + 1;
    $this->tfaSaveTfaData((int) $this->user->id(), $user_tfa_data);
  }

  /**
   * Whether at least one plugin allows authentication.
   *
   * If any plugin returns TRUE then authentication is not interrupted by TFA.
   *
   * @return bool
   *   TRUE if login allowed otherwise FALSE.
   */
  public function pluginAllowsLogin(): bool {
    $login_definitions = $this->tfaPluginManager->getLoginDefinitions();
    if (!empty($login_definitions)) {
      foreach ($login_definitions as $plugin_id => $definition) {
        /** @var \Drupal\tfa\Plugin\TfaLoginInterface $login_plugin */
        try {
          $login_plugin = $this->tfaPluginManager->createInstance($plugin_id, ['uid' => $this->user->id()]);
          if (isset($login_plugin) && $login_plugin->loginAllowed()) {
            return TRUE;
          }
        }
        catch (PluginException $e) {
          continue;
        }
      }
    }

    return FALSE;
  }

  /**
   * Wrapper for user_login_finalize().
   */
  public function doUserLogin(): void {
    // @todo Set a hash mark to indicate TFA authorization has passed.
    user_login_finalize($this->user);
  }

  /**
   * Check if the user can log in without TFA.
   *
   * @return bool
   *   Return true if the user can log in without TFA,
   *   otherwise return false.
   */
  public function canLoginWithoutTfa(): bool {
    $user = $this->getUser();

    // Users that have configured a TFA method should not be allowed to skip.
    $user_settings = $this->userData->get('tfa', (int) $user->id(), 'tfa_user_settings');
    $user_enabled_validation_plugins = [];
    if (is_array($user_settings) && array_key_exists('data', $user_settings)) {
      $user_enabled_validation_plugins = is_array($user_settings['data']['plugins']) ? $user_settings['data']['plugins'] : [];
    }
    foreach ($this->tfaPluginManager->getValidationDefinitions(FALSE) as $plugin_id => $definition) {
      if (array_key_exists($plugin_id, $user_enabled_validation_plugins)) {
        /** @var \Drupal\tfa\Plugin\TfaValidationInterface $plugin */
        $plugin = $this->tfaPluginManager->createInstance($plugin_id, ['uid' => $user->id()]);
        if ($plugin->ready()) {
          $message = $this->configFactory->get('tfa.settings')->get('help_text');
          if (is_string($message)) {
            $this->messenger->addError($message);
          }
          return FALSE;
        }
      }
    }
    // User may be able to skip TFA, depending on module settings and number of
    // prior attempts.
    $remaining = $this->remainingSkips();
    $user = $this->getUser();
    if ($remaining) {
      if ($user->hasPermission('setup own tfa')) {
        $tfa_setup_link = Url::fromRoute('tfa.overview', [
          'user' => $user->id(),
        ])
          ->setUrlGenerator($this->urlGenerator)
          ->toString();
        $message = $this->formatPlural(
          $remaining - 1,
          'You are required to <a href="@link">setup two-factor authentication</a>. You have @remaining attempt left. After this you will be unable to login.',
          'You are required to <a href="@link">setup two-factor authentication</a>. You have @remaining attempts left. After this you will be unable to login.',
          ['@remaining' => $remaining - 1, '@link' => $tfa_setup_link]
        );
        $this->messenger->addError($message);
      }
      else {
        $message = $this->formatPlural(
          $remaining - 1,
          'You are required to setup two-factor authentication however your account does not have the necessary permissions. Please contact an administrator. You have @remaining attempt left. After this you will be unable to login.',
          'You are required to setup two-factor authentication however your account does not have the necessary permissions. Please contact an administrator. You have @remaining attempts left. After this you will be unable to login.',
          ['@remaining' => $remaining - 1]
        );
        $this->messenger->addError($message);
      }
      // User can log in without TFA.
      return TRUE;
    }
    else {
      $message = $this->configFactory->get('tfa.settings')->get('help_text');
      $message = is_string($message) ? $message : '';
      $this->messenger->addError($message);
      $this->loggerChannel->notice('@name has no more remaining attempts for bypassing the second authentication factor.', ['@name' => $user->getAccountName()]);
    }

    // User can't login without TFA.
    return FALSE;
  }

}
