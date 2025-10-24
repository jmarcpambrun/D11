<?php

namespace Drupal\event_scheduler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class EventSchedulerUtils implements EventSchedulerUtilsInterface {

  use StringTranslationTrait;

  protected EventSchedulerDatabaseInterface $database;

  protected TimeInterface $time;

  protected QueueInterface $queue;

  protected ConfigFactoryInterface $configFactory;

  protected LoggerChannelInterface $logger;

  protected ?bool $isEventSchedulerEnabled = NULL;

  protected ?bool $isEventSchedulerLogging = NULL;

  /**
   * Constructor.
   *
   * @param EventSchedulerDatabaseInterface $database
   * @param TimeInterface $time
   * @param QueueFactory $queueFactory
   * @param ConfigFactoryInterface $configFactory
   * @param LoggerChannelInterface $logger
   */
  public function __construct(
      EventSchedulerDatabaseInterface $database,
      TimeInterface $time,
      QueueFactory $queueFactory,
      ConfigFactoryInterface $configFactory,
      LoggerChannelInterface $logger
  ) {
    $this->database = $database;
    $this->time = $time;
    $this->queue = $queueFactory->get(EventSchedulerDispatcher::QUEUE_NAME);
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * @inheritDoc
   */
  public function entityDelete(EntityInterface $entity): void {
    // Delete all events related to this entity.
    $this->database->delete([
        'tag' => ['value' => $entity->getCacheTagsToInvalidate()],
    ]);
  }

  /**
   * @inheritDoc
   */
  public function runCron(): void {
    $now = $this->time->getRequestTime();

    // Remove older-than-a-week scheduled events that have not been launched,
    // these are very unlikely to exist but could if the site has been offline.
    $this->deleteOld($now - (86400 * 7), FALSE);

    // Find greater-than-day-old scheduled events that have been launched in
    // order to delete them. (They should have been deleted already, but you
    // can never be sure what might interrupt it).
    $this->deleteOld($now - 86400, TRUE);

    try {
      // Find all scheduled events that have passed their
      // deadline but are not already being processed.
      $conditions = [
          'launch' => ['value' => $now, 'op' => '<'],
          'processed' => ['value' => 0],
      ];

      foreach ($this->getIds($conditions) as $itemId) {
        // Now queue the event ID to be handled later.
        $this->queue->createItem($itemId);

        // And update the DB to show it's been processed.
        $conditions = [
            'id' => ['value' => $itemId],
        ];

        // Set processed to 1 because it's been transferred to the queue.
        $update_fields = ['processed' => 1];

        $this->database->update($update_fields, $conditions);
      }
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Failed when trying to load scheduled events because: %x', ['%x' => $e->getMessage()]));
    }

  }

  /**
   * Fetch all the ids of scheduled event records that match the conditions.
   *
   * @param array $conditions
   *
   * @return array
   */
  function getIds(array $conditions = []): array {
    $ids = [];
    foreach ($this->database->load($conditions, ['id']) as $item) {
      // Collect the ids of found events.
      $ids[] = $item->id;
    }
    return $ids;
  }

  /**
   * Delete any scheduled event records that match the conditions.
   *
   * @param int $older_than
   * @param bool $processed
   */
  protected function deleteOld(int $older_than, bool $processed): void {
    // Build the conditions from the supplied parameters.
    $conditions = [
        'launch' => ['value' => $older_than, 'op' => '<'],
        'processed' => ['value' => (int) $processed],
    ];

    $this->database->delete($conditions);
  }

  /**
   * @inheritDoc
   */
  public function isEnabled(): bool {
    if ($this->isEventSchedulerEnabled === NULL) {
      $settings = $this->configFactory->get('event_scheduler.settings');
      $this->isEventSchedulerEnabled = $settings->get('enabled') ?? TRUE;
    }
    return $this->isEventSchedulerEnabled;
  }

  /**
   * @inheritDoc
   */
  public function isLogging(): bool {
    if ($this->isEventSchedulerLogging === NULL) {
      $settings = $this->configFactory->get('event_scheduler.settings');
      $this->isEventSchedulerLogging = $settings->get('logging') ?? FALSE;
    }
    return $this->isEventSchedulerLogging;
  }

  /**
   * @inheritDoc
   */
  public function log(mixed $value, string $prefix = ''): void {
    if ($this->isLogging()) {
      $prefix = $prefix ? "$prefix: " : '';
      $value = !is_bool($value) ? $value : ($value ? 'True' : 'False');
      $value = is_scalar($value) ? $value : print_r($value, TRUE);
      $this->logger->debug("<pre>$prefix$value</pre>");
    }
  }

}
