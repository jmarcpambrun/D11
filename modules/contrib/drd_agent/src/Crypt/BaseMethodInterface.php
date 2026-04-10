<?php

namespace Drupal\drd_agent\Crypt;

/**
 * Provides an interface for encryption methods.
 *
 * @ingroup drd
 */
interface BaseMethodInterface {

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
   * Get an initialization vector.
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
   * @return bool|string
   *   The password or FALSE otherwise.
   */
  public function getPassword(): bool|string;

  /**
   * Encrypt and encode any list of arguments.
   *
   * @param array $args
   *   The arguments to be encrypted.
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
   *   The initialization vector.
   *
   * @return mixed
   *   The decoded, decrypted and deserialized arguments.
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

}
