<?php

namespace Drupal\eca_webform\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Provides help info event for eca_webform.
 *
 * @package Drupal\eca_webform\Event
 */
class HelpInfo extends Event implements WebformEventInterface {

  /**
   * The help info.
   *
   * @var array
   */
  protected array $helpInfo = [];

  /**
   * The help info.
   *
   * @return array
   *   The help info.
   */
  public function getHelpInfo(): array {
    return $this->helpInfo;
  }

  /**
   * The help info.
   *
   * @param array $helpInfo
   *   The help info.
   */
  public function setHelpInfo(array $helpInfo): void {
    $this->helpInfo = $helpInfo;
  }

}
