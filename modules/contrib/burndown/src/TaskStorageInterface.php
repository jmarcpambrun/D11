<?php

namespace Drupal\burndown;

use Drupal\burndown\Entity\TaskInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the storage handler class for Task entities.
 *
 * This extends the base storage class, adding required special handling for
 * Task entities.
 *
 * @ingroup burndown
 */
interface TaskStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Task revision IDs for a specific Task.
   *
   * @param \Drupal\burndown\Entity\TaskInterface $entity
   *   The Task entity.
   *
   * @return int[]
   *   Task revision IDs (in ascending order).
   */
  public function revisionIds(TaskInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Task author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Task revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\burndown\Entity\TaskInterface $entity
   *   The Task entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(TaskInterface $entity);

  /**
   * Unsets the language for all Task with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
