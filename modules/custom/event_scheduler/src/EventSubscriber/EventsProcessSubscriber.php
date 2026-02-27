<?php

namespace Drupal\event_scheduler\EventSubscriber;

use Drupal\Component\Datetime\Time;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\event_scheduler\Event\EventScheduleInterface;
use Drupal\event_scheduler\EventSchedulerInterface;
use Drupal\event_scheduler\EventSchedulerUtilsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\event_scheduler\EventSchedulerDispatcher;
use Drupal\event_scheduler\EventSchedulerDatabaseInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class EventsProcessSubscriber.
 */
class EventsProcessSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Name of the lock we're using.
   */
  protected const LOCK = 'event-scheduler-process-lock';

  protected AccountInterface $account;

  protected MessengerInterface $messenger;

  protected EventSchedulerDispatcher $scheduler;

  protected EventSchedulerInterface $eventScheduler;

  protected EventSchedulerDatabaseInterface $schedulerDatabase;

  protected EventSchedulerUtilsInterface $eventSchedulerUtils;

  protected Time $time;

  protected StateInterface $state;

  /**
   * Constructs a new EventsProcessSubscriber object.
   *
   * @param EventSchedulerDispatcher $scheduler
   * @param EventSchedulerInterface $eventScheduler
   * @param EventSchedulerDatabaseInterface $schedulerDatabase
   * @param EventSchedulerUtilsInterface $eventSchedulerUtils
   * @param Time $time
   * @param StateInterface $state
   */
  public function __construct(
    AccountInterface $account,
    MessengerInterface $messenger,
    EventSchedulerDispatcher $scheduler,
    EventSchedulerInterface $eventScheduler,
    EventSchedulerDatabaseInterface $schedulerDatabase,
    EventSchedulerUtilsInterface $eventSchedulerUtils,
    Time $time,
    StateInterface $state
  ) {
    $this->account = $account;
    $this->messenger = $messenger;
    $this->scheduler = $scheduler;
    $this->eventScheduler = $eventScheduler;
    $this->schedulerDatabase = $schedulerDatabase;
    $this->eventSchedulerUtils = $eventSchedulerUtils;
    $this->time = $time;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents(): array {
    // Make sure this happens before the processing of local events.
    $events[KernelEvents::TERMINATE] = ['onKernelTerminate', 1100];
    $events[KernelEvents::REQUEST][] = ['onKernelSchedulerDisabled', 30];

    return $events;
  }

  /**
   * If the Event Scheduler is disabled, remind the admins.
   *
   * @param RequestEvent $event
   *
   * @return void
   */
  public function onKernelSchedulerDisabled(RequestEvent $event): void {
    if (!$this->eventSchedulerUtils->isEnabled()) {
      if ($this->account->hasPermission('manage event scheduler')) {
        $this->messenger->addMessage($this->t('Event scheduling is disabled. Delayed events will not be processed, functionality may be disrupted.'), 'status', FALSE);
      }
    }
  }

  /**
   * This method is called whenever the KERNEL::TERMINATE event is
   * dispatched.
   *
   * @param TerminateEvent $event
   */
  public function onKernelTerminate(TerminateEvent $event): void {
    if (!$this->eventSchedulerUtils->isEnabled()) {
      // Not enabled so don't do this. THIS WILL LOSE DELAYED EVENTS.
      return;
    }

    if ($this->state->get(self::LOCK, FALSE)) {
      // If this is locked then it's already being processed.
      return;
    }
    // Okay, let's do this thing. Prevent other processes from having a look in.
    $this->state->set(self::LOCK, TRUE);

    // Time now...
    $now = $this->time->getCurrentTime();

    // Fetch the timestamp of the next scheduled event, if this is
    // zero it means there aren't any, and if the timestamp is in
    // the future we don't want to do anything right now.
    $timestamp = $this->schedulerDatabase->nextScheduledEventTimestamp();
    if (!$timestamp || $timestamp > $now) {
      $this->eventSchedulerUtils->log('No scheduled events', 'EventsProcessSubscriber');
      // Make sure to remove the lock...
      $this->state->delete(self::LOCK);
      return;
    }

    // Find all scheduled events that have passed their
    // deadline but are not already being processed.
    $conditions = [
      'launch' => ['value' => $now, 'op' => '<'],
      'processed' => ['value' => 0],
    ];

    $logEvents = [];
    /** @var EventScheduleInterface | Event $scheduledEvent */
    foreach ($this->eventScheduler->loadEvent($conditions) as $scheduledEvent) {
      // Extract the event's name, so we can dispatch it...
      $eventName = $scheduledEvent->getName();
      // And dispatch it normally.
      $logEvents[$eventName] = $scheduledEvent->getTag();
      $this->scheduler->dispatch($scheduledEvent, $eventName);
    }
    $this->eventSchedulerUtils->log($logEvents, 'EventsProcessSubscriber');

    // Now delete them.
    $this->schedulerDatabase->delete($conditions);

    // And remove the lock.
    $this->state->delete(self::LOCK);
  }

}
