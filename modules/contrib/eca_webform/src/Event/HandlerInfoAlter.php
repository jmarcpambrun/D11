<?php

namespace Drupal\eca_webform\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Provides handler info alter event for eca_webform.
 *
 * @package Drupal\eca_webform\Event
 */
class HandlerInfoAlter extends Event implements WebformEventInterface {

  /**
   * Array of webform elements, keyed on the machine-readable element name.
   *
   * @var array
   */
  protected array $definitions;

  /**
   * Constructs the ElementInfoAlter event.
   *
   * @param array $definitions
   *   Array of webform elements, keyed on the machine-readable element name.
   */
  public function __construct(array &$definitions) {
    $this->definitions = &$definitions;
  }

  /**
   * The definitions.
   *
   * @return array
   *   Array of webform elements, keyed on the machine-readable element name.
   */
  public function &getDefinitions(): array {
    return $this->definitions;
  }

}
