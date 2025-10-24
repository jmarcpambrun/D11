<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 23/08/18
 * Time: 10:50
 */

namespace Drupal\event_scheduler\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\event_scheduler\Event\EventScheduleInterface;
use Drupal\event_scheduler\EventSchedulerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the functionality for the Cron Event Scheduling Queue Workers.
 *
 * @QueueWorker(
 *   id = "cron_event_scheduler",
 *   title = @Translation("Launch scheduled events"),
 *   cron = {"time" = 10}
 * )
 */
class CronLaunchScheduledEvent extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * @var EventDispatcherInterface
   */
  protected $dispatcher;
  /**
   * @var EventSchedulerInterface
   */
  protected $scheduler;

  /**
   * Creates a new LaunchScheduledEvent object.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @param EventDispatcher $dispatcher
   * @param EventSchedulerInterface $scheduler
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              EventDispatcherInterface $dispatcher,
                              EventSchedulerInterface $scheduler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dispatcher = $dispatcher;
    $this->scheduler = $scheduler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('event_scheduler.scheduler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($itemId) {
//    $logger = $this->getLogger('event_scheduler_qworker');
    // Make sure we select the event we want.
    $conditions = ['id' => ['value' => $itemId]];

    /** @var EventScheduleInterface | Event $scheduledEvent */
    if ($scheduledEvent = $this->scheduler->loadEvent($conditions, TRUE)) {
//      $logger->debug('Got event from queue: ' . $scheduledEvent->getName());
      // Extract the event's name, so we can dispatch it...
      $eventName = $scheduledEvent->getName();
      // And dispatch it normally.
      $this->dispatcher->dispatch($eventName, $scheduledEvent);
    }

    // Remove the event from the database completely.
    $this->scheduler->deleteEvent($conditions);
  }
}