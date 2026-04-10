<?php

namespace Drupal\drd\Crypt;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\drd\Encryption;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base encryption method.
 *
 * @ingroup drd
 */
abstract class BaseMethod implements BaseMethodInterface {

  /**
   * The Drupal container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected Messenger $messenger;

  /**
   * The encryption service.
   *
   * @var \Drupal\drd\Encryption
   */
  protected Encryption $encryption;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected ModuleHandler $moduleHandler;

  /**
   * BaseMethod constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal container.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
    $this->logger = $container->get('logger.factory')->get('DRD');
    $this->messenger = $container->get('messenger');
    $this->encryption = $container->get('drd.encrypt');
    $this->moduleHandler = $container->get('module_handler');
  }

  /**
   * {@inheritdoc}
   */
  public function authBeforeDecrypt(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresPassword(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resetPassword(): BaseMethodInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    return [];
  }

  /**
   * Returns base64 encoded random bytes of the given length.
   *
   * @param int $length
   *   Length of the password to be generated.
   *
   * @return string
   *   Base64 encoded random password.
   */
  protected function generatePassword(int $length): string {
    try {
      $randomBytes = random_bytes($length);
    }
    catch (\Exception) {
      $this->messenger->addError(t('Your system does not provide real good random data, hence you should fix that first before you continue with DRD!'));
      return '';
    }
    return base64_encode($randomBytes);
  }

  /**
   * Callback to encrypt and decrypt files.
   *
   * @param string $mode
   *   This is "-e" to encrypt or "-d" to decrypt.
   * @param string $in
   *   Input filename.
   * @param string $out
   *   Output filename.
   *
   * @return int
   *   Exit code of the openssl command.
   */
  private function cryptFileExecute(string $mode, string $in, string $out): int {
    $output = [];
    $cmd = [
      'openssl',
      $this->getCipher(),
      $mode,
      '-a',
      '-salt',
      '-in',
      $in,
      '-out',
      $out,
      '-k',
      base64_encode($this->getPassword()),
    ];
    exec(implode(' ', $cmd), $output, $ret);
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function encryptFile(string $filename): string {
    if ($this->getCipher()) {
      exec('openssl version', $output, $ret);
      if ($ret === 0) {
        $in = $filename;
        $filename .= '.openssl';
        if ($this->cryptFileExecute('-e', $in, $filename) !== 0) {
          $filename = $in;
        }
      }
    }
    return $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function decryptFile(string $filename): string {
    if ((pathinfo($filename, PATHINFO_EXTENSION) === 'openssl') && $this->getCipher()) {
      exec('openssl version', $output, $ret);
      if ($ret === 0) {
        $in = $filename;
        $filename = substr($in, 0, -8);
        if ($this->cryptFileExecute('-d', $in, $filename) !== 0) {
          $filename = $in;
        }
      }
    }
    return $filename;
  }

}
