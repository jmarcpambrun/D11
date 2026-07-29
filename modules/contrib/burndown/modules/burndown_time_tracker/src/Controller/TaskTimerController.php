<?php

declare(strict_types=1);

namespace Drupal\burndown_time_tracker\Controller;

use Drupal\burndown\Entity\Task;
use Drupal\burndown_time_tracker\Service\TaskTimerService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Timer API controller for task-level start/stop operations.
 */
final class TaskTimerController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly TaskTimerService $taskTimerService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('burndown_time_tracker.task_timer'),
    );
  }

  /**
   * Returns the current user's active timer state.
   */
  public function state(): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $timer = $this->taskTimerService->getActiveTimer($uid);
    return new JsonResponse([
      'success' => 1,
      'timer' => $this->normalizeTimerPayload($timer),
    ]);
  }

  /**
   * Starts a timer for a task ticket ID.
   */
  public function start(string $ticket_id): JsonResponse {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      return new JsonResponse([
        'success' => 0,
        'message' => $this->t('Task not found.'),
      ], 404);
    }

    $uid = (int) $this->currentUser()->id();
    $now = time();
    $existing = $this->taskTimerService->getActiveTimer($uid);

    if ($existing && (int) $existing['task_id'] === (int) $task->id()) {
      return new JsonResponse([
        'success' => 1,
        'message' => $this->t('Timer is already running for task @task.', ['@task' => $task->getName()]),
        'timer' => $this->normalizeTimerPayload($existing),
      ]);
    }

    $message = $this->t('Timer started for task @task.', ['@task' => $task->getName()]);

    if ($existing && (int) $existing['task_id'] !== (int) $task->id()) {
      $stopped = $this->taskTimerService->stopAndRecord($uid, $now);
      $old_task_name = $this->t('Unknown task');
      $was_recorded = !empty($stopped['recorded']);
      if ($stopped && isset($stopped['task']) && $stopped['task']) {
        $old_task_name = $stopped['task']->getName();
      }

      if ($was_recorded) {
        $message = $this->t('Since a new timer was started for task @new_task, your previously running timer for @old_task has been stopped and recorded for that task.', [
          '@new_task' => $task->getName(),
          '@old_task' => $old_task_name,
        ]);
      }
      else {
        $message = $this->t('Since a new timer was started for task @new_task, your previously running timer for @old_task has been stopped. Elapsed time was under 0.02h, so no work entry was recorded for that task.', [
          '@new_task' => $task->getName(),
          '@old_task' => $old_task_name,
        ]);
      }
    }

    $this->taskTimerService->startTimer($uid, (int) $task->id(), $now);
    $timer = $this->taskTimerService->getActiveTimer($uid);

    return new JsonResponse([
      'success' => 1,
      'message' => $message,
      'timer' => $this->normalizeTimerPayload($timer),
    ]);
  }

  /**
   * Stops the current user's active timer.
   */
  public function stop(): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $stopped = $this->taskTimerService->stopAndRecord($uid, time());

    if (!$stopped) {
      return new JsonResponse([
        'success' => 0,
        'message' => $this->t('There is no active timer to stop.'),
      ], 400);
    }

    $message = !empty($stopped['recorded'])
      ? $this->t('Timer stopped and recorded as a clocked work entry.')
      : $this->t('Timer stopped. Elapsed time was under 0.02h, so no work entry was recorded.');

    return new JsonResponse([
      'success' => 1,
      'message' => $message,
      'timer' => NULL,
    ]);
  }

  /**
   * Normalizes timer payload with task metadata for API consumers.
   */
  private function normalizeTimerPayload(?array $timer): ?array {
    if (!$timer) {
      return NULL;
    }

    $task_id = isset($timer['task_id']) ? (int) $timer['task_id'] : 0;
    $task = $task_id > 0 ? Task::load($task_id) : NULL;

    return [
      'uid' => isset($timer['uid']) ? (int) $timer['uid'] : 0,
      'task_id' => $task_id,
      'ticket_id' => $task ? $task->getTicketId() : NULL,
      'task_name' => $task ? $task->getName() : NULL,
      'started' => isset($timer['started']) ? (int) $timer['started'] : 0,
    ];
  }

}
