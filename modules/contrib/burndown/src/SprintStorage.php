<?php

namespace Drupal\burndown;

use Drupal\burndown\Entity\SprintInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
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
class SprintStorage extends SqlContentEntityStorage implements SprintStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(SprintInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {burndown_sprint_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {burndown_sprint_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(SprintInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {burndown_sprint_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('burndown_sprint_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
