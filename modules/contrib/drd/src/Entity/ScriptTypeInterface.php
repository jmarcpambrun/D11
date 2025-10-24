<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Script Type entities.
 */
interface ScriptTypeInterface extends ConfigEntityInterface {

  /**
   * Get script type interpreter.
   *
   * @return string
   *   The script type interpreter.
   */
  public function interpreter(): string;

  /**
   * Get script type extension.
   *
   * @return string
   *   The script type extension.
   */
  public function extension(): string;

  /**
   * Get script type prefix.
   *
   * @return string
   *   The script type prefix.
   */
  public function prefix(): string;

  /**
   * Get script type suffix.
   *
   * @return string
   *   The script type suffix.
   */
  public function suffix(): string;

  /**
   * Get script type line prefix.
   *
   * @return string
   *   The script type line prefix.
   */
  public function lineprefix(): string;

}
