<?php

namespace Drupal\burndown;

use Drupal\burndown\Entity\ProjectInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the storage handler class for Project entities.
 *
 * This extends the base storage class, adding required special handling for
 * Project entities.
 *
 * @ingroup burndown
 */
interface ProjectStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Project revision IDs for a specific Project.
   *
   * @param \Drupal\burndown\Entity\ProjectInterface $entity
   *   The Project entity.
   *
   * @return int[]
   *   Project revision IDs (in ascending order).
   */
  public function revisionIds(ProjectInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Project author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Project revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\burndown\Entity\ProjectInterface $entity
   *   The Project entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(ProjectInterface $entity);

  /**
   * Unsets the language for all Project with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
