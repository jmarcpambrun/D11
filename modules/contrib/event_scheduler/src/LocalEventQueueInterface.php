<?php

namespace Drupal\event_scheduler;

use Drupal\Component\EventDispatcher\Event;

/**
 * Interface LocalEventQueueInterface.
 */
interface LocalEventQueueInterface {

  /**
   * Add an event to the queue
   *
   * @param string $eventName
   * @param Event $event
   */
  public function add(string $eventName, Event $event);

  /**
   * Returns the queued events as long as there are events to return.
   *
   * @return \Generator
   */
  public function get(): \Generator;


}
