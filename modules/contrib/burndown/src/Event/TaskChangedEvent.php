<?php

namespace Drupal\burndown\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountInterface;
use Drupal\burndown\Entity\Task;

/**
 * Event that is fired when a task is edited.
 */
class TaskChangedEvent extends Event {

  const CHANGED = 'burndown_event_task_changed';

  /**
   * The task.
   *
   * @var \Drupal\burndown\Entity\Task
   */
  public $task;

  /**
   * The user who edited the task.
   *
   * @var \Drupal\Core\Session\AccountInterface|null
   */
  public $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\burndown\Entity\Task $task
   *   The modified task.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user who edited the task.
   */
  public function __construct(Task $task, AccountInterface $account) {
    $this->task = $task;
    $this->account = $account;
  }

}
