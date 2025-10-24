<?php

namespace Drupal\burndown;

use Drupal\burndown\Entity\SprintInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the storage handler class for Sprint entities.
 *
 * This extends the base storage class, adding required special handling for
 * Sprint entities.
 *
 * @ingroup burndown
 */
interface SprintStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Sprint revision IDs for a specific Sprint.
   *
   * @param \Drupal\burndown\Entity\SprintInterface $entity
   *   The Sprint entity.
   *
   * @return int[]
   *   Sprint revision IDs (in ascending order).
   */
  public function revisionIds(SprintInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Sprint author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Sprint revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\burndown\Entity\SprintInterface $entity
   *   The Sprint entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(SprintInterface $entity);

  /**
   * Unsets the language for all Sprint with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
