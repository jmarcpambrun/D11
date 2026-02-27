<?php

namespace Drupal\event_scheduler;

use Drupal\Core\Entity\EntityInterface;

interface EventSchedulerUtilsInterface {

  /**
   * Delete all events related to this entity from the event database.
   *
   * @param EntityInterface $entity
   */
  public function entityDelete(EntityInterface $entity): void;

  /**
   * Process the event database to add items to the event queue for processing.
   */
  public function runCron(): void;

  /**
   * Is the event scheduler currently enabled?
   *
   * @return bool
   */
  public function isEnabled(): bool;

  /**
   * Is the event scheduler currently logging?
   *
   * @return bool
   */
  public function isLogging(): bool;

  /**
   * Log a message and value if we are currently logging.
   *
   * @param mixed $value
   * @param string $prefix
   *
   * @return void
   */
  public function log(mixed $value, string $prefix = ''): void;

}
