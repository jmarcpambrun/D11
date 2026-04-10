<?php

namespace Drupal\mail_box_management\Plugin;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * File ConfigurationHelper responsible for imap configs handling.
 *
 * @file
 * ConfigurationHelper.php contains class ConfigurationHelper.
 */

/**
 * Class ConfigurationHelper responsible for imap configs handling.
 *
 * @class
 * ConfigurationHelper responsible for imap configs handling.
 */
class ConfigurationHelper {

  /**
   * List of configs set for this module.
   *
   * @var array|string[]
   */
  private array $configurations = [
    'mail_box_management.settings',
    'mail_box_management.cache_mail_data',
    'mail_box_management.cache_mail_content',
    'mail_box_management.cache_mail_cron',
    'mail_box_management.cache_mail_clear',
    'mail_box_management.cache_mail_count_mailbox',
    'mail_box_management.mailbox_theme',
  ];

  /**
   * List of configs values.
   *
   * @var array
   */
  private array $configurationValues;

  /**
   * Initialize the ConfigurationHelper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    foreach ($this->configurations as $configuration_key) {
      $this->configurationValues[$configuration_key] = $config_factory
        ->getEditable($configuration_key);
    }
  }

  /**
   * Get all configs.
   *
   * @return array|string[]
   *   Returns array of configurations.
   */
  public function getConfigurationsKeys(): array {
    return $this->configurations;
  }

  /**
   * Get all configs values.
   *
   * @return array
   *   Returns array of configurations values.
   */
  public function getConfigurationValues(): array {
    return $this->configurationValues;
  }

  /**
   * Get config value by name.
   *
   * @param string $configuration_key
   *   Configuration key.
   *
   * @return \Drupal\Core\Config\Config|null
   *   Returns configuration value.
   */
  public function get(string $configuration_key): Config|NULL {
    if (str_starts_with($configuration_key, 'mail_box_management.')) {
      return $this->configurationValues[$configuration_key] ?? NULL;
    }
    return $this->configurationValues["mail_box_management." . $configuration_key] ?? NULL;
  }

  /**
   * Get config by current user.
   *
   * @param string $configuration_key
   *   Config key.
   *
   * @return string|null
   *   Config data is return if found or null.
   */
  public function getByCurrentUser(string $configuration_key): ?string {
    $config = $this->get('mail_box_management.settings')?->get('imap_servers') ?? [];
    $found = array_filter($config, function ($value) use ($configuration_key) {
      return $value['owner_id'] == mail_box_management_service('current_user')->id();
    });
    if (empty($found)) {
      return NULL;
    }
    $config = reset($found);
    return $config[$configuration_key] ?? NULL;
  }

  /**
   * Set configuration value.
   *
   * @param string $configuration_key
   *   Configuration key to set its value.
   * @param mixed $value
   *   Value for configuration key.
   *
   * @return \Drupal\Core\Config\Config|bool
   *   True if config was saved.
   */
  public function set(string $configuration_key, mixed $value): Config|bool {
    $configuration_key = str_contains($configuration_key, 'mail_box_management.') ?
      $configuration_key : 'mail_box_management.' . $configuration_key;

    $config = $this->configurationValues[$configuration_key] ?? NULL;
    if (empty($config)) {
      return FALSE;
    }
    $list = explode('.', $configuration_key);
    $config_storage_key = end($list);
    return $config->set($config_storage_key, $value)->save();
  }

  /**
   * Delete configuration by uid.
   *
   * @param int $uid
   *   Owner uid.
   *
   * @return \Drupal\Core\Config\Config|bool
   *   True if deleted.
   */
  public function delete(int $uid): Config|bool {
    $config = $this->get('mail_box_management.settings')->get('imap_servers') ?? [];
    $others = array_filter($config, function ($value) use ($uid) {
      return $value['owner_id'] != $uid;
    });
    if (empty($config)) {
      return FALSE;
    }
    $config = $this->get('mail_box_management.settings');
    $config->set('imap_servers', $others)->save();
    return TRUE;
  }

}
