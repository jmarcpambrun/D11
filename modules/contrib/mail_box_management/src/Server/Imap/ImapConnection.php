<?php

namespace Drupal\mail_box_management\Server\Imap;

use IMAP\Connection;

/**
 * File ImapConnection.
 *
 * @file
 * ImapConnection.php contains class ImapConnection
 */

/**
 * ImapConnection is responsible for imap connectivity.
 *
 * @class
 * ImapConnection is responsible for imap connectivity.
 */
class ImapConnection {

  /**
   * Username for imap server.
   *
   * @var ?string
   */
  public ?string $configUsername;

  /**
   * Password for imap server.
   *
   * @var ?string
   */
  public ?string $configPassword;

  /**
   * Hostname for imap server.
   *
   * @var ?string
   */
  public ?string $configHostname;

  /**
   * Port for imap server.
   *
   * @var ?string
   */
  public ?string $configPort;

  /**
   * IMAP connection.
   *
   * @var false|\IMAP\Connection
   */
  public false|Connection $imapConnection;

  /**
   * Secure SSL or TLS for imap.
   *
   * @var ?string
   */
  public ?string $secure;

  /**
   * Connection string namespace.
   *
   * @var string
   */
  public string $hostname;

  /**
   * Initialize connection.
   */
  public function __construct(array $server = []) {
    $config_helper = mail_box_management_service('mail_box_management.config');
    $config = $config_helper->get('mail_box_management.settings');
    $current_user = mail_box_management_service('current_user');
    $user_configs = array_filter($config->get('imap_servers') ?? [], function ($config) use ($current_user) {
      return $config['owner_id'] == $current_user->id();
    });
    $user_configs = !empty($user_configs) ? reset($user_configs) : $server;
    $this->configHostname = $user_configs['host'] ?? NULL;
    $this->configUsername = $user_configs['username'] ?? NULL;
    $this->configPassword = $user_configs['password'] ?? NULL;
    $this->configPort = $user_configs['port'] ?? NULL;
    $this->secure = $user_configs['secure'] ?? FALSE;
    if (!empty($this->configHostname) &&
      !empty($this->configPort) &&
      !empty($this->configUsername) &&
      !empty($this->configPassword) &&
      !empty($this->secure)
    ) {
      $this->hostname = "{{$this->configHostname}:{$this->configPort}/imap/{$this->secure}}";
      if ($con = imap_open($this->hostname, $this->configUsername, $this->configPassword)) {
        $this->imapConnection = $con;
      }
    }

  }

  /**
   * Destroy connection.
   */
  public function __destruct() {
    unset($this->imapConnection);
  }

}
