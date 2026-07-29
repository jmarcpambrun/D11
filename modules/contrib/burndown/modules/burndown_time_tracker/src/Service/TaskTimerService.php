<?php

declare(strict_types=1);

namespace Drupal\burndown_time_tracker\Service;

use Drupal\burndown\Entity\Task;
use Drupal\Core\Database\Connection;

/**
 * Handles active task timers for users.
 */
final class TaskTimerService {

  /**
   * Timer table name.
   */
  private const TABLE = 'burndown_time_tracker_timer';

  /**
   * Minimum rounded-hours threshold required to persist a work-log entry.
   */
  private const MIN_LOG_HOURS = 0.02;

  /**
   * Database connection.
   */
  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Returns the current user's active timer row, if any.
   */
  public function getActiveTimer(int $uid): ?array {
    $row = $this->database->select(self::TABLE, 't')
      ->fields('t', ['uid', 'task_id', 'started'])
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

  /**
   * Returns all active timers.
   *
   * @return array<int, array<string, int|string>>
   *   Active timer rows keyed numerically.
   */
  public function getActiveTimers(): array {
    $rows = $this->database->select(self::TABLE, 't')
      ->fields('t', ['uid', 'task_id', 'started'])
      ->orderBy('started', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
  }

  /**
   * Starts or updates a timer for a user/task pair.
   */
  public function startTimer(int $uid, int $task_id, int $started): void {
    $this->database->merge(self::TABLE)
      ->key('uid', $uid)
      ->fields([
        'task_id' => $task_id,
        'started' => $started,
      ])
      ->execute();
  }

  /**
   * Stops a running timer and records a work-log entry.
   */
  public function stopAndRecord(int $uid, int $stopped, string $stopped_by = ''): ?array {
    $timer = $this->getActiveTimer($uid);
    if (!$timer) {
      return NULL;
    }

    $task_id = isset($timer['task_id']) ? (int) $timer['task_id'] : 0;
    $started = isset($timer['started']) ? (int) $timer['started'] : 0;

    $task = Task::load($task_id);
    $recorded = FALSE;
    if ($task) {
      $seconds = max(0, $stopped - $started);
      $raw_hours = $seconds / 3600;

      // Ignore very short timers to avoid creating 0.00h noise entries.
      if ($raw_hours >= self::MIN_LOG_HOURS) {
        $hours = round($raw_hours, 2);
        $hours_text = $this->formatHours($hours);
        $comment = 'clocked';
        if ($stopped_by !== '') {
          $comment .= '. Stopped by ' . $stopped_by;
        }

        $task
          ->addLog('work', $comment, $hours_text . 'h', $stopped, $uid, '')
          ->save();
        $recorded = TRUE;
      }
    }

    $this->database->delete(self::TABLE)
      ->condition('uid', $uid)
      ->execute();

    return [
      'timer' => $timer,
      'task' => $task,
      'recorded' => $recorded,
    ];
  }

  /**
   * Formats rounded hours with max precision of 2 decimals.
   */
  public function formatHours(float $hours): string {
    $formatted = number_format($hours, 2, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted === '' ? '0' : $formatted;
  }

}
