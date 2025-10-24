<?php

namespace Drupal\drd;

/**
 * Provides services for SSH commands.
 *
 * @package Drupal\drd
 */
class SshPhp extends Ssh {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function login(): bool {
    $this->connection = @ssh2_connect($this->hostname, $this->port);
    if (!$this->connection) {
      throw new \RuntimeException('SSH connection not possible.');
    }
    switch ($this->mode) {
      case 1:
        $success = @ssh2_auth_password(
          $this->connection,
          $this->username,
          $this->password
        );
        break;

      case 2:
        $success = @ssh2_auth_pubkey_file(
          $this->connection,
          $this->username,
          $this->pubKeyFile,
          $this->privKeyFile,
          $this->passphrase
        );
        break;

      case 3:
        if (function_exists('ssh2_auth_agent')) {
          $success = @ssh2_auth_agent(
            $this->connection,
            $this->username
          );
        }
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
    $stream = ssh2_exec($this->connection, $command);
    stream_set_blocking($stream, TRUE);
    $this->output = stream_get_contents($stream);
    $this->error = stream_get_contents(ssh2_fetch_stream($stream, SSH2_STREAM_STDERR));
    if (!empty($this->error)) {
      return FALSE;
    }
    return TRUE;
  }

}

if (!defined('SSH2_STREAM_STDERR')) {
  define('SSH2_STREAM_STDERR', 1);
}
