<?php

namespace Drupal\group\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccessPolicyBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\CalculatedPermissionsItem;
use Drupal\Core\Session\RefinableCalculatedPermissionsInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\group\PermissionScopeInterface;

/**
 * Access policy for permissions coming from an individual group role.
 */
final class IndividualGroupRoleAccessPolicy extends AccessPolicyBase {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(string $scope): bool {
    return $scope === PermissionScopeInterface::INDIVIDUAL_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account, string $scope): RefinableCalculatedPermissionsInterface {
    $calculated_permissions = parent::calculatePermissions($account, $scope);

    // The member permissions need to be recalculated whenever the user is added
    // to or removed from a group.
    $calculated_permissions->addCacheTags(['group_relationship_list:plugin:group_membership:entity:' . $account->id()]);

    foreach (GroupMembership::loadByUser($account) as $group_membership) {
      assert($group_membership instanceof GroupMembershipInterface);

      // If the member's roles change, so do the permissions.
      $calculated_permissions->addCacheableDependency($group_membership);

      foreach ($group_membership->getRoles(FALSE) as $group_role) {
        $item = new CalculatedPermissionsItem(
          $group_role->getPermissions(),
          $group_role->isAdmin(),
          $group_role->getScope(),
          $group_membership->getGroupId(),
        );
        $calculated_permissions->addCacheableDependency($group_role);
        $calculated_permissions->addItem($item);
      }
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts(): array {
    return ['user'];
  }

}
