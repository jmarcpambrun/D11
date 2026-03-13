<?php

namespace Drupal\burndown;

use Drupal\burndown\Entity\ProjectInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
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
class ProjectStorage extends SqlContentEntityStorage implements ProjectStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(ProjectInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {burndown_project_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {burndown_project_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(ProjectInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {burndown_project_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('burndown_project_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
