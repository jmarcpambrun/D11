<?php

namespace Drupal\burndown\Event;

use Drupal\burndown\Entity\Task;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountInterface;

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
   * The user who wrote the comment.
   *
   * @var \Drupal\Core\Session\AccountInterface|null
   */
  public $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\burndown\Entity\Task $task
   *   The newly created task.
   * @param string $comment
   *   The text of the comment.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user who wrote the comment.
   */
  public function __construct(Task $task, $comment, AccountInterface $account) {
    $this->task = $task;
    $this->comment = $comment;
    $this->account = $account;
  }

}
