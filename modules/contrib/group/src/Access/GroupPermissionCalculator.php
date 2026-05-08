<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccessPolicyProcessorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\CalculatedPermissions;
use Drupal\Core\Session\CalculatedPermissionsInterface;
use Drupal\Core\Session\RefinableCalculatedPermissions;
use Drupal\group\PermissionScopeInterface;

/**
 * Collects group permissions for an account.
 */
class GroupPermissionCalculator implements GroupPermissionCalculatorInterface {

  public function __construct(protected AccessPolicyProcessorInterface $accessPolicyProcessor) {
  }

  /**
   * {@inheritdoc}
   */
  public function calculateFullPermissions(AccountInterface $account): CalculatedPermissionsInterface {
    $calculated_permissions = new RefinableCalculatedPermissions();
    $calculated_permissions
      ->merge($this->accessPolicyProcessor->processAccessPolicies($account, PermissionScopeInterface::OUTSIDER_ID))
      ->merge($this->accessPolicyProcessor->processAccessPolicies($account, PermissionScopeInterface::INSIDER_ID))
      ->merge($this->accessPolicyProcessor->processAccessPolicies($account, PermissionScopeInterface::INDIVIDUAL_ID));
    return new CalculatedPermissions($calculated_permissions);
  }

}
