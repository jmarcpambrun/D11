<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining DrdCore and DrdDomain entities.
 *
 * @ingroup drd
 */
interface BaseInterface extends EntityInterface, ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Host/Core/Domain name.
   *
   * @param bool $fallbackToDomain
   *   Whether to use the domain name if entity doesn'T have a name yet.
   *
   * @return string
   *   Name of the Host/Core/Domain.
   */
  public function getName(bool $fallbackToDomain = TRUE): string;

  /**
   * Sets the Host/Core/Domain name.
   *
   * @param string|null $name
   *   The Host/Core/Domain name.
   *
   * @return $this
   */
  public function setName(?string $name): self;

  /**
   * Gets the Host/Core/Domain creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Host/Core/Domain.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the Host/Core/Domain creation timestamp.
   *
   * @param int $timestamp
   *   The Host/Core/Domain creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): self;

  /**
   * Returns the Host/Core/Domain published status indicator.
   *
   * Unpublished Host/Core/Domain are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Host/Core/Domain is published.
   */
  public function isPublished(): bool;

  /**
   * Sets the published status of a Host/Core/Domain.
   *
   * @param bool $published
   *   TRUE to set this Host/Core/Domain to published, FALSE to set it to
   *   unpublished.
   *
   * @return $this
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setPublished(bool $published): self;

  /**
   * Get header array for an entity.
   *
   * Returns the header values applicable to this remote entity. For hosts,
   * that's just their own header values, for core this is their host's plus
   * their own headers and finally for domains, this is their core's plus their
   * own headers so that we get the aggregat that can be used for remote
   * requests.
   *
   * @return array
   *   Accumulated associative array of header keys and values.
   */
  public function getHeader(): array;

}
