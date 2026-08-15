<?php

namespace Drupal\burndown\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\burndown\Entity\Task;

/**
 * Event that is fired when a task is closed.
 */
class TaskClosedEvent extends Event {

  const CLOSED = 'burndown_event_task_closed';

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
   *   The task that was closed.
   */
  public function __construct(Task $task) {
    $this->task = $task;
  }

}
