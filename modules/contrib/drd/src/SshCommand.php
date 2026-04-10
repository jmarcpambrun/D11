<?php

namespace Drupal\drd;

use Drupal\drd\Entity\DomainInterface;

/**
 * Provides services for SSH commands.
 *
 * @package Drupal\drd
 */
class SshCommand {

  /**
   * DRD domain entity.
   *
   * @var \Drupal\drd\Entity\DomainInterface
   */
  protected DomainInterface $domain;

  /**
   * SSH connection.
   *
   * @var SshInterface
   */
  protected SshInterface $connection;

  /**
   * SSH command.
   *
   * @var string
   */
  protected string $command = '';

  /**
   * Set the DRD domain entity.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   The domain entity.
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function setDomain(DomainInterface $domain): self {
    $this->domain = $domain;
    $this->initConnection();
    return $this;
  }

  /**
   * Set the SSH command.
   *
   * @param string $command
   *   The command.
   *
   * @return $this
   */
  public function setCommand(string $command): self {
    $this->command = $command;
    return $this;
  }

  /**
   * Get the SSH output.
   *
   * @return string
   *   The output.
   */
  public function getOutput(): string {
    return $this->connection->getOutput();
  }

  /**
   * Execute the SSH command.
   *
   * @return bool
   *   TRUE, if command executed successfully.
   */
  public function execute(): bool {
    return $this->connection->exec($this->command);
  }

  /**
   * Initialize SSH connection.
   *
   * @throws \RuntimeException
   * @throws \Exception
   */
  private function initConnection(): void {
    $host = NULL;
    if ($core = $this->domain->getCore()) {
      $host = $core->getHost();
    }
    if ($host === NULL || empty($host->supportsSsh())) {
      throw new \RuntimeException('SSH for this host is disabled.');
    }

    $settings = $host->getSshSettings();

    if (!empty($settings['host'])) {
      $host = $settings['host'];
    }
    else {
      $host = $this->domain->getDomainName();
    }

    if (!function_exists('ssh2_connect')) {
      $this->connection = new SshPhp(
        $host,
        $settings['port'],
        $settings['auth']['mode'],
        $settings['auth']['username'],
        $settings['auth']['password'],
        $settings['auth']['file_public_key'],
        $settings['auth']['file_private_key'],
        $settings['auth']['key_secret']
      );
    }
    else {
      $this->connection = new SshLibSec(
        $host,
        $settings['port'],
        $settings['auth']['mode'],
        $settings['auth']['username'],
        $settings['auth']['password'],
        $settings['auth']['file_public_key'],
        $settings['auth']['file_private_key'],
        $settings['auth']['key_secret']
      );
    }

    if (!$this->connection->login()) {
      throw new \RuntimeException('SSH authentication failed.');
    }
  }

}
