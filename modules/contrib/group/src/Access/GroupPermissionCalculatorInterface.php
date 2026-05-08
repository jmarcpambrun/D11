<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\CalculatedPermissionsInterface;

/**
 * Aggregates the calculated permissions from all scopes into one set.
 */
interface GroupPermissionCalculatorInterface {

  /**
   * Calculates the full group permissions for an account.
   *
   * This includes all scopes: outsider, insider, individual.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to retrieve the permissions.
   *
   * @return \Drupal\Core\Session\CalculatedPermissionsInterface
   *   An object representing the full group permissions.
   */
  public function calculateFullPermissions(AccountInterface $account): CalculatedPermissionsInterface;

}
