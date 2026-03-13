<?php

namespace Drupal\drd\Entity;

use Drupal\drd\Update\PluginStorageInterface;

/**
 * Provides an interface for defining Core entities.
 *
 * @ingroup drd
 */
interface CoreInterface extends BaseInterface {

  /**
   * Get language code of the core.
   *
   * @return string
   *   Language code.
   */
  public function getLangCode(): string;

  /**
   * Get Drupal root directory of the core.
   *
   * @return string
   *   The Drupal root directory.
   */
  public function getDrupalRoot(): string;

  /**
   * Gets the Core host.
   *
   * @return \Drupal\drd\Entity\HostInterface|null
   *   Host of the Core.
   */
  public function getHost(): ?HostInterface;

  /**
   * Sets the Core drupal release.
   *
   * @param ReleaseInterface $release
   *   The Core drupal release.
   *
   * @return $this
   */
  public function setDrupalRelease(ReleaseInterface $release): self;

  /**
   * Gets the Core drupal release.
   *
   * @return \Drupal\drd\Entity\ReleaseInterface|null
   *   Drupal release of the Core.
   */
  public function getDrupalRelease(): ?ReleaseInterface;

  /**
   * Sets the URL of the Git repository.
   *
   * @param string $url
   *   The URL of the Git repository.
   *
   * @return $this
   */
  public function setGitRepo(string $url): self;

  /**
   * Gets the URL of the Git repository.
   *
   * @return string
   *   URL of the Git repository.
   */
  public function getGitRepo(): string;

  /**
   * Get the update settings for the core.
   *
   * @return array
   *   The update settings.
   */
  public function getUpdateSettings(): array;

  /**
   * Get the update plugin for the core.
   *
   * @return \Drupal\drd\Update\PluginStorageInterface
   *   The update plugin.
   *
   * @throws \Exception
   */
  public function getUpdatePlugin(): PluginStorageInterface;

  /**
   * Sets the Core host.
   *
   * @param HostInterface $host
   *   The Core host.
   *
   * @return $this
   */
  public function setHost(HostInterface $host): self;

  /**
   * Get all domains of the core.
   *
   * @param array $properties
   *   Properties for the query.
   *
   * @return \Drupal\drd\Entity\DomainInterface[]
   *   List of domains of this core.
   */
  public function getDomains(array $properties = []): array;

  /**
   * Get the first active domain for this core.
   *
   * @return \Drupal\drd\Entity\DomainInterface|null
   *   A domain entity.
   */
  public function getFirstActiveDomain(): ?DomainInterface;

  /**
   * Get all releases for which updates are available.
   *
   * @param bool $includeLocked
   *   Whether to include locked releases or not.
   * @param bool $securityOnly
   *   Whether to only include security releases or not.
   * @param bool $forceLockedSecurity
   *   Whether to locked security releases or not.
   *
   * @return \Drupal\drd\Entity\ReleaseInterface[]
   *   List of release entities.
   */
  public function getAvailableUpdates(bool $includeLocked = FALSE, bool $securityOnly = FALSE, bool $forceLockedSecurity = FALSE): array;

  /**
   * Get list of update logs.
   *
   * @return array
   *   List of update logs.
   */
  public function getUpdateLogList(): array;

  /**
   * Get update log from a specific timestamp.
   *
   * @param int $timestamp
   *   Timestamp to select which update log to return.
   *
   * @return string
   *   The update log.
   */
  public function getUpdateLog(int $timestamp): string;

  /**
   * Save a collected  update log.
   *
   * @param string $log
   *   The update log.
   *
   * @return $this
   */
  public function saveUpdateLog(string $log): self;

  /**
   * Set project releases being locked for this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface[] $releases
   *   List of locked release entities.
   *
   * @return $this
   */
  public function setLockedReleases(array $releases): self;

  /**
   * Get project releases being locked by this core.
   *
   * @return \Drupal\drd\Entity\ReleaseInterface[]
   *   List of releases.
   */
  public function getLockedReleases(): array;

  /**
   * Check if a release is locked for this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface $release
   *   The release to ckeck.
   * @param bool $checkGlobal
   *   Set to True if you also want to check global lock status.
   *
   * @return bool
   *   True if release is lock, False otherwise.
   */
  public function isReleaseLocked(ReleaseInterface $release, bool $checkGlobal = FALSE): bool;

  /**
   * Lock a release for this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface $release
   *   The release which should be locked.
   *
   * @return $this
   */
  public function lockRelease(ReleaseInterface $release): self;

  /**
   * Unlock a release for this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface $release
   *   The release which should be unlocked.
   *
   * @return $this
   */
  public function unlockRelease(ReleaseInterface $release): self;

  /**
   * Unlock all releases for this core.
   *
   * @return $this
   */
  public function unlockAllReleases(): self;

  /**
   * Set project releases being hacked on this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface[] $releases
   *   List of hacked release entities.
   *
   * @return $this
   */
  public function setHackedReleases(array $releases): self;

  /**
   * Get project releases being hacked on this core.
   *
   * @return \Drupal\drd\Entity\ReleaseInterface[]
   *   List of releases.
   */
  public function getHackedReleases(): array;

  /**
   * Check if a release is hacked on this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface $release
   *   The release to ckeck.
   *
   * @return bool
   *   True if release is lock, False otherwise.
   */
  public function isReleaseHacked(ReleaseInterface $release): bool;

  /**
   * Mark a release as hacked on this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface $release
   *   The release which should be marked as hacked.
   *
   * @return $this
   */
  public function markReleaseHacked(ReleaseInterface $release): self;

  /**
   * Mark a release as unhacked on this core.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface $release
   *   The release which should be marked as unhacked.
   *
   * @return $this
   */
  public function markReleaseUnhacked(ReleaseInterface $release): self;

}
