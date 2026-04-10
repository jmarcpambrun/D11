<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Sprint entities.
 *
 * @ingroup burndown
 */
interface SprintInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Sprint name.
   *
   * @return string
   *   Name of the Sprint.
   */
  public function getName();

  /**
   * Sets the Sprint name.
   *
   * @param string $name
   *   The Sprint name.
   *
   * @return \Drupal\burndown\Entity\SprintInterface
   *   The called Sprint entity.
   */
  public function setName($name);

  /**
   * Gets the Sprint creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Sprint.
   */
  public function getCreatedTime();

  /**
   * Sets the Sprint creation timestamp.
   *
   * @param int $timestamp
   *   The Sprint creation timestamp.
   *
   * @return \Drupal\burndown\Entity\SprintInterface
   *   The called Sprint entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Sprint revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Sprint revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\burndown\Entity\SprintInterface
   *   The called Sprint entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Sprint revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Sprint revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\burndown\Entity\SprintInterface
   *   The called Sprint entity.
   */
  public function setRevisionUserId($uid);

}
