<?php

namespace Drupal\mailboxlayer\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Process mailboxlayer tasks.
 *
 * @QueueWorker(
 *   id = "mailboxlayer_log_removal",
 *   title = @Translation("Mailboxlayer Log Queue Worker"),
 *   cron = {"time" = 60}
 * )
 */
class MailboxlayerLogRemoval extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Deleting all the entries from the mailboxlayer log table.
    $connection = \Drupal::database();
    $connection->delete('mailboxlayer_log')
      ->condition('id', $data, '<=')
      ->execute();
  }

}
