<?php

namespace Drupal\drd_agent\Plugin\DrdPiAuth;

/**
 * The authentication interface for DRDPi.
 */
interface DrdPiAuthInterface {

  /**
   * Validate the provided input with the local secrets.
   *
   * Should throw exceptions if a validation failed.
   *
   * @param array $input
   *   The decoded input.
   */
  public function validate(array $input): void;

}
