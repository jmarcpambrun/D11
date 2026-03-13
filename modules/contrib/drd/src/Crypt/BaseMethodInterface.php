<?php

namespace Drupal\drd\Crypt;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface for encryption methods.
 *
 * @ingroup drd
 */
interface BaseMethodInterface {

  /**
   * Whether the crypt method requires authentication before decryption.
   *
   * @return bool
   *   TRUE if authentication is required before decryption.
   */
  public function authBeforeDecrypt(): bool;

  /**
   * Can be overwritten and determines if the CryptMethod works with a password.
   *
   * @return bool
   *   TRUE if password is required.
   */
  public function requiresPassword(): bool;

  /**
   * Reset the crypt method password to force the generation of a new one.
   *
   * @return $this
   */
  public function resetPassword(): self;

  /**
   * Get the crypt settings.
   *
   * @return array
   *   The settings.
   */
  public function getSettings(): array;

  /**
   * Get the crypt method label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Find out if the crypt method is available.
   *
   * @return bool
   *   TRUE if method is available.
   */
  public function isAvailable(): bool;

  /**
   * Get a list of available cipher methods.
   *
   * @return array
   *   List of methods.
   */
  public function getCipherMethods(): array;

  /**
   * Add settings container into an existing form.
   *
   * @param array $form
   *   The form.
   * @param array $condition
   *   A list of conditions that can be used for visibility of components.
   */
  public function settingsForm(array &$form, array $condition): void;

  /**
   * Retrieve values from settings form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   The settings.
   */
  public function settingsFormValues(FormStateInterface $form_state): array;

  /**
   * Get an initialiation vector.
   *
   * @return string
   *   The IV.
   */
  public function getIv(): string;

  /**
   * Get the selected cipher.
   *
   * @return string|bool
   *   The cipher.
   */
  public function getCipher(): bool|string;

  /**
   * Get the password.
   *
   * @return string
   *   The password.
   */
  public function getPassword(): string;

  /**
   * Encrypt and encode any list of arguments.
   *
   * @param array $args
   *   The arguments to be encrpyted.
   *
   * @return string
   *   Encrypted and base64 encoded serialisation of the arguments.
   */
  public function encrypt(array $args): string;

  /**
   * Decode, decrypt and unserialize arguments from the other end.
   *
   * @param string $body
   *   The encrypted, serialized and encoded string to process.
   * @param string $iv
   *   The initialiation vector.
   *
   * @return mixed
   *   The decoded, decrypted and unserialized arguments.
   */
  public function decrypt(string $body, string $iv): mixed;

  /**
   * Encrypt a file.
   *
   * @param string $filename
   *   Filename which should be encrypted.
   *
   * @return string
   *   Filename of the encrypted version.
   */
  public function encryptFile(string $filename): string;

  /**
   * Decrypt a file.
   *
   * @param string $filename
   *   Filename which should be decrypted.
   *
   * @return string
   *   Filename of the decrypted version.
   */
  public function decryptFile(string $filename): string;

}
