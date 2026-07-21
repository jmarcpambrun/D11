<?php

namespace Drupal\burndown_time_tracker\Plugin\views\access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Access plugin for the Burndown hours report.
 *
 * @ViewsAccess(
 *   id = "burndown_hours_access",
 *   title = @Translation("Burndown hours access"),
 *   help = @Translation("Accessible to users who can manage or comment on Burndown tasks.")
 * )
 */
class HoursReportAccess extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('administer burndown') || $account->hasPermission('burndown comment on task');
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_custom_access', '\\Drupal\\burndown_time_tracker\\Plugin\\views\\access\\HoursReportAccess::routeAccess');
  }

  /**
   * Custom route access callback for the generated view route.
   */
  public static function routeAccess(AccountInterface $account) {
    return AccessResult::allowedIf($account->hasPermission('administer burndown') || $account->hasPermission('burndown comment on task'))
      ->cachePerPermissions();
  }

}
