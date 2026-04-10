<?php

namespace Drupal\drd;

/**
 * Provides helper functions for SSH services.
 *
 * @package Drupal\drd
 */
abstract class Ssh implements SshInterface {

  public const SSH_MODE_USERNAME_PASSWORD = 1;
  public const SSH_MODE_KEY = 2;
  public const SSH_MODE_AGENT = 3;

  /**
   * The SSH connection.
   *
   * @var \phpseclib3\Net\SSH2|resource|false
   */
  protected mixed $connection;

  /**
   * The host name.
   *
   * @var string
   */
  protected string $hostname;

  /**
   * The SSH port.
   *
   * @var int
   */
  protected int $port;

  /**
   * Connection mode, SSH_MODE_USERNAME_PASSWORD|SSH_MODE_KEY|SSH_MODE_AGENT.
   *
   * @var string
   */
  protected string $mode;

  /**
   * The username.
   *
   * @var string
   */
  protected string $username;

  /**
   * The password.
   *
   * @var string
   */
  protected string $password;

  /**
   * Filename for the public key.
   *
   * @var string
   */
  protected string $pubKeyFile;

  /**
   * Filename for the private key.
   *
   * @var string
   */
  protected string $privKeyFile;

  /**
   * Passphrase for the private key.
   *
   * @var string
   */
  protected string $passphrase;

  /**
   * The output from SSH session.
   *
   * @var string
   */
  protected string $output = '';

  /**
   * The error output from SSH session.
   *
   * @var string
   */
  protected string $error = '';

  /**
   * Construct a SSH object.
   *
   * @param string $hostname
   *   The host name to connect to.
   * @param int $port
   *   The SSH port.
   * @param string $mode
   *   Connection mode, SSH_MODE_USERNAME_PASSWORD|SSH_MODE_KEY|SSH_MODE_AGENT.
   * @param string $username
   *   The username.
   * @param string $password
   *   The password.
   * @param string $pubKeyFile
   *   Filename for the public key.
   * @param string $privKeyFile
   *   Filename for the private key.
   * @param string $passphrase
   *   Passphrase for the private key.
   */
  public function __construct(string $hostname, int $port, string $mode, string $username, string $password, string $pubKeyFile, string $privKeyFile, string $passphrase) {
    $this->hostname = $hostname;
    $this->port = $port;
    $this->mode = $mode;
    $this->username = $username;
    $this->password = $password;
    $this->pubKeyFile = $pubKeyFile;
    $this->privKeyFile = $privKeyFile;
    $this->passphrase = $passphrase;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutput(): string {
    return $this->output;
  }

  /**
   * {@inheritdoc}
   */
  public function getError(): string {
    return $this->error;
  }

}
