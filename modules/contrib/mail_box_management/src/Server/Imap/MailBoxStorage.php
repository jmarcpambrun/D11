<?php

namespace Drupal\mail_box_management\Server\Imap;

/**
 * Contains MailBoxStorage class.
 *
 * @file
 * MailBoxStorage.php
 */

/**
 * MailBoxStorage for generate mailbox activities.
 *
 * @class
 * MailBoxStorage for generate mailbox activities.
 */
class MailBoxStorage {

  /**
   * List of mailboxes collected from server.
   *
   * @var array|bool
   */
  private array|bool $mailboxes;

  public function __construct(private readonly ImapConnection $connection) {
    if ($this->connection->imapConnection) {
      $this->mailboxes = imap_list($this->connection->imapConnection, $this->connection->hostname, '*');
    }
    else {
      $this->mailboxes = [];
    }
  }

  /**
   * Returns mailboxes.
   *
   * @return array|bool
   *   Mailboxes list with key as mailbox name and value as namespace name.
   */
  public function getMailboxes(): array|bool {
    if (!empty($this->mailboxes)) {
      $new_array = [];
      foreach ($this->mailboxes as $mailbox) {
        $list = explode('}', $mailbox);
        $mailbox_name = end($list);
        if (str_contains($mailbox_name, '.')) {
          $list = explode('.', $mailbox_name);
          $mailbox_name = end($list);
        }
        if (str_contains($mailbox_name, '/')) {
          $list = explode('/', $mailbox_name);
          $mailbox_name = end($list);
        }
        $new_array[ucfirst(strtolower($mailbox_name))] = $mailbox;
      }
      return $new_array;
    }
    return $this->mailboxes;
  }

  /**
   * Create mailbox name folder on server.
   *
   * @param string $mailbox_name
   *   Folder name to create.
   *
   * @return bool
   *   True if mailbox was created.
   */
  public function createMilBox(string $mailbox_name): bool {
    $full_name = $this->connection->hostname . "INBOX." . $mailbox_name;
    if (imap_createmailbox($this->connection->imapConnection, imap_utf7_encode($full_name))) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Deleting mailbox entirely.
   *
   * @param string $mailbox_name
   *   Folder name to delete.
   *
   * @return bool
   *   True if deletion was successfully
   */
  public function deleteMilBox(string $mailbox_name): bool {
    if (imap_deletemailbox($this->connection->imapConnection, $mailbox_name)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Deleting all mailboxes.
   *
   * @return bool
   *   True is return all times.
   */
  public function deleteAllMilBoxes(): bool {
    foreach ($this->mailboxes as $mailbox) {
      $this->deleteMilBox($mailbox);
    }
    return TRUE;
  }

  /**
   * Renaming the folder name.
   *
   * @param string $mailbox_name
   *   Old name to be renamed.
   * @param string $new_name
   *   New to name to changed to.
   *
   * @return bool
   *   True if folder name was renamed.
   */
  public function renameMilBox(string $mailbox_name, string $new_name): bool {
    if (imap_renamemailbox($this->connection->imapConnection, $mailbox_name, $new_name)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check if mailbox exist.
   *
   * @param string $mailbox_name
   *   Mailbox namespace to be checked.
   *
   * @return array|bool
   *   Return false if mailbox was not found or mailbox data if found.
   */
  public function checkMailboxStatus(string $mailbox_name): array|bool {
    $status = imap_status($this->connection->imapConnection, $mailbox_name, SA_ALL);
    return $status === FALSE ? FALSE : (array) $status;
  }

  /**
   * Get headers info of mailbox.
   *
   * @param string $mailbox_name
   *   Mailbox namespace string.
   *
   * @return array
   *   Returns headers information of mailbox.
   */
  public function getHeaders(string $mailbox_name): array {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return [];
    }
    // Open the mailbox.
    $inbox = imap_reopen($copy_connection, $mailbox_name);
    // Check if the mailbox opened successfully.
    $headers = [];
    if ($inbox) {
      // Search for all emails in the "Junks" mailbox.
      $emails = imap_search($copy_connection, 'ALL');

      if ($emails) {
        rsort($emails);
        $config_helper = mail_box_management_service('mail_box_management.config');
        $cache_mail_count_mailbox = $config_helper->get('cache_mail_count_mailbox')?->get('cache_mail_count_mailbox') ?? 10;
        $emails = count($emails) > $cache_mail_count_mailbox ? array_slice($emails, 0, $cache_mail_count_mailbox) : $emails;
        // Loop through each email and fetch headers and content.
        foreach ($emails as $emailId) {
          // Fetch the email header info.
          $header = imap_headerinfo($copy_connection, $emailId);
          // Check if 'from' and 'to' fields are set and not empty.
          $fromName = !empty($header->from) && isset($header->from[0]->personal) ? $header->from[0]->personal : NULL;
          $fromEmail = !empty($header->from) && isset($header->from[0]->mailbox) ? $header->from[0]->mailbox : NULL;
          $fromHost = !empty($header->from) && isset($header->from[0]->host) ? $header->from[0]->host : NULL;
          $toName = !empty($header->to) && isset($header->to[0]->personal) ? $header->to[0]->personal : NULL;
          $toEmail = !empty($header->to) && isset($header->to[0]->mailbox) ? $header->to[0]->mailbox : NULL;
          $toHost = !empty($header->to) && isset($header->to[0]->host) ? $header->to[0]->host : NULL;

          // Construct full email addresses.
          $fromEmail = $fromEmail ? $fromEmail . '@' . $fromHost : NULL;
          $toEmail = $toEmail ? $toEmail . '@' . $toHost : NULL;

          // Get the subject.
          $subject = $header->subject ?? '';

          // Collect headers.
          $headers[] = [
            'to_mail' => $toEmail,
            'from_mail' => $fromEmail,
            'subject' => $subject,
            'from_name' => $fromName,
            'to_name' => $toName,
            ...((array) $header),
          ];
        }
      }
    }

    // Sort headers by message number if more than 2 headers exist.
    if (count($headers) > 2) {
      usort($headers, function ($a, $b) {
        return intval($b['Msgno']) - intval($a['Msgno']);
      });
    }

    return $headers;
  }

  /**
   * Get page range.
   *
   * @param string $mailbox_name
   *   Mailbox namespace string.
   * @param int $per_page
   *   Page total emails.
   *
   * @return array|int[]
   *   Returns array of total number and pages number.
   */
  public function getTotalRange(string $mailbox_name, int $per_page = 20): array {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return [
        'total' => 0,
        'pages' => 0,
      ];
    }
    // Open the mailbox.
    $inbox = imap_reopen($copy_connection, $mailbox_name);
    // Check if the mailbox opened successfully.
    if (!$inbox) {
      return [
        'total' => 0,
        'pages' => 0,
      // Return 0 if unable to open mailbox.
      ];
    }

    // Get the total number of messages in the mailbox.
    $totalMessages = imap_num_msg($copy_connection);

    // Calculate the total number of pages needed (round up)
    $pages = ceil($totalMessages / $per_page);
    return [
      'total' => $totalMessages,
      'pages' => $pages,
    ];
  }

  /**
   * Returns array.
   *
   * @param string $mailbox_name
   *   Mailbox namespace string.
   * @param int $page
   *   Page number.
   * @param int $per_page
   *   Total mail per page.
   *
   * @return array
   *   Returns array.
   */
  public function getHeadersForPage(string $mailbox_name, int $page = 1, int $per_page = 20): array {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return [];
    }
    // Open the mailbox.
    $inbox = imap_reopen($copy_connection, $mailbox_name);
    if (!$inbox) {
      // Return empty array if unable to open mailbox.
      return [];
    }

    // Get the total number of messages in the mailbox.
    $totalMessages = imap_num_msg($copy_connection);

    // Calculate the starting message number for the given page.
    // The first email on the page.
    $startMessage = ($page - 1) * $per_page + 1;

    // Calculate the ending message number for the given page.
    // Ensure it doesn't exceed total emails.
    $endMessage = min($startMessage + $per_page - 1, $totalMessages);

    // Prepare the email range for imap_search.
    $emailRange = $startMessage . ':' . $endMessage;

    // Search for emails in the given range.
    $emails = imap_search($copy_connection, $emailRange, 'ALL');
    $headers = [];
    if ($emails) {
      // Loop through each email and fetch headers and content.
      foreach ($emails as $emailId) {
        // Fetch the email header info.
        $header = imap_headerinfo($copy_connection, $emailId);
        // Get "From" and "To" emails and names.
        $fromName = $header->from[0]->personal;
        $fromEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
        $toName = $header->to[0]->personal;
        $toEmail = $header->to[0]->mailbox . '@' . $header->to[0]->host;
        $subject = $header->subject;
        // $array = (array) $header;
        $headers[] = [
          'to_mail' => $toEmail,
          'from_mail' => $fromEmail,
          'subject' => $subject,
          'from_name' => $fromName,
          'to_name' => $toName,
          ...((array) $header),
        ];
      }
    }
    if (count($headers) > 2) {
      usort($headers, function ($a, $b) {
        return intval($b['Msgno']) - intval($a['Msgno']);
      });
    }

    return $headers;
  }

}
