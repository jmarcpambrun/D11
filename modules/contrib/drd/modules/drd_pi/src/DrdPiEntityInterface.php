<?php

namespace Drupal\drd_pi;

use Drupal\drd\Entity\BaseInterface;

/**
 * Provides an interface for platform based entities.
 */
interface DrdPiEntityInterface {

  /**
   * ID of the DrdPiEntity.
   *
   * @return string
   *   The ID.
   */
  public function id(): string;

  /**
   * Label of the DrdPiEntity.
   *
   * @return string
   *   The label.
   */
  public function label(): string;

  /**
   * The host this entity is attached to.
   *
   * @return DrdPiHost
   *   The host entity.
   */
  public function host(): DrdPiHost;

  /**
   * Set the matching Drd entity.
   *
   * @param \Drupal\drd\Entity\BaseInterface $entity
   *   The DRD entity.
   *
   * @return $this
   */
  public function setDrdEntity(BaseInterface $entity): self;

  /**
   * Get the matching DRD entity.
   *
   * @return \Drupal\drd\Entity\BaseInterface
   *   The DRD entity.
   */
  public function getDrdEntity(): BaseInterface;

  /**
   * Check if the DrdPiEntity has a matching DRD entity.
   *
   * @return bool
   *   TRUE if it has a matching DRD entity, FALSE otherwise.
   */
  public function hasDrdEntity(): bool;

  /**
   * Create the matching DRD entity.
   *
   * @return $this
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function create(): self;

  /**
   * Update the matching DRD entity.
   *
   * @return $this
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function update(): self;

  /**
   * Set a header key/value pair.
   *
   * @param string $key
   *   The key.
   * @param string $value
   *   The value.
   *
   * @return $this
   */
  public function setHeader(string $key, string $value): self;

}
