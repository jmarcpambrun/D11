<?php

declare(strict_types=1);

namespace Drupal\burndown_time_tracker\Utils;

/**
 * Utility methods for Burndown Time Tracker report behavior.
 */
final class BurndownTimeTrackerUtils {

  /**
   * Keeps only allowed Hours report query parameters.
   */
  public static function filterReportQuery(array $query): array {
    $allowed = [
      'project',
      'sprint',
      'tracked_user',
      'date_start',
      'date_end',
      'sort_by',
      'sort_order',
      'page',
    ];

    $filtered = [];
    foreach ($allowed as $key) {
      if (!array_key_exists($key, $query)) {
        continue;
      }

      $value = $query[$key];
      if ($value === '' || $value === NULL) {
        continue;
      }

      $filtered[$key] = $value;
    }

    return $filtered;
  }

  /**
   * Resolve the selected user ID from the current query values.
   */
  public static function resolveSelectedUserId(array $input, bool $is_admin, int $default_uid): int {
    if (!$is_admin) {
      return $default_uid;
    }

    if (empty($input['tracked_user'])) {
      return $default_uid;
    }

    if (is_numeric($input['tracked_user'])) {
      return (int) $input['tracked_user'];
    }

    if (is_string($input['tracked_user']) && preg_match('/\((\d+)\)\s*$/', $input['tracked_user'], $matches)) {
      return (int) $matches[1];
    }

    return $default_uid;
  }

  /**
   * Calculates filtered total in hours (h only, ignoring d/w/etc).
   */
  public static function calculateTotalHours(int $uid, array $input): float {
    $query = \Drupal::service('database')->select('burndown_task__log', 'l');
    $query->addField('l', 'log_work_done');
    $query->condition('l.deleted', 0);
    $query->condition('l.log_type', 'work');
    $query->condition('l.log_uid', $uid);

    if (!empty($input['project']) || !empty($input['sprint'])) {
      $query->innerJoin('burndown_task_field_data', 't', 't.id = l.entity_id');

      if (!empty($input['project']) && is_numeric($input['project'])) {
        $query->condition('t.project', (int) $input['project']);
      }
      if (!empty($input['sprint']) && is_numeric($input['sprint'])) {
        $query->condition('t.sprint', (int) $input['sprint']);
      }
    }

    if (!empty($input['date_start'])) {
      $start = strtotime($input['date_start'] . ' 00:00:00');
      if ($start !== FALSE) {
        $query->condition('l.log_created', $start, '>=');
      }
    }
    if (!empty($input['date_end'])) {
      $end = strtotime($input['date_end'] . ' 23:59:59');
      if ($end !== FALSE) {
        $query->condition('l.log_created', $end, '<=');
      }
    }

    $sum = 0.0;
    foreach ($query->execute()->fetchCol() as $work_done) {
      if (is_string($work_done) && preg_match('/^([0-9]*\.?[0-9]+)h$/', trim($work_done), $matches)) {
        $sum += (float) $matches[1];
      }
    }

    return $sum;
  }

  /**
   * Detects whether filtered work-log entries include non-hour units.
   */
  public static function hasUnsupportedUnits(int $uid, array $input): bool {
    $query = \Drupal::service('database')->select('burndown_task__log', 'l');
    $query->addField('l', 'log_work_done');
    $query->condition('l.deleted', 0);
    $query->condition('l.log_type', 'work');
    $query->condition('l.log_uid', $uid);

    if (!empty($input['project']) || !empty($input['sprint'])) {
      $query->innerJoin('burndown_task_field_data', 't', 't.id = l.entity_id');

      if (!empty($input['project']) && is_numeric($input['project'])) {
        $query->condition('t.project', (int) $input['project']);
      }
      if (!empty($input['sprint']) && is_numeric($input['sprint'])) {
        $query->condition('t.sprint', (int) $input['sprint']);
      }
    }

    if (!empty($input['date_start'])) {
      $start = strtotime($input['date_start'] . ' 00:00:00');
      if ($start !== FALSE) {
        $query->condition('l.log_created', $start, '>=');
      }
    }
    if (!empty($input['date_end'])) {
      $end = strtotime($input['date_end'] . ' 23:59:59');
      if ($end !== FALSE) {
        $query->condition('l.log_created', $end, '<=');
      }
    }

    foreach ($query->execute()->fetchCol() as $work_done) {
      if (is_string($work_done) && preg_match('/^([0-9]*\.?[0-9]+)h$/', trim($work_done))) {
        continue;
      }

      if (!empty(trim((string) $work_done))) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
