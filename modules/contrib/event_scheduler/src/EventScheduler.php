<?php

namespace Drupal\event_scheduler;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\event_scheduler\Event\EventScheduleInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ScheduledEventsService.
 */
class EventScheduler implements EventSchedulerInterface {

  const FORMAT = 'json';

  protected EventSchedulerUtilsInterface $eventSchedulerUtils;

  protected EventSchedulerDatabase $database;

  protected SerializerInterface $serializer;

  protected LoggerChannelInterface $logger;

  /**
   * Constructs a new ScheduledEventsService object.
   *
   * @param EventSchedulerUtilsInterface $eventSchedulerUtils
   * @param EventSchedulerDatabase $database
   * @param SerializerInterface $serializer
   * @param LoggerChannelFactoryInterface $loggerFactory
   */
  public function __construct(
    EventSchedulerUtilsInterface $eventSchedulerUtils,
    EventSchedulerDatabase $database,
    SerializerInterface $serializer,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->eventSchedulerUtils = $eventSchedulerUtils;
    $this->database = $database;
    $this->serializer = $serializer;
    $this->logger = $loggerFactory->get('event_scheduler');
  }

  //---------------------------------------------------------- EVENT OPERATIONS

  /**
   * Insert the event into the DB, serializing the event structure.
   *
   * @param string $eventName
   * @param EventScheduleInterface $event
   *
   * @return int|null
   *
   * @throws \Exception
   */
  public function saveEvent(string $eventName, EventScheduleInterface $event): ?int {
    $this->eventSchedulerUtils->log(sprintf('Saving event: %s (%s).', $eventName, $event->getTag()), 'EventScheduler');
    /** @var EventScheduleInterface $event */
    $entry = [
      'name' => $eventName,
      'class' => $event->getClass(),
      'tag' => $event->getTag(),
      'event' => $this->serializer->serialize($event, static::FORMAT),
      'launch' => $event->getLaunch(),
      'processed' => 0,
    ];
    return $this->database->insert($entry);
  }

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
  public function loadEvent(array $conditions = [], bool $firstOnly = FALSE): null|array|EventScheduleInterface {
    $events = [];
    foreach ($this->database->load($conditions) as $values) {
      $this->eventSchedulerUtils->log(sprintf('Loading event: %s', $values->name), 'EventScheduler');
      /** @var EventScheduleInterface $event */
      if ($event = $this->serializer->deserialize($values->event, $values->class, static::FORMAT)) {
        $this->eventSchedulerUtils->log(sprintf('Deserialized event: %s (%s)', $values->name, $values->tag), 'EventScheduler');
        $event->setId($values->id);

        $events[$event->id()] = $event;
      }
    }
    return empty($events) ? ($firstOnly ? NULL : []) : ($firstOnly ? reset($events) : $events);
  }

  /**
   * Delete the matching events from the DB.
   *
   * @param array $conditions
   *
   */
  public function deleteEvent(array $conditions = []): void {
    $this->database->delete($conditions);
  }

  /**
   * @return EventSchedulerDatabase
   */
  public function getDatabase(): EventSchedulerDatabase {
    return $this->database;
  }

}
