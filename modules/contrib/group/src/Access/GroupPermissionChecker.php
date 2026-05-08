<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\PermissionScopeInterface;

/**
 * Calculates group permissions for an account.
 */
class GroupPermissionChecker implements GroupPermissionCheckerInterface {

  public function __construct(protected GroupPermissionCalculatorInterface $groupPermissionCalculator) {
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermissionInGroup($permission, AccountInterface $account, GroupInterface $group) {
    $calculated_permissions = $this->groupPermissionCalculator->calculateFullPermissions($account);

    // First check if anything gave the user individual access to the group.
    $item = $calculated_permissions->getItem(PermissionScopeInterface::INDIVIDUAL_ID, $group->id());
    if ($item && $item->hasPermission($permission)) {
      return TRUE;
    }

    // Then check their synchronized access depending on if they are a member.
    if (GroupMembership::loadSingle($group, $account)) {
      $item = $calculated_permissions->getItem(PermissionScopeInterface::INSIDER_ID, $group->bundle());
    }
    else {
      $item = $calculated_permissions->getItem(PermissionScopeInterface::OUTSIDER_ID, $group->bundle());
    }

    return $item && $item->hasPermission($permission);
  }

}
