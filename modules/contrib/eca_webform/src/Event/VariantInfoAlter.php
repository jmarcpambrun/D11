<?php

namespace Drupal\eca_webform\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Provides variant info alter event for eca_webform.
 *
 * @package Drupal\eca_webform\Event
 */
class VariantInfoAlter extends Event implements WebformEventInterface {

  /**
   * Array of webform variants, keyed on the machine-readable handler name.
   *
   * @var array
   */
  protected array $variants;

  /**
   * Constructs the VariantsInfoAlter event.
   *
   * @param array $variants
   *   Array of webform variants, keyed on the machine-readable handler name.
   */
  public function __construct(array &$variants) {
    $this->variants = &$variants;
  }

  /**
   * The variants.
   *
   * @return array
   *   Array of webform variants, keyed on the machine-readable handler name.
   */
  public function &getVariants(): array {
    return $this->variants;
  }

}
