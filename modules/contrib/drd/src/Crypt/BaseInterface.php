<?php

namespace Drupal\drd\Crypt;

/**
 * Provides an interface for encryption.
 *
 * @ingroup drd
 */
interface BaseInterface {

  /**
   * Create instance of a crypt object of given method with provided settings.
   *
   * @param string $method
   *   ID of the crypt method.
   * @param array $settings
   *   Settings of the crypt instance.
   *
   * @return BaseMethodInterface
   *   The crypt object.
   */
  public static function getInstance(string $method, array $settings): BaseMethodInterface;

  /**
   * Get a list of crypt methods, either just their ids or instances of each.
   *
   * @param bool $instances
   *   Whether to receive ids (FALSE) or instances (TRUE).
   *
   * @return array
   *   List of crypt methods.
   */
  public static function getMethods(bool $instances = FALSE): array;

  /**
   * Count the number of methods that both end support.
   *
   * @param array|null $remote
   *   List of supported remote crypt methods.
   *
   * @return int
   *   Number of common crapt methods.
   */
  public static function countAvailableMethods(?array $remote = NULL): int;

}
