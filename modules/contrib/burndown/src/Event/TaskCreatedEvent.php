<?php

namespace Drupal\burndown\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\burndown\Entity\Task;

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
   * Constructs the object.
   *
   * @param \Drupal\burndown\Entity\Task $task
   *   The newly created task.
   */
  public function __construct(Task $task) {
    $this->task = $task;
  }

}
