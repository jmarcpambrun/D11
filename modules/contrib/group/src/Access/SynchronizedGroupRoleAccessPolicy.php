<?php

namespace Drupal\group\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccessPolicyBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\CalculatedPermissionsItem;
use Drupal\Core\Session\RefinableCalculatedPermissionsInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\PermissionScopeInterface;

/**
 * Access policy for permissions coming from synchronized group roles.
 */
final class SynchronizedGroupRoleAccessPolicy extends AccessPolicyBase {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(string $scope): bool {
    return $scope === PermissionScopeInterface::OUTSIDER_ID || $scope === PermissionScopeInterface::INSIDER_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account, string $scope): RefinableCalculatedPermissionsInterface {
    $calculated_permissions = parent::calculatePermissions($account, $scope);

    // @todo Introduce config:group_role_list:scope:SCOPE cache tag.
    // If a new group role is introduced, we need to recalculate the permissions
    // for the provided scope.
    $calculated_permissions->addCacheTags(['config:group_role_list']);

    $roles = $account->getRoles();
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadByProperties([
      'scope' => $scope,
      'global_role' => $roles,
    ]);

    foreach ($group_roles as $group_role) {
      assert($group_role instanceof GroupRoleInterface);
      $item = new CalculatedPermissionsItem(
        $group_role->getPermissions(),
        $group_role->isAdmin(),
        $group_role->getScope(),
        $group_role->getGroupTypeId(),
      );
      $calculated_permissions->addItem($item);
      $calculated_permissions->addCacheableDependency($group_role);
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts(): array {
    return ['user.roles'];
  }

}
