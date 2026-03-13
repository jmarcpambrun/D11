<?php

namespace Drupal\drd;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\ProcessorInterface;
use Drupal\drd\Entity\BaseInterface;
use Drupal\drd\Plugin\Action\BaseEntityInterface;
use Drupal\drd\Plugin\Action\BaseGlobalInterface;
use Drupal\drd\Plugin\Action\BaseInterface as ActionBaseInterface;

/**
 * Easy access to the DRD queue.
 */
class QueueManager {

  /**
   * The queue processor.
   *
   * @var \Drupal\advancedqueue\ProcessorInterface
   */
  protected ProcessorInterface $processor;

  /**
   * QueueManager constructor.
   *
   * @param \Drupal\advancedqueue\ProcessorInterface $processor
   *   The queue processor.
   */
  public function __construct(ProcessorInterface $processor) {
    $this->processor = $processor;
  }

  /**
   * Get the DRD Queue.
   *
   * @return \Drupal\advancedqueue\Entity\QueueInterface
   *   The DRD Queue.
   */
  private function getQueue(): QueueInterface {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface|null $queue */
    $queue = Queue::load('drd');
    if ($queue === NULL) {
      $queue = Queue::create([
        'id' => 'drd',
        'label' => 'DRD',
        'backend' => 'database',
      ]);
      try {
        $queue->save();
      }
      catch (EntityStorageException) {
        // @todo Log this exception.
      }
    }
    return $queue;
  }

  /**
   * Process all jobs in the DRD queue.
   */
  public function processAll(): void {
    $this->processor->processQueue($this->getQueue());
  }

  /**
   * Get the number of items in the queue.
   *
   * @return int
   *   The number of items.
   */
  public function countItems(): int {
    $counts = $this->getQueue()->getBackend()->countJobs();
    return $counts[Job::STATE_QUEUED];
  }

  /**
   * Add a new item to the queue.
   *
   * @param \Drupal\drd\Plugin\Action\BaseInterface $action
   *   The action.
   * @param \Drupal\drd\Entity\BaseInterface|null $entity
   *   The entity.
   */
  public function createItem(ActionBaseInterface $action, ?BaseInterface $entity = NULL): void {
    try {
      $arguments = json_encode($action->getArguments(), JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      // @todo Log this exception.
      $arguments = [];
    }
    $payload = [
      'action' => $action->getPluginId(),
      'arguments' => $arguments,
    ];
    if ($action instanceof BaseEntityInterface && $entity !== NULL) {
      $type = 'drd_action_entity';
      $payload['entity_type'] = $entity->getEntityTypeId();
      $payload['entity_id'] = $entity->id();
    }
    elseif ($action instanceof BaseGlobalInterface) {
      $type = 'drd_action_global';
    }
    if (!empty($type)) {
      $job = Job::create($type, $payload);
      $this->getQueue()->enqueueJob($job);
      if (!$action->canBeQueued()) {
        $this->processor->processJob($job, $this->getQueue());
      }
    }
  }

  /**
   * Add new items to the queue.
   *
   * @param \Drupal\drd\Plugin\Action\BaseInterface $action
   *   The action.
   * @param \Drupal\drd\Entity\BaseInterface[] $entities
   *   The entities.
   */
  public function createItems(ActionBaseInterface $action, array $entities): void {
    foreach ($entities as $entity) {
      $this->createItem($action, $entity);
    }
  }

}
