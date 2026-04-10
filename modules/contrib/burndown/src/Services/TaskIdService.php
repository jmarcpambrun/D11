<?php

namespace Drupal\burndown\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Utility\Error;

/**
 * A service class for fetching and incrementing next sequential ID.
 *
 * TaskIdService obtains the next ID (and increments it) from a
 * project, in an atomic manner.
 */
class TaskIdService {

  /**
   * A database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Get (and increment) the task counter from a project.
   */
  public function getNextIdFor($project_id) {
    // Repeatedly attempt to get a unique ticket id for our new task.
    for ($i = 1; $i <= 10; $i++) {
      $ticket_id = $this->getIncrementedId($project_id);
      if ($ticket_id !== FALSE) {
        // Make sure it doesn't exist in a task already.
        $exists = $this->ticketIdExists($ticket_id);
        if (!$exists) {
          // Good to go!
          return $ticket_id;
        }
      }
    }

    // Couldn't get an id somehow...
    return FALSE;
  }

  /**
   * Transactional method to obtain an incremented id from the project.
   */
  private function getIncrementedId($project_id) {
    // Create a transaction.
    $transaction = $this->connection->startTransaction();

    try {
      // First get the current value.
      $data = $this->connection->select('burndown_project_field_data', 'b')
        ->fields('b', ['ticket_id'])
        ->condition('b.id', $project_id)
        ->execute();

      $results = $data->fetchAll(\PDO::FETCH_OBJ);

      if (empty($results)) {
        throw new \Exception('Project ID ' . $project_id . ' does not exist.');
      }

      // Current counter value.
      $current_id = $results[0]->ticket_id;

      // Increment the id.
      $current_id++;

      // Save the value back to the DB.
      $num_updated = $this->connection->update('burndown_project_field_data')
        ->fields([
          'ticket_id' => $current_id,
        ])
        ->condition('id', $project_id)
        ->execute();

      if ($num_updated == 0) {
        throw new \Exception('Could not update task counter for project.');
      }

      return $current_id;
    }
    catch (\Exception $e) {
      // Roll back the transaction.
      $transaction->rollBack();

      // Log the error.
      $variables = Error::decodeException($e);
      \Drupal::logger('burndown')
        ->error('%type: @message in %function (line %line of %file).', $variables);

      return FALSE;
    }
  }

  /**
   * Check if our new ticket id somehow exists.
   */
  private function ticketIdExists($ticket_id) {
    $task_ids = \Drupal::entityQuery('burndown_task')
      ->condition('ticket_id', $ticket_id)
      ->accessCheck(FALSE)
      ->execute();
    return !empty($task_ids);
  }

}
