<?php

namespace Drupal\burndown\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when a task is added.
 */
class TaskCreatedEvent extends Event {

  const ADDED = 'burndown_event_task_created';

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
   *   The newly created task.
   */
  public function __construct(EntityInterface $task) {
    $this->task = $task;
  }

}
