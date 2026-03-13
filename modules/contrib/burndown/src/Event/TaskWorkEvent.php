<?php

namespace Drupal\burndown\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when someone adds a worklog to a task.
 */
class TaskWorkEvent extends Event {

  const WORKED = 'burndown_event_task_work';

  /**
   * The task.
   *
   * @var Drupal\Core\Entity\EntityInterface
   */
  public $task;

  /**
   * The comment.
   *
   * @var string
   */
  public $comment;

  /**
   * The amount of work done.
   *
   * @var string
   */
  public $workDone;

  /**
   * The user who did the work.
   *
   * @var int
   */
  public $uid;

  /**
   * Constructs the object.
   *
   * @param Drupal\Core\Entity\EntityInterface $task
   *   The newly created task.
   * @param string $comment
   *   The comment.
   * @param string $workDone
   *   The maount of work done.
   * @param int $uid
   *   The id of the user who did the work.
   *
   * @todo Provide more detailed/explanatory comments on parameters.
   */
  public function __construct(EntityInterface $task, $comment, $workDone, $uid) {
    // $created, $uid,
    $this->task = $task;
    $this->comment = $comment;
    $this->workDone = $workDone;
    $this->uid = $uid;
  }

}
