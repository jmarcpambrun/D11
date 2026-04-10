<?php

namespace Drupal\drd;

use phpseclib3\File\X509;
use phpseclib3\Net\SSH2;
use phpseclib3\System\SSH\Agent;

/**
 * Provides helper functions for SSH operations.
 *
 * @package Drupal\drd
 */
class SshLibSec extends Ssh {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function login(): bool {
    $this->connection = new SSH2($this->hostname, $this->port);
    switch ($this->mode) {
      case Ssh::SSH_MODE_USERNAME_PASSWORD:
        $success = $this->connection->login($this->username, $this->password);
        break;

      case Ssh::SSH_MODE_KEY:
        if (!empty($this->privKeyFile) && file_exists($this->privKeyFile)) {
          $x509 = new X509();
          $cert = $x509->loadX509(file_get_contents($this->privKeyFile));
          if ($cert) {
            $success = $this->connection->login($this->username, $cert);
          }
        }
        break;

      case Ssh::SSH_MODE_AGENT:
        $agent = new Agent();
        $success = $this->connection->login($this->username, $agent);
        break;

    }

    if (empty($success)) {
      throw new \RuntimeException('SSH authentication failed.');
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exec(string $command): bool {
    /** @var \phpseclib3\Net\SSH2 $connection */
    $connection = $this->connection;
    $this->output = $connection->exec($command);
    if (empty($this->output)) {
      $this->error = implode(PHP_EOL, $connection->getErrors());
      return FALSE;
    }
    return TRUE;
  }

}
