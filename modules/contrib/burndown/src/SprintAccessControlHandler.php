<?php

namespace Drupal\burndown;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Sprint entity.
 *
 * @see \Drupal\burndown\Entity\Sprint.
 */
class SprintAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\burndown\Entity\SprintInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished sprint entities');
        }

        return AccessResult::allowedIfHasPermission($account, 'view published sprint entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit sprint entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete sprint entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add sprint entities');
  }

}
