<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRoleStorageInterface;
use Drupal\user\UserInterface;

/**
 * User hook implementations for Group.
 */
final class UserHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('user_update')]
  public function userUpdate(AccountInterface $account): void {
    // If a user's roles change, we need to reset their group roles cache.
    $new = array_unique($account->getRoles());
    $old = array_unique($account->original->getRoles());
    sort($new);
    sort($old);

    if ($new != $old) {
      $storage = $this->entityTypeManager->getStorage('group_role');
      assert($storage instanceof GroupRoleStorageInterface);
      $storage->resetUserGroupRoleCache($account);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('user_delete')]
  public function userDelete(EntityInterface $account): void {
    // If a user is deleted, we delete all of their groups too.
    $storage = $this->entityTypeManager->getStorage('group');
    if ($groups = $storage->loadByProperties(['uid' => $account->id()])) {
      $storage->delete($groups);
    }
  }

  /**
   * Implements hook_user_cancel_methods_alter().
   */
  #[Hook('user_cancel_methods_alter')]
  public function userCancelMethodsAlter(array &$methods): void {
    $methods['user_cancel_block']['title'] = $this->t('Disable the account and keep its content and groups.');
    $methods['user_cancel_block']['description'] .= ' ' . $this->t('Groups that were created by you will still list you as the owner.');
    $methods['user_cancel_block_unpublish']['title'] .= ' ' . $this->t('Does not affect groups.');
    $methods['user_cancel_block_unpublish']['description'] .= ' ' . $this->t('Groups that were created by you will remain visible.');
    $methods['user_cancel_reassign']['title'] .= ' ' . $this->t('Reassign its groups to the super administrator.');
    $methods['user_cancel_reassign']['description'] .= ' ' . $this->t('All of your groups will be assigned to the super administrator.');
    $methods['user_cancel_delete']['title'] = $this->t('Delete the account, its content and groups.');
    $methods['user_cancel_delete']['description'] .= ' ' . $this->t('This includes groups that were created by you, including all of their content relationships!');
  }

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel(array $edit, UserInterface $account, string $method): void {
    // Reassign all groups to the super user.
    if ($method == 'user_cancel_reassign') {
      $storage = $this->entityTypeManager->getStorage('group');
      $gids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uid', $account->id())
        ->execute();

      // Run this as a batch if there are more than 10 groups.
      if (count($gids) > 10) {
        batch_set([
          'operations' => [
            [[$this, 'massReassignToSuperUser'], [$gids]],
          ],
        ]);
      }
      // Run it immediately if not.
      else {
        foreach ($storage->loadMultiple($gids) as $group) {
          assert($group instanceof GroupInterface);
          $group->set('uid', 1);
          $storage->save($group);
        }
      }
    }
  }

  /**
   * Implements callback_batch_operation().
   *
   * Mass reassigns ownership of groups to the super user.
   *
   * @param int[] $ids
   *   An array of group IDs.
   * @param array $context
   *   The batch context array.
   *
   * @see callback_batch_operation
   */
  public function massReassignToSuperUser(array $ids, array &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($ids);
      $context['sandbox']['ids'] = $ids;
    }

    // Try to update 10 groups at a time.
    $ids = array_slice($context['sandbox']['ids'], $context['sandbox']['progress'], 10);

    $storage = $this->entityTypeManager->getStorage('group');
    foreach ($storage->loadMultiple($ids) as $group) {
      assert($group instanceof GroupInterface);
      $group->set('uid', 1);
      $storage->save($group);
      $context['sandbox']['progress']++;
    }

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

}
