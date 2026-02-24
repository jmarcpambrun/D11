<?php

namespace Drupal\event_scheduler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\event_scheduler\Event\EventDelayInterface;
use Drupal\event_scheduler\Event\EventScheduleInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class EventSchedulerDispatcher.
 *
 * Concept stolen from https://thomas.jarrand.fr/blog/events-part-3/
 */
class EventSchedulerDispatcher implements EventDispatcherInterface, EventSubscriberInterface {

  use LoggerChannelTrait;


  const string QUEUE_NAME = 'cron_event_scheduler';

  protected bool $pageSent;

  /**
   * Constructs a new DelayedEventDispatcher object.
   *
   * @param EventDispatcherInterface $eventDispatcher
   * @param EventSchedulerInterface $scheduler
   * @param EventSchedulerUtilsInterface $eventSchedulerUtils
   * @param TimeInterface $time
   * @param LocalEventQueueInterface $localEventQueue
   *
   *  The event queue service allows more events to be added to the
   *  end while it's being processed, to ensure we don't process
   *  events within events (which causes issues when handling entities).
   */
  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected EventSchedulerInterface $scheduler,
    protected EventSchedulerUtilsInterface $eventSchedulerUtils,
    protected TimeInterface $time,
    protected LocalEventQueueInterface $localEventQueue
  ) {
    $this->pageSent = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::TERMINATE => ['setPageSent', 1000]];
  }

  /**
   * Set pageSent flag to avoid recursion, and
   * dispatch locally queued events for processing.
   *
   * We never need to unset this flag because the next page will reset it.
   */
  public function setPageSent(): void {
    if (!$this->pageSent) {
      $this->pageSent = TRUE;

      /** @var object $event */
      foreach ($this->localEventQueue->get() as $eventName => $event) {
        $this->eventSchedulerUtils->log(sprintf('Dispatching queued event: %s', $eventName), 'EventSchedulerDispatcher');
        $this->eventDispatcher->dispatch($event, $eventName);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dispatch($event, ?string $eventName = NULL): object {
    /** @var Event $event */
    // Is this an event to be scheduled, delayed, or just dispatched?
    if ($event instanceof EventDelayInterface) {
      $this->eventSchedulerUtils->log(sprintf('Dispatching to local queue: %s', $eventName), 'EventSchedulerDispatcher');
      $this->addToLocalQueue($event->getName(), $event);
    }
    elseif ($event instanceof EventScheduleInterface) {
      $this->eventSchedulerUtils->log(sprintf('Dispatching to database: %s (%s)', $eventName, $event->getTag()), 'EventSchedulerDispatcher');
      $this->saveToDB($event->getName(), $event);
    }
    else {
      $this->eventSchedulerUtils->log(sprintf('Dispatching now: %s', $eventName), 'EventSchedulerDispatcher');
      // It's just an ordinary event, dispatch it normally.
      $this->eventDispatcher->dispatch($event);
    }
    return $event;
  }

  /**
   * Add the event to the local queue unless we're already
   * in the terminate phase, in which case dispatch it now.
   *
   * @param string $eventName
   * @param Event $event
   */
  protected function addToLocalQueue(string $eventName, Event $event): void {
    // To be performed at the end of this scheduled page.
    $this->eventSchedulerUtils->log(sprintf('Adding to local queue: %s', $eventName), 'EventSchedulerDispatcher');
    // Haven't reached the end yet, so save it in the local queue.
    $this->localEventQueue->add($eventName, $event);
  }

  /**
   * It's an event that needs to be saved for the future...
   *
   * Unless launch time is passed, in which case send it to the
   * local queue (which might dispatch it now, anyway).
   *
   * @param string $eventName
   * @param Event|EventScheduleInterface $event
   */
  protected function saveToDB(string $eventName, Event|EventScheduleInterface $event): void {
    if ($event->getLaunch() > $this->time->getRequestTime()) {
      $this->eventSchedulerUtils->log(sprintf('Saving to database: %s (%s)', $eventName, $event->getTag()), 'EventSchedulerDispatcher');
      $this->scheduler->saveEvent($eventName, $event);
    }
    else {
      // This should be executed immediately, so add it to the queue.
      $this->eventSchedulerUtils->log(sprintf('Adding to local queue (overdue): %s (%s)', $eventName, $event->getTag()), 'EventSchedulerDispatcher');
      $this->addToLocalQueue($eventName, $event);
    }
  }

  //=================================================== STANDARD METHODS

  /**
   * Adds an event listener that listens on the specified events.
   *
   * @param string $eventName The event to listen on
   * @param callable $listener The listener
   * @param int $priority The higher this value, the earlier an event
   *                            listener will be triggered in the chain (defaults to 0)
   */
public function addListener(string $eventName, callable $listener, int $priority = 0): void {
    $this->eventDispatcher->addListener($eventName, $listener, $priority);
  }

  /**
   * Adds an event subscriber.
   *
   * The subscriber is asked for all the events he is
   * interested in and added as a listener for these events.
   *
   * @param EventSubscriberInterface $subscriber
   */
	public function addSubscriber(EventSubscriberInterface $subscriber): void {
    $this->eventDispatcher->addSubscriber($subscriber);
  }

  /**
   * Removes an event listener from the specified events.
   *
   * @param string $eventName The event to remove a listener from
   * @param callable $listener The listener to remove
   */
  public function removeListener(string $eventName, callable $listener): void {
    $this->eventDispatcher->removeListener($eventName, $listener);
  }

  /**
   * Removes an event subscriber from the specified events.
   *
   * @param EventSubscriberInterface $subscriber
   */
  public function removeSubscriber(EventSubscriberInterface $subscriber): void {
    $this->eventDispatcher->removeSubscriber($subscriber);
  }

  /**
   * Gets the listeners of a specific event or all listeners sorted by descending priority.
   *
   * @param string $eventName The name of the event
   *
   * @return array The event listeners for the specified event, or all event listeners by event name
   */
  public function getListeners($eventName = NULL): array {
    return $this->eventDispatcher->getListeners($eventName);
  }

  /**
   * Gets the listener priority for a specific event.
   *
   * Returns null if the event or the listener does not exist.
   *
   * @param string $eventName The name of the event
   * @param callable $listener The listener
   *
   * @return int|null The event listener priority
   */
  public function getListenerPriority($eventName, $listener): ?int {
    return $this->eventDispatcher->getListenerPriority($eventName, $listener);
  }

  /**
   * Checks whether an event has any registered listeners.
   *
   * @param string $eventName The name of the event
   *
   * @return bool true if the specified event has any listeners, false otherwise
   */
  public function hasListeners($eventName = NULL): bool {
    return $this->eventDispatcher->hasListeners($eventName);
  }

}
