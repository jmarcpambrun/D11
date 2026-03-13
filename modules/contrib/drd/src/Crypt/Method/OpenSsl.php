<?php

namespace Drupal\drd\Crypt\Method;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Crypt\BaseMethod;
use Drupal\drd\Crypt\BaseMethodInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides OpenSSL encryption functionality.
 *
 * @ingroup drd
 */
class OpenSsl extends BaseMethod {

  /**
   * The cypher.
   *
   * @var mixed|string|null
   */
  private mixed $cipher;

  /**
   * The initialization vector.
   *
   * @var string
   */
  private string $iv;

  /**
   * The password.
   *
   * @var mixed|string
   */
  private mixed $password;

  /**
   * List of supported ciphers.
   *
   * @var array|int[]
   */
  private array $supportedCipher = [
    'aes-256-ctr' => 32,
    'aes-128-cbc' => 16,
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(ContainerInterface $container, array $settings = []) {
    parent::__construct($container);
    $this->cipher = $settings['cipher'] ?? $this->getDefaultCipher();
    $this->password = $settings['password'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'OpenSSL';
  }

  /**
   * {@inheritdoc}
   */
  public function getCipher(): string {
    return $this->cipher;
  }

  /**
   * {@inheritdoc}
   */
  public function getPassword(): string {
    return base64_decode($this->password);
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return function_exists('openssl_encrypt');
  }

  /**
   * Get the default cipher which is the first from the list of available ones.
   */
  private function getDefaultCipher(): string {
    $ciphers = $this->getCipherMethods();
    return empty($ciphers) ? '' : array_shift($ciphers);
  }

  /**
   * Calculate key length for the selected cipher.
   */
  private function getKeyLength(): int {
    return $this->supportedCipher[$this->cipher] ?? 32;
  }

  /**
   * {@inheritdoc}
   */
  public function getCipherMethods(): array {
    $result = [];
    $available = openssl_get_cipher_methods();
    foreach ($this->supportedCipher as $cipher => $keyLength) {
      if (in_array($cipher, $available, TRUE)) {
        $result[$cipher] = $cipher;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, array $condition): void {
    $form['openssl_cipher'] = [
      '#type' => 'select',
      '#title' => t('Cipher'),
      '#options' => $this->getCipherMethods(),
      '#default_value' => $this->cipher,
      '#states' => [
        'required' => $condition,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValues(FormStateInterface $form_state): array {
    $cipher = $form_state->getValue('openssl_cipher');
    $reset = (empty($this->password) ||
      $cipher !== $this->cipher);
    $this->cipher = $cipher;
    if ($reset) {
      $this->resetPassword();
    }

    return $this->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function resetPassword(): BaseMethodInterface {
    $this->password = $this->generatePassword($this->getKeyLength());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $settings = [
      'cipher' => $this->cipher,
      'password' => $this->password,
    ];
    $this->encryption->encrypt($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getIv(): string {
    /* @noinspection DuplicatedCode */
    if (empty($this->iv)) {
      $nonceSize = openssl_cipher_iv_length($this->cipher);
      $strong = TRUE;
      /* @noinspection CryptographicallySecureRandomnessInspection */
      $this->iv = openssl_random_pseudo_bytes($nonceSize, $strong);
      if (!$strong || !$this->iv) {
        $this->logger->warning('Your systm does not produce secure randomness.');
      }
    }
    return $this->iv;
  }

  /**
   * {@inheritdoc}
   */
  public function encrypt(array $args): string {
    return empty($this->password) ?
      '' :
      openssl_encrypt(
        serialize($args),
        $this->cipher,
        $this->getPassword(),
        OPENSSL_RAW_DATA,
        $this->getIv()
      );
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt(string $body, string $iv): mixed {
    $this->iv = $iv;
    $data = openssl_decrypt(
      $body,
      $this->cipher,
      $this->getPassword(),
      OPENSSL_RAW_DATA,
      $this->iv
    );
    /* @noinspection UnserializeExploitsInspection */
    $result = unserialize($data);
    if ($result === FALSE) {
      $data = str_replace([';r:', ';R:'], ';i:', $data);
      /* @noinspection UnserializeExploitsInspection */
      $result = unserialize($data);
    }
    return $result;
  }

}

if (!defined('OPENSSL_RAW_DATA')) {
  define('OPENSSL_RAW_DATA', 1);
}
