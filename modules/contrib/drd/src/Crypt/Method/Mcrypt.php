<?php

namespace Drupal\drd\Crypt\Method;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Crypt\BaseMethod;
use Drupal\drd\Crypt\BaseMethodInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Mcrypt encryption functionality.
 *
 * @ingroup drd
 */
class Mcrypt extends BaseMethod {

  /**
   * The cipher.
   *
   * @var mixed|string
   */
  private mixed $cipher;

  /**
   * The encryption mode.
   *
   * @var mixed|string
   */
  private mixed $mode;

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
   * {@inheritdoc}
   */
  public function __construct(ContainerInterface $container, array $settings = []) {
    parent::__construct($container);
    $this->cipher = $settings['cipher'] ?? 'rijndael-256';
    $this->mode = $settings['mode'] ?? 'cbc';
    $this->password = $settings['password'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'MCrypt';
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
    return function_exists('mcrypt_encrypt');
  }

  /**
   * {@inheritdoc}
   */
  public function getCipherMethods(): array {
    return [
      'rijndael-128',
      'rijndael-192',
      'rijndael-256',
    ];
  }

  /**
   * Get list of mcrpyt modes.
   */
  public function getModes(): array {
    return [
      'ecb',
      'cbc',
      'cfb',
      'ofb',
      'nofb',
      'stream',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, array $condition): void {
    $form['mcrypt_cipher'] = [
      '#type' => 'select',
      '#title' => t('Cipher'),
      '#options' => $this->getCipherMethods(),
      '#default_value' => array_search($this->cipher, $this->getCipherMethods(), TRUE),
      '#states' => [
        'required' => $condition,
      ],
    ];
    $form['mcrypt_mode'] = [
      '#type' => 'select',
      '#title' => t('Mode'),
      '#options' => $this->getModes(),
      '#default_value' => array_search($this->mode, $this->getModes(), TRUE),
      '#states' => [
        'required' => $condition,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValues(FormStateInterface $form_state): array {
    $ciphers = $this->getCipherMethods();
    $modes = $this->getModes();

    $cipher = $ciphers[$form_state->getValue('mcrypt_cipher')];
    $mode = $modes[$form_state->getValue('mcrypt_mode')];
    $reset = (empty($this->password) ||
      $cipher !== $this->cipher ||
      $mode !== $this->mode);
    $this->cipher = $cipher;
    $this->mode = $mode;
    if ($reset) {
      $this->resetPassword();
    }

    return $this->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function resetPassword(): BaseMethodInterface {
    if (function_exists('mcrypt_get_key_size')) {
      $this->password = $this->generatePassword(@mcrypt_get_key_size($this->cipher, $this->mode));
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $settings = [
      'cipher' => $this->cipher,
      'mode' => $this->mode,
      'password' => $this->password,
    ];
    $this->encryption->encrypt($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getIv(): string {
    if (empty($this->iv) && function_exists('mcrypt_get_iv_size')) {
      $nonceSize = mcrypt_get_iv_size($this->cipher, $this->mode);
      if (function_exists('mcrypt_create_iv')) {
        /* @noinspection CryptographicallySecureRandomnessInspection */
        $this->iv = mcrypt_create_iv($nonceSize);
      }
    }
    return $this->iv;
  }

  /**
   * {@inheritdoc}
   */
  public function encrypt(array $args): string {
    if (function_exists('mcrypt_encrypt')) {
      return mcrypt_encrypt(
        $this->cipher,
        $this->getPassword(),
        serialize($args),
        $this->mode,
        $this->getIv()
      );
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt(string $body, string $iv): mixed {
    $this->iv = $iv;
    if (function_exists('mcrypt_decrypt')) {
      /* @noinspection UnserializeExploitsInspection */
      return unserialize(mcrypt_decrypt(
        $this->cipher,
        $this->getPassword(),
        $body,
        $this->mode,
        $this->iv
      ));
    }
    return '';
  }

}
