<?php

namespace Drupal\burndown;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Swimlane entity.
 *
 * @see \Drupal\burndown\Entity\Swimlane.
 */
class SwimlaneAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\burndown\Entity\SwimlaneInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished swimlane entities');
        }

        return AccessResult::allowedIfHasPermission($account, 'view published swimlane entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit swimlane entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete swimlane entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add swimlane entities');
  }

}
