<?php

namespace Drupal\event_scheduler;

/**
 * Interface EventSchedulerDatabaseInterface.
 */
interface EventSchedulerDatabaseInterface {

  const NEXT_TIMESTAMP_KEY = 'event-scheduler-next-timestamp';

  /**
   * Save a scheduled event in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the database record.
   *
   * @return int|null
   *   The number of updated rows.
   *   The number of updated rows. Returns null on fail.
   *
   * @see db_insert()
   */
  public function insert(array $entry): ?int;

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
  public function update(array $fields, array $conditions = []): ?int;

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
  public function delete(array $conditions): ?int;

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
  public function load(array $conditions = [], array $fields = []): array;

  /**
   * Return the timestamp of the next scheduled event. This is stored
   * as a key/value pair, but reset whenever a change is made to the database.
   *
   * It returns zero if there are no events scheduled.
   *
   * @return int
   */
  public function nextScheduledEventTimestamp(): int;


}
