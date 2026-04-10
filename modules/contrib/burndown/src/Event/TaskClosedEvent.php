<?php

namespace Drupal\burndown\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when a task is closed.
 */
class TaskClosedEvent extends Event {

  const CLOSED = 'burndown_event_task_closed';

  /**
   * The task.
   *
   * @var Drupal\Core\Entity\EntityInterface
   */
  public $task;

  /**
   * Constructs the object.
   *
   * @param Drupal\Core\Entity\EntityInterface $task
   *   The task that was closed.
   */
  public function __construct(EntityInterface $task) {
    $this->task = $task;
  }

}
