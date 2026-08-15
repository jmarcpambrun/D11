<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Project entities.
 *
 * @ingroup burndown
 */
interface ProjectInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Project name.
   *
   * @return string
   *   Name of the Project.
   */
  public function getName();

  /**
   * Sets the Project name.
   *
   * @param string $name
   *   The Project name.
   *
   * @return \Drupal\burndown\Entity\ProjectInterface
   *   The called Project entity.
   */
  public function setName($name);

  /**
   * Gets the Project creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Project.
   */
  public function getCreatedTime();

  /**
   * Sets the Project creation timestamp.
   *
   * @param int $timestamp
   *   The Project creation timestamp.
   *
   * @return \Drupal\burndown\Entity\ProjectInterface
   *   The called Project entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Project revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Project revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\burndown\Entity\ProjectInterface
   *   The called Project entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Project revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Project revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\burndown\Entity\ProjectInterface
   *   The called Project entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Returns whether this project uses sprint boards.
   *
   * @return bool
   *   TRUE for sprint projects, FALSE otherwise.
   */
  public function isSprint();

  /**
   * Gets the project shortcode.
   *
   * @return string
   *   The project shortcode.
   */
  public function getShortcode();

  /**
   * Gets the estimation mode for this project.
   *
   * @return string
   *   The estimate type value.
   */
  public function getEstimateType();

  /**
   * Gets available estimate values for this project.
   *
   * @return array
   *   Estimate options keyed by machine value.
   */
  public function getEstimateSizes();

}
