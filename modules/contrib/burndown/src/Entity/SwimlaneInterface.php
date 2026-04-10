<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Swimlane entities.
 *
 * @ingroup burndown
 */
interface SwimlaneInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Swimlane name.
   *
   * @return string
   *   Name of the Swimlane.
   */
  public function getName();

  /**
   * Sets the Swimlane name.
   *
   * @param string $name
   *   The Swimlane name.
   *
   * @return \Drupal\burndown\Entity\SwimlaneInterface
   *   The called Swimlane entity.
   */
  public function setName($name);

  /**
   * Gets the Swimlane creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Swimlane.
   */
  public function getCreatedTime();

  /**
   * Sets the Swimlane creation timestamp.
   *
   * @param int $timestamp
   *   The Swimlane creation timestamp.
   *
   * @return \Drupal\burndown\Entity\SwimlaneInterface
   *   The called Swimlane entity.
   */
  public function setCreatedTime($timestamp);

}
