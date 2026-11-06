<?php

declare(strict_types=1);

namespace Drupal\maestro_ai_tools\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\user\Entity\User;

/**
 * Reports the status of a Maestro workflow process.
 *
 * Two levels, following Maestro's own rules for seeing a process:
 * - "view maestro process entities" (the permission Maestro's process access
 *   handler uses): any process, with full details (stage, open tasks and
 *   their assignees, recently completed tasks and who completed them).
 * - "view maestro task console", for a process the user started or is
 *   currently assigned an open task in, when its template has "show details"
 *   turned on: only whether it is active or complete, and when it started and
 *   completed. Nothing about its tasks or the people involved.
 * Having completed a task in a process gives no access. A process that
 * doesn't exist is refused exactly like one the caller may not see, so the
 * tool can't be used to probe which process IDs exist.
 *
 * Process variable values are never included: they can hold sensitive data.
 */
#[Tool(
  id: 'get_maestro_workflow_status',
  label: new TranslatableMarkup('Get Maestro Workflow Status'),
  description: new TranslatableMarkup('Reports the status of a Maestro workflow process given its process ID: whether it is active or complete, its current stage, which tasks are waiting and who they are assigned to, and the most recently completed tasks.'),
  operation: ToolOperation::Read,
  destructive: FALSE,
  input_definitions: [
    'process_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Process ID'),
      description: new TranslatableMarkup('The ID of the Maestro process, as returned when the workflow was started.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'summary' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Summary'),
      description: new TranslatableMarkup('A short, readable description of where the workflow stands.'),
    ),
    'state' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('State'),
      description: new TranslatableMarkup('"active" or "complete".'),
    ),
    'details' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Details'),
      description: new TranslatableMarkup('The status as JSON. "detail_level" is "full" (process, template, initiator, dates, stage, open tasks with assignees, recently completed tasks) for users who may view all Maestro processes, or "basic" (process, template, state and dates only) for users involved in the process.'),
    ),
  ],
)]
final class GetMaestroWorkflowStatusTool extends ToolBase {

  /**
   * How many completed tasks to list, most recent first.
   */
  private const RECENT_COMPLETED = 10;

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $process_id = (int) ($values['process_id'] ?? 0);
    $process = $process_id > 0 ? MaestroEngine::getProcessEntryById($process_id) : NULL;
    if (!$process) {
      return ExecutableResult::failure(new TranslatableMarkup('Process @pid was not found, or you are not allowed to see it.', ['@pid' => $process_id]));
    }

    // Only users who may view all Maestro processes get the full picture.
    $details = $this->currentUser->hasPermission('view maestro process entities')
      ? $this->buildDetails($process_id, $process)
      : $this->buildBasicDetails($process_id, $process);
    return ExecutableResult::success(
      new TranslatableMarkup('Status of Maestro process @pid.', ['@pid' => $process_id]),
      [
        'summary' => $this->buildSummary($details),
        'state' => $details['state'],
        'details' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      ],
    );
  }

  /**
   * The bare minimum: which process, whether it is done, and when.
   */
  private function buildBasicDetails(int $process_id, $process): array {
    $template_id = $process->template_id->getString();
    $template = MaestroEngine::getTemplate($template_id);
    $complete = (int) $process->complete->getString() === 1;
    return [
      'detail_level' => 'basic',
      'process_id' => $process_id,
      'process_name' => $process->process_name->getString(),
      'template_label' => $template ? (string) $template->label() : $template_id,
      'state' => $complete ? 'complete' : 'active',
      'started' => self::date($process->created->value),
      'completed' => $complete ? self::date($process->completed->value) : NULL,
    ];
  }

  /**
   * Collects the full process status.
   */
  private function buildDetails(int $process_id, $process): array {
    $template_id = $process->template_id->getString();
    $template = MaestroEngine::getTemplate($template_id);
    $initiator = $process->getOwner();

    $details = [
      'detail_level' => 'full',
      'process_id' => $process_id,
      'process_name' => $process->process_name->getString(),
      'template_id' => $template_id,
      'template_label' => $template ? (string) $template->label() : $template_id,
      'state' => (int) $process->complete->getString() === 1 ? 'complete' : 'active',
      'initiator' => $initiator ? $initiator->getDisplayName() : NULL,
      'started' => self::date($process->created->value),
      'completed' => (int) $process->complete->getString() === 1 ? self::date($process->completed->value) : NULL,
      'stage' => NULL,
      'open_tasks' => [],
      'completed_task_count' => 0,
      'recent_completed_tasks' => [],
    ];

    // Workflow stage, as shown on Maestro's status bar.
    $stage_count = (int) MaestroEngine::getProcessVariable('workflow_timeline_stage_count', $process_id);
    if ($stage_count > 0) {
      $current_stage = (int) MaestroEngine::getProcessVariable('workflow_current_stage', $process_id);
      $entries = MaestroEngine::getAllStatusEntriesForProcess($process_id);
      $details['stage'] = [
        'current' => $current_stage,
        'count' => $stage_count,
        'message' => (string) MaestroEngine::getProcessVariable('workflow_current_stage_message', $process_id),
        // The status machine name set by the task that reached this stage.
        'status' => $entries[$current_stage]['status'] ?? '',
      ];
    }

    $storage = \Drupal::entityTypeManager()->getStorage('maestro_queue');
    $queue_ids = $storage->getQuery()->accessCheck(FALSE)->condition('process_id', $process_id)->sort('id')->execute();
    $completed = [];
    foreach ($storage->loadMultiple($queue_ids) as $queue) {
      $status = (int) $queue->status->getString();
      if ($status === TASK_STATUS_ACTIVE && (int) $queue->archived->getString() === 0) {
        $assigned = [];
        foreach (MaestroEngine::getAssignedNamesOfQueueItem($queue->id(), TRUE) as $assignment) {
          $assigned[] = $assignment['assign_type'] . ' ' . $assignment['assign_id'];
        }
        $details['open_tasks'][] = [
          'task_id' => $queue->task_id->getString(),
          'label' => $queue->task_label->getString(),
          'interactive' => (int) $queue->is_interactive->getString() === 1,
          'assigned_to' => $assigned,
          'open_since' => self::date($queue->created->value),
        ];
      }
      elseif ($status !== TASK_STATUS_ACTIVE) {
        // The queue's uid is a plain integer: who completed the task.
        $completed_by = (int) $queue->uid->value > 0 ? User::load((int) $queue->uid->value) : NULL;
        $completed[] = [
          'task_id' => $queue->task_id->getString(),
          'label' => $queue->task_label->getString(),
          'outcome' => (string) MaestroEngine::getTaskStatusLabel($status),
          'completed_by' => $completed_by ? $completed_by->getDisplayName() : NULL,
          'completed' => self::date($queue->completed->value),
        ];
      }
    }
    $details['completed_task_count'] = count($completed);
    $details['recent_completed_tasks'] = array_slice(array_reverse($completed), 0, self::RECENT_COMPLETED);

    return $details;
  }

  /**
   * Builds the readable summary from the details.
   */
  private function buildSummary(array $details): string {
    $lines = [];
    $lines[] = sprintf(
      'Process %d%s (template "%s") is %s.',
      $details['process_id'],
      $details['process_name'] !== '' ? ' "' . $details['process_name'] . '"' : '',
      $details['template_label'],
      strtoupper($details['state']),
    );
    $lines[] = sprintf(
      'Started %s%s%s.',
      $details['started'] ?? 'at an unknown time',
      !empty($details['initiator']) ? ' by ' . $details['initiator'] : '',
      $details['completed'] ? ', completed ' . $details['completed'] : '',
    );
    if ($details['detail_level'] === 'basic') {
      return implode("
", $lines);
    }
    if ($details['stage']) {
      $lines[] = sprintf(
        'Stage %d of %d%s%s.',
        $details['stage']['current'],
        $details['stage']['count'],
        $details['stage']['message'] !== '' ? ': ' . $details['stage']['message'] : '',
        $details['stage']['status'] !== '' ? ' (status: ' . $details['stage']['status'] . ')' : '',
      );
    }
    if ($details['open_tasks']) {
      foreach ($details['open_tasks'] as $task) {
        $lines[] = sprintf(
          'Waiting on "%s"%s, open since %s.',
          $task['label'],
          $task['assigned_to'] ? ' (assigned to ' . implode(', ', $task['assigned_to']) . ')' : '',
          $task['open_since'] ?? 'an unknown time',
        );
      }
    }
    elseif ($details['state'] === 'active') {
      $lines[] = 'No task is currently waiting; the workflow will continue on the next orchestrator run.';
    }
    if ($details['recent_completed_tasks']) {
      $last = $details['recent_completed_tasks'][0];
      $lines[] = sprintf(
        '%d task(s) done so far; the most recent was "%s" (%s%s, %s).',
        $details['completed_task_count'],
        $last['label'],
        $last['outcome'],
        $last['completed_by'] ? ' by ' . $last['completed_by'] : '',
        $last['completed'] ?? 'unknown time',
      );
    }
    return implode("\n", $lines);
  }

  /**
   * Formats a timestamp as an ISO 8601 date, or NULL when not set.
   */
  private static function date($timestamp): ?string {
    $timestamp = (int) $timestamp;
    return $timestamp > 0 ? \Drupal::service('date.formatter')->format($timestamp, 'custom', 'c') : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Decided per process, so the result is never cached.
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = $this->processAccess((int) ($values['process_id'] ?? 0), $account)->setCacheMaxAge(0);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Whether the account may see the status of the process.
   */
  private function processAccess(int $process_id, AccountInterface $account): AccessResult {
    if ($account->hasPermission('view maestro process entities')) {
      return AccessResult::allowed();
    }
    $denied = AccessResult::forbidden('Process not found, or you are not allowed to see it.');
    if ($process_id <= 0 || $account->isAnonymous() || !$account->hasPermission('view maestro task console')) {
      return $denied;
    }
    $process = MaestroEngine::getProcessEntryById($process_id);
    if (!$process) {
      return $denied;
    }
    // Maestro only shows a process's status details to console users when the template allows it.
    $template = MaestroEngine::getTemplate($process->template_id->getString());
    if (!$template || empty($template->show_details)) {
      return $denied;
    }
    return self::isInvolved($process_id, $process, $account) ? AccessResult::allowed() : $denied;
  }

  /**
   * Whether the account started the process or is assigned an open task in it.
   *
   * Having completed a task in it does not count: that says nothing about
   * whether the user should see how the workflow is going.
   */
  private static function isInvolved(int $process_id, $process, AccountInterface $account): bool {
    $uid = (int) $account->id();
    if ($process->getOwnerId() === $uid) {
      return TRUE;
    }
    // Assigned an open task in it (directly or through a role).
    $open = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery()->accessCheck(FALSE)
      ->condition('process_id', $process_id)
      ->condition('status', TASK_STATUS_ACTIVE)
      ->condition('archived', 0)
      ->execute();
    foreach ($open as $queue_id) {
      if (MaestroEngine::canUserExecuteTask($queue_id, $uid)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
