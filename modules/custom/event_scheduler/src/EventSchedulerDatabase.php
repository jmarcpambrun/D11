<?php

namespace Drupal\event_scheduler;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;

/**
 * Class EventSchedulerDatabase.
 */
class EventSchedulerDatabase implements EventSchedulerDatabaseInterface {

  const TABLE_NAME = 'event_scheduler';

  const TABLE_ALIAS = 'es';

  protected Connection $connection;

  protected LoggerChannelInterface $logger;

  protected StateInterface $state;

  /**
   * Constructs a new EventSchedulerDatabase object.
   *
   * @param Connection $connection
   * @param LoggerChannelFactoryInterface $loggerFactory
   * @param StateInterface $state
   */
  public function __construct(
    Connection $connection,
    LoggerChannelFactoryInterface $loggerFactory,
    StateInterface $state
  ) {
    $this->connection = $connection;
    $this->logger = $loggerFactory->get('event_scheduler_db');
    $this->state = $state;
  }

  /**
   * Save a scheduled event in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the database record.
   *
   * @return int | null
   *   The number of updated rows. Returns null on fail.
   *
   * @see db_insert()
   */
  public function insert(array $entry): ?int {
    $return_value = NULL;
    try {
      $this->clearNextScheduledEventTimestamp();

      $last_insert_id = $this->connection->insert(static::TABLE_NAME)
        ->fields($entry)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error(t('DB Insert failed. Message = "%message"', [
        '%message' => $e->getMessage(),
      ]));
      $last_insert_id = NULL;
    }
    return $last_insert_id;
  }

  /**
   * Update an entry in the database.
   *
   * @param array $fields
   *   An array containing all the fields of the item to be updated.
   *
   * @param array $conditions
   *   An array containing all the conditions used to search the entries in the
   *   table. Indexed by field, sub-array with 'value' and 'op' (optional, defaults to '=').
   *
   * @return int | null
   *   The number of updated rows. Returns null on fail.
   *
   * @see db_update()
   */
  public function update(array $fields, array $conditions = []): ?int {
    try {
      $this->clearNextScheduledEventTimestamp();

      // Connection->update()...->execute() returns the number of rows updated.
      $update = $this->connection
        ->update(static::TABLE_NAME)
        ->fields($fields);

      $count = $this->addConditions($update, $conditions)->execute();
    }
    catch (\Exception $e) {
      $this->logger->error(t('DB Update failed. Message = "%message", query = "%query"', [
          '%message' => $e->getMessage(),
          '%query' => $e->query_string,
        ]
      ));
      $count = NULL;
    }
    return $count;
  }

  /**
   * Delete an entry from the database.
   *
   * @param array $conditions
   *   An array containing all the conditions used to search the entries in the
   *   table. Indexed by field, sub-array with 'value' and 'op' (optional, defaults to '=').
   *
   * @return int|null
   *
   * @see db_delete()
   */
  public function delete(array $conditions): ?int {
    $delete = $this->connection->delete(static::TABLE_NAME);
    $this->clearNextScheduledEventTimestamp();

    return $this->addConditions($delete, $conditions)->execute();
  }

  /**
   * Read from the database using a conditions array.
   *
   * @param array $conditions
   *   An array containing all the conditions used to search the entries in the
   *   table. Indexed by field, sub-array with 'value' and 'op' (optional, defaults to '=').
   *
   * @param array $fields
   *   An array containing the names of the fields we want (empty for all).
   *
   * @return \stdClass[]
   *   An array containing the loaded entries if found.
   *
   * @see db_select()
   */
  public function load(array $conditions = [], array $fields = []): array {
    // Read all the fields from the dbtng_example table.
    $select = $this->connection
      ->select(static::TABLE_NAME, static::TABLE_ALIAS)
      // Add all the fields into our select query.
      ->fields(static::TABLE_ALIAS, $fields);

    // Return the result in object format.
    return $this->addConditions($select, $conditions)->execute()->fetchAll();
  }

  /**
   * @param SelectInterface | Delete | Update $query
   * @param array $conditions
   *
   * @return SelectInterface | Delete | Update
   */
  protected function addConditions($query, array $conditions = []) {
    // Add each field and value as a condition to this query.
    foreach ($conditions as $field => $value) {
      $value += ['op' => '='];

      // Automatically handle arrays.
      if ($value['op'] == '=' && is_array($value['value'])) {
        $value['op'] = 'IN';
      }

      // Add the condition.
      $query->condition($field, $value['value'], $value['op']);
    }
    return $query;
  }

  /**
   * Return the timestamp of the next scheduled event. This is stored
   * as a key/value pair, but reset whenever a change is made to the database.
   *
   * It returns zero if there are no events scheduled.
   *
   * @return int
   */
  public function nextScheduledEventTimestamp(): int {
    // Fetch the saved value but return NULL if nothing was saved.
    $timestamp = $this->state->get(static::NEXT_TIMESTAMP_KEY, NULL);

    // Null means we have to re-check the DB because a change
    // was made, and the key/value pair was deleted.
    if ($timestamp === NULL) {
      // If we *know* there are no scheduled events, set it to zero,
      // so we don't have to check it again until the DB is modified.
      $timestamp = $this->getNextScheduledEventTimestamp();
      $this->setNextScheduledEventTimestamp($timestamp);
    }

    return $timestamp;
  }

  /**
   * Fetches the timestamp of the next scheduled event, or zero if none at all.
   *
   * @return int
   */
  protected function getNextScheduledEventTimestamp(): int {
    $timestamp = $this->connection
      ->select(static::TABLE_NAME, static::TABLE_ALIAS)
      ->fields(static::TABLE_ALIAS, ['launch'])
      ->range(0, 1)
      ->orderBy(static::TABLE_ALIAS . '.launch', 'ASC')
      ->execute()
      ->fetchField();

    return $timestamp ?: 0;
  }

  /**
   * Used to set the next timestamp value, which is used in the event
   * subscriber to test against the current time and see whether any
   * scheduled events need to be activated.
   *
   * @param int $timestamp (if zero the value is removed).
   *
   * @return EventSchedulerDatabaseInterface
   */
  protected function setNextScheduledEventTimestamp(int $timestamp): EventSchedulerDatabaseInterface {
    if ($timestamp == 0) {
      $this->state->delete(static::NEXT_TIMESTAMP_KEY);
    }
    else {
      $this->state->set(static::NEXT_TIMESTAMP_KEY, $timestamp);
    }
    return $this;
  }

  /**
   * Removes the next scheduled event timestamp, we do this whenever the
   * database is changed. But only find the next one when it's asked for.
   *
   * @return EventSchedulerDatabaseInterface
   */
  protected function clearNextScheduledEventTimestamp(): EventSchedulerDatabaseInterface {
    return $this->setNextScheduledEventTimestamp(0);
  }

}
