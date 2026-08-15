<?php

namespace Drupal\burndown\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\burndown\Entity\Task;

/**
 * Event that is fired when someone comments on a task.
 */
class TaskCommentEvent extends Event {

  const COMMENTED = 'burndown_event_task_comment';

  /**
   * The task.
   *
   * @var \Drupal\burndown\Entity\Task
   */
  public $task;

  /**
   * The comment.
   *
   * @var string
   */
  public $comment;

  /**
   * Constructs the object.
   *
   * @param \Drupal\burndown\Entity\Task $task
   *   The newly created task.
   * @param string $comment
   *   The text of the comment.
   */
  public function __construct(Task $task, $comment) {
    $this->task = $task;
    $this->comment = $comment;
  }

}
