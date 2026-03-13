<?php

namespace Drupal\drd;

/**
 * Interface for DRD entities queries service.
 */
interface SelectEntitiesInterface {

  /**
   * Retrievs a list of set selection criteria for this service.
   *
   * @return array
   *   An array containing all the set selection criteria.
   */
  public function getSelectionCriteria(): array;

  /**
   * Set the tag name to search for.
   *
   * @param string|null $name
   *   The tag name.
   *
   * @return $this
   */
  public function setTag(?string $name): self;

  /**
   * Set the host name to search for.
   *
   * @param string|null $name
   *   The host name.
   *
   * @return $this
   */
  public function setHost(?string $name): self;

  /**
   * Set the host ID to search for.
   *
   * @param int|null $id
   *   The host id.
   *
   * @return $this
   */
  public function setHostId(?int $id): self;

  /**
   * Set the core name to search for.
   *
   * @param string|null $name
   *   The core name.
   *
   * @return $this
   */
  public function setCore(?string $name): self;

  /**
   * Set the core ID to search for.
   *
   * @param int|null $id
   *   The core id.
   *
   * @return $this
   */
  public function setCoreId(?int $id): self;

  /**
   * Set the domain to search for.
   *
   * @param string|null $domain
   *   The domain.
   *
   * @return $this
   */
  public function setDomain(?string $domain): self;

  /**
   * Set the domain ID to search for.
   *
   * @param int|null $id
   *   The domain id.
   *
   * @return $this
   */
  public function setDomainId(?int $id): self;

  /**
   * Get selected hosts.
   *
   * @return \Drupal\drd\Entity\HostInterface[]|false
   *   The selected hosts.
   */
  public function hosts(): array|bool;

  /**
   * Get selected cores.
   *
   * @return \Drupal\drd\Entity\CoreInterface[]|false
   *   The selected cores.
   */
  public function cores(): array|bool;

  /**
   * Get selected domains.
   *
   * @return \Drupal\drd\Entity\DomainInterface[]|false
   *   The selected domains.
   */
  public function domains(): array|bool;

}
