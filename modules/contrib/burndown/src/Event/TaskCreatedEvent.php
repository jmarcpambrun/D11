<?php

namespace Drupal\burndown\Event;

use Drupal\burndown\Entity\Task;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountInterface;

/**
 * Event that is fired when a task is added.
 */
class TaskCreatedEvent extends Event {

  const ADDED = 'burndown_event_task_created';

  /**
   * The task.
   *
   * @var \Drupal\burndown\Entity\Task
   */
  public $task;

  /**
   * The user who created the task.
   *
   * @var \Drupal\Core\Session\AccountInterface|null
   */
  public $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\burndown\Entity\Task $task
   *   The newly created task.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user who created the task.
   */
  public function __construct(Task $task, AccountInterface $account) {
    $this->task = $task;
    $this->account = $account;
  }

}
