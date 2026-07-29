<?php

namespace Drupal\burndown_time_tracker\Controller;

use Drupal\burndown\Entity\Task;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Access callbacks for Burndown time tracker routes.
 */
class HoursReportController {

  /**
   * Access callback for editing an individual time entry.
   */
  public function checkTimeEntryAccess(Task $burndown_task, int $delta, AccountInterface $account) {
    $log = $burndown_task->getLog();
    if (!isset($log[$delta])) {
      return AccessResult::forbidden();
    }

    $entry = $log[$delta];
    if (($entry['type'] ?? '') !== 'work') {
      return AccessResult::forbidden();
    }

    if ($account->hasPermission('manage user work hours')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (!$account->hasPermission('burndown comment on task')) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    $entry_uid = isset($entry['uid']) && is_numeric($entry['uid']) ? (int) $entry['uid'] : 0;
    return AccessResult::allowedIf($entry_uid === (int) $account->id())->cachePerUser();
  }

}
