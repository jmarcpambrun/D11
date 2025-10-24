<?php

namespace Drupal\eca_webform\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Provides access rules event for eca_webform.
 *
 * @package Drupal\eca_webform\Event
 */
class AccessRules extends Event implements WebformEventInterface {

  /**
   * The access rules.
   *
   * @var array
   */
  protected array $accessRules = [];

  /**
   * The access rules.
   *
   * @return array
   *   The access rules.
   */
  public function getAccessRules(): array {
    return $this->accessRules;
  }

  /**
   * The access rules.
   *
   * @param array $accessRules
   *   The access rules.
   */
  public function setAccessRules(array $accessRules): void {
    $this->accessRules = $accessRules;
  }

}
