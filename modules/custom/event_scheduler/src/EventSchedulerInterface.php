<?php

namespace Drupal\event_scheduler;

use Drupal\event_scheduler\Event\EventScheduleInterface;

/**
 * Interface ScheduledEventsServiceInterface.
 */
interface EventSchedulerInterface {

  /**
   * @param string $eventName
   * @param EventScheduleInterface $event
   *
   * @return int|null
   */
  public function saveEvent(string $eventName, EventScheduleInterface $event): ?int;

  /**
   * Extract the matching events from the DB, unserializing the event structure.
   *
   * @param array $conditions
   *
   * @param bool $firstOnly
   *
   * @return EventScheduleInterface[] | EventScheduleInterface | null
   *
   */
  public function loadEvent(array $conditions = [], bool $firstOnly = FALSE): null|array|EventScheduleInterface;

  /**
   * Delete the matching events from the DB.
   *
   * @param array $conditions
   *
   * @return void
   */
  public function deleteEvent(array $conditions = []): void;

  /**
   * @return EventSchedulerDatabase
   */
  public function getDatabase(): EventSchedulerDatabase;

}
