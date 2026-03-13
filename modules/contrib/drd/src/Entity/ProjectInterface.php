<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Url;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Project entities.
 *
 * @ingroup drd
 */
interface ProjectInterface extends UpdateStatusInterface, ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Get language code of the project.
   *
   * @return string
   *   Language code.
   */
  public function getLangCode(): string;

  /**
   * Gets the Project label.
   *
   * @return string
   *   Label of the Project.
   */
  public function getLabel(): string;

  /**
   * Sets the Project label.
   *
   * @param string $label
   *   The Project label.
   *
   * @return $this
   */
  public function setLabel(string $label): self;

  /**
   * Gets the Project name.
   *
   * @return string
   *   Name of the Project.
   */
  public function getName(): string;

  /**
   * Sets the Project name.
   *
   * @param string $name
   *   The Project name.
   *
   * @return $this
   */
  public function setName(string $name): self;

  /**
   * Gets the Project type.
   *
   * @return string
   *   Name of the Project.
   */
  public function getType(): string;

  /**
   * Sets the Project type.
   *
   * @param string $type
   *   The Project type.
   *
   * @return $this
   */
  public function setType(string $type): self;

  /**
   * Gets the Project creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Project.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the Project creation timestamp.
   *
   * @param int $timestamp
   *   The Project creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): self;

  /**
   * Returns the Project published status indicator.
   *
   * Unpublished Project are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Project is published.
   */
  public function isPublished(): bool;

  /**
   * Sets the published status of a Project.
   *
   * @param bool $published
   *   TRUE to set this Project to published, FALSE to set it to unpublished.
   *
   * @return $this
   */
  public function setPublished(bool $published): self;

  /**
   * Get project's URL on drupal.org.
   *
   * @return \Drupal\Core\Url
   *   The project url.
   */
  public function getProjectLink(): Url;

  /**
   * Create new or return existing project entity.
   *
   * @param string $type
   *   The project type.
   * @param string $name
   *   The project name.
   *
   * @return \Drupal\drd\Entity\ProjectInterface
   *   The project entity.
   */
  public static function findOrCreate(string $type, string $name): ProjectInterface;

  /**
   * Find existing project entity.
   *
   * @param string $name
   *   The project name.
   *
   * @return \Drupal\drd\Entity\ProjectInterface|bool
   *   The project entity or False if not found.
   */
  public static function find(string $name): ProjectInterface|bool;

}
