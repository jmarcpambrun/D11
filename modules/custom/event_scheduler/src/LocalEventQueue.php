<?php

namespace Drupal\event_scheduler;

use Drupal\Component\EventDispatcher\Event;

/**
 * Class LocalEventQueue.
 */
class LocalEventQueue implements LocalEventQueueInterface {

  /**
   * @var Event[]
   */
  protected array $queue = [];

  /**
   * @inheritDoc
   */
  public function add(string $eventName, Event $event) {
    $this->queue[] = [$eventName, $event];
  }

  /**
   * @inheritDoc
   */
  public function get(): \Generator {
    while (!empty($this->queue)) {
      [$eventName, $event] = array_shift($this->queue);
      yield $eventName => $event;
    }
  }

}
