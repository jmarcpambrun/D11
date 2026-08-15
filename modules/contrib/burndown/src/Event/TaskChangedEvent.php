<?php

namespace Drupal\burndown\Event;

use Drupal\Component\EventDispatcher\Event;
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
   * Constructs the object.
   *
   * @param \Drupal\burndown\Entity\Task $task
   *   The modified task.
   */
  public function __construct(Task $task) {
    $this->task = $task;
  }

}
