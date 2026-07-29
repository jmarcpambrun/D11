<?php

declare(strict_types=1);

namespace Drupal\burndown_time_tracker\Form;

use Drupal\burndown\Entity\Task;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\burndown_time_tracker\Service\TaskTimerService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative page for viewing and stopping active work timers.
 */
final class ManageWorkTimersForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    private readonly TaskTimerService $taskTimerService,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('burndown_time_tracker.task_timer'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'burndown_time_tracker_manage_work_timers_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $timers = $this->taskTimerService->getActiveTimers();

    $uids = [];
    $task_ids = [];
    foreach ($timers as $timer) {
      $uids[] = (int) ($timer['uid'] ?? 0);
      $task_ids[] = (int) ($timer['task_id'] ?? 0);
    }

    $users = !empty($uids) ? User::loadMultiple(array_unique($uids)) : [];
    $tasks = !empty($task_ids) ? Task::loadMultiple(array_unique($task_ids)) : [];

    $now = time();

    $form['timers'] = [
      '#type' => 'table',
      '#caption' => $this->t('These are all the actively running timers.'),
      '#header' => [
        $this->t('User'),
        $this->t('Task'),
        $this->t('Elapsed time'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No active timers found.'),
    ];

    foreach ($timers as $timer) {
      $uid = (int) ($timer['uid'] ?? 0);
      $task_id = (int) ($timer['task_id'] ?? 0);
      $started = (int) ($timer['started'] ?? 0);

      $username = isset($users[$uid]) ? $users[$uid]->getDisplayName() : (string) $this->t('Unknown user (@uid)', ['@uid' => $uid]);

      $task_cell = (string) $this->t('Unknown task (@id)', ['@id' => $task_id]);
      if (isset($tasks[$task_id])) {
        $task = $tasks[$task_id];
        $task_text = $task->getTicketId() . ' - ' . $task->getName();
        $task_cell = Link::fromTextAndUrl($task_text, Url::fromUri('internal:/burndown/task/' . $task->id()))->toString();
      }

      $elapsed = max(0, $now - $started);
      $elapsed_text = $this->dateFormatter->formatInterval($elapsed);

      $row_key = 'uid_' . $uid;
      $form['timers'][$row_key]['username'] = [
        '#plain_text' => $username,
      ];
      $form['timers'][$row_key]['task'] = [
        '#markup' => $task_cell,
      ];
      $form['timers'][$row_key]['elapsed'] = [
        '#plain_text' => $elapsed_text,
      ];
      $form['timers'][$row_key]['operations'] = [
        '#type' => 'submit',
        '#value' => $this->t('Stop timer'),
        '#name' => 'stop_timer_' . $uid,
        '#timer_uid' => $uid,
        '#submit' => ['::stopSingleSubmit'],
        '#limit_validation_errors' => [],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['stop_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Stop ALL timers'),
      '#submit' => ['::stopAllSubmit'],
      '#limit_validation_errors' => [],
      '#disabled' => empty($timers),
      '#button_type' => 'primary',
      '#attributes' => [
        'onclick' => 'return confirm("Are you sure you want to stop all active timers?");',
      ],
    ];

    return $form;
  }

  /**
   * Stops one selected timer from the admin table.
   */
  public function stopSingleSubmit(array &$form, FormStateInterface $form_state): void {
    $element = $form_state->getTriggeringElement();
    $uid = (int) ($element['#timer_uid'] ?? 0);

    if ($uid <= 0) {
      $this->messenger()->addError($this->t('Unable to determine which timer to stop.'));
      return;
    }

    $stopped_by = $this->currentActorName();
    $result = $this->taskTimerService->stopAndRecord($uid, time(), $stopped_by);

    if (!$result) {
      $this->messenger()->addWarning($this->t('No active timer found for that user.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $target_name = isset($result['timer']['uid']) ? $this->usernameForUid((int) $result['timer']['uid']) : $this->t('Unknown user');

    if (!empty($result['recorded'])) {
      $this->messenger()->addStatus($this->t('Stopped timer for @user and recorded work time.', ['@user' => $target_name]));
    }
    else {
      $this->messenger()->addWarning($this->t('Stopped timer for @user. Elapsed time was under 0.02h, so no work entry was recorded.', ['@user' => $target_name]));
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Stops all active timers.
   */
  public function stopAllSubmit(array &$form, FormStateInterface $form_state): void {
    $timers = $this->taskTimerService->getActiveTimers();
    if (empty($timers)) {
      $this->messenger()->addStatus($this->t('No active timers to stop.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $stopped_by = $this->currentActorName();
    $recorded_count = 0;
    $stopped_count = 0;

    foreach ($timers as $timer) {
      $uid = (int) ($timer['uid'] ?? 0);
      if ($uid <= 0) {
        continue;
      }

      $result = $this->taskTimerService->stopAndRecord($uid, time(), $stopped_by);
      if ($result) {
        $stopped_count++;
        if (!empty($result['recorded'])) {
          $recorded_count++;
        }
      }
    }

    if ($stopped_count === 0) {
      $this->messenger()->addWarning($this->t('No active timers were stopped.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Stopped @count timers. Recorded work entries for @recorded timers.', [
        '@count' => $stopped_count,
        '@recorded' => $recorded_count,
      ]));
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Intentionally unused. Actions are handled by dedicated submit callbacks.
  }

  /**
   * Gets a display name for a user ID.
   */
  private function usernameForUid(int $uid): string {
    if ($uid <= 0) {
      return (string) $this->t('Unknown user');
    }

    $user = User::load($uid);
    return $user ? $user->getDisplayName() : (string) $this->t('Unknown user');
  }

  /**
   * Gets the current actor display name.
   */
  private function currentActorName(): string {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      return (string) $this->currentUser()->getAccountName();
    }

    $user = User::load($uid);
    return $user ? $user->getDisplayName() : (string) $this->currentUser()->getAccountName();
  }

}
