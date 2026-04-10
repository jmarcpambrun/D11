<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Major Version entities.
 *
 * @ingroup drd
 */
interface MajorInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Get language code of the major.
   *
   * @return string
   *   Language code.
   */
  public function getLangCode(): string;

  /**
   * Gets the Major Version name.
   *
   * @return string
   *   Name of the Major Version.
   */
  public function getName(): string;

  /**
   * Sets the Major Version name.
   *
   * @param string $name
   *   The Major Version name.
   *
   * @return $this
   */
  public function setName(string $name): self;

  /**
   * Gets the Major Version coreversion.
   *
   * @return int
   *   Core version of the Major Version.
   */
  public function getCoreVersion(): int;

  /**
   * Sets the Major Version coreversion.
   *
   * @param int $coreversion
   *   The Major Version core version.
   *
   * @return $this
   */
  public function setCoreVersion(int $coreversion): self;

  /**
   * Gets the Major Version majorversion.
   *
   * @return int
   *   Major version of the Major Version.
   */
  public function getMajorVersion(): int;

  /**
   * Sets the Major Version major version.
   *
   * @param int $majorversion
   *   The Major Version major version.
   *
   * @return $this
   */
  public function setMajorVersion(int $majorversion): self;

  /**
   * Gets the Major Version project.
   *
   * @return \Drupal\drd\Entity\ProjectInterface|null
   *   Project of the Major Version.
   */
  public function getProject(): ?ProjectInterface;

  /**
   * Sets the Major Version project.
   *
   * @param ProjectInterface $project
   *   The Major Version project.
   *
   * @return $this
   */
  public function setProject(ProjectInterface $project): self;

  /**
   * Gets the Major Version parent project.
   *
   * @return \Drupal\drd\Entity\ProjectInterface|null
   *   Parent project of the Major Version.
   */
  public function getParentProject(): ?ProjectInterface;

  /**
   * Sets the Major Version parent project.
   *
   * @param ProjectInterface $project
   *   The Major Version parent project.
   *
   * @return $this
   */
  public function setParentProject(ProjectInterface $project): self;

  /**
   * Gets the Major Version recommended release.
   *
   * @return \Drupal\drd\Entity\ReleaseInterface|null
   *   Recommended release of the Major Version.
   */
  public function getRecommendedRelease(): ?ReleaseInterface;

  /**
   * Sets the Major Version recommended release.
   *
   * @param ReleaseInterface $release
   *   The Major Version recommended release.
   *
   * @return $this
   */
  public function setRecommendedRelease(ReleaseInterface $release): self;

  /**
   * Gets the Major Version creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Major Version.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the Major Version creation timestamp.
   *
   * @param int $timestamp
   *   The Major Version creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): self;

  /**
   * Returns the Major Version published status indicator.
   *
   * Unpublished Major Version are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Major Version is published.
   */
  public function isPublished(): bool;

  /**
   * Sets the published status of a Major Version.
   *
   * @param bool $published
   *   TRUE to set this Major Version to published, FALSE otherwise.
   *
   * @return $this
   */
  public function setPublished(bool $published): self;

  /**
   * Returns the Major Version hidden status indicator.
   *
   * Hidden Major Version will not be checked for update status.
   *
   * @return bool
   *   TRUE if the Major Version is hidden.
   */
  public function isHidden(): bool;

  /**
   * Sets the hidden status of a Major Version.
   *
   * @param bool $hidden
   *   TRUE to set this Major Version to hidden, FALSE otherwise (default).
   *
   * @return $this
   */
  public function setHidden(bool $hidden): self;

  /**
   * Returns the Major Version supported status indicator.
   *
   * Unsupported Major Version will raise warnings.
   *
   * @return bool
   *   TRUE if the Major Version is supported.
   */
  public function isSupported(): bool;

  /**
   * Sets the supported status of a Major Version.
   *
   * @param bool $supported
   *   TRUE to set this Major Version to supported (default), FALSE otherwise.
   *
   * @return $this
   */
  public function setSupported(bool $supported): self;

  /**
   * Update project status.
   *
   * Collect all update statuses of installed releases of this major and write
   * their aggregated values into this major's db record.
   *
   * @return $this
   */
  public function updateStatus(): self;

  /**
   * Create new or return existing major entity.
   *
   * @param string $type
   *   Project type.
   * @param string $name
   *   Project name.
   * @param string $version
   *   Major version.
   *
   * @return \Drupal\drd\Entity\MajorInterface
   *   The major entity.
   */
  public static function findOrCreate(string $type, string $name, string $version): MajorInterface;

  /**
   * Find existing major entity.
   *
   * @param string $name
   *   Project name.
   * @param string $version
   *   Major version.
   *
   * @return \Drupal\drd\Entity\MajorInterface|bool
   *   The major entity, or False if not found.
   */
  public static function find(string $name, string $version): bool|MajorInterface;

}
