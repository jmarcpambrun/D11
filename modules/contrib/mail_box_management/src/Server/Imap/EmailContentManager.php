<?php

namespace Drupal\mail_box_management\Server\Imap;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\mail_box_management\Plugin\FileType;
use Symfony\Component\Yaml\Yaml;

/**
 * File EmailContentManager.php contains class EmailContentManager.
 *
 * @file
 * EmailContentManager.php contains class EmailContentManager.
 */

/**
 * EmailContentManager is responsible for all mail content managements.
 *
 * @class
 * EmailContentManager is responsible for all mail content managements.
 */
class EmailContentManager {

  /**
   * Status messages.
   *
   * @var string
   */
  public string $message;

  /**
   * Initializing the EmailContentManager object.
   *
   * @param \Drupal\mail_box_management\Server\Imap\ImapConnection $connection
   *   ImapConnection class object.
   */
  public function __construct(private readonly ImapConnection $connection) {
    $this->message = '';
  }

  /**
   * Get mails with all replies references.
   *
   * @param string $mailbox
   *   Mailbox namespace.
   * @param int $message_num
   *   Mail number.
   *
   * @return array|array[]
   *   Returns mail with referencing mails.
   */
  public function getContentWithReferences(string $mailbox, int $message_num): array {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return [];
    }
    if (imap_reopen($copy_connection, $mailbox)) {
      $header = imap_headerinfo($copy_connection, $message_num);
      $answered = trim(($header->Answered ?? ''));
      if (empty($answered)) {
        return [$this->getContent($mailbox, $message_num)];
      }
      $boxes = new MailBoxStorage($this->connection);
      $mailboxes = $boxes->getMailboxes();
      $first_mail = $this->getContent($mailbox, $message_num);
      $all_mails = [
        0 => $first_mail,
      ];
      foreach ($mailboxes as $key => $mailbox) {
        if ($key !== 'Trash') {
          $emails = $this->getMessageNumbersByInReplyTo($mailbox, $header->message_id);
          if (!empty($emails)) {
            foreach ($emails as $email_id) {
              $all_mails[] = $this->getContent($mailbox, $email_id);
            }
          }
        }
      }
      if (count($all_mails) > 2) {
        // Sort the emails by the `date` field.
        usort($all_mails, function ($a, $b) {
          $dateA = strtotime($a['header']['date']);
          $dateB = strtotime($b['header']['date']);
          // Oldest to newest.
          return $dateA <=> $dateB;
        });
      }
      return $all_mails;
    }
    return [];
  }

  /**
   * Read mail content.
   *
   * @param string $mailbox
   *   Mailbox namespace.
   * @param int $message_num
   *   Mail number.
   *
   * @return array
   *   Returns mail header data, content and attachments if exist.
   */
  public function getContent(string $mailbox, int $message_num): array {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return [];
    }
    $data = [];

    if (imap_reopen($copy_connection, $mailbox)) {
      $structure = imap_fetchstructure($copy_connection, $message_num);
      $header = imap_headerinfo($copy_connection, $message_num);
      // Check if 'from' and 'to' fields are set and not empty.
      $fromName = !empty($header->from) && isset($header->from[0]->personal) ? $header->from[0]->personal : NULL;
      $fromEmail = !empty($header->from) && isset($header->from[0]->mailbox) ? $header->from[0]->mailbox : NULL;
      $fromHost = !empty($header->from) && isset($header->from[0]->host) ? $header->from[0]->host : NULL;
      $toName = !empty($header->to) && isset($header->to[0]->personal) ? $header->to[0]->personal : NULL;
      $toEmail = !empty($header->to) && isset($header->to[0]->mailbox) ? $header->to[0]->mailbox : NULL;
      $toHost = !empty($header->to) && isset($header->to[0]->host) ? $header->to[0]->host : NULL;
      $headers = [
        'to_mail' => $toEmail,
        'from_mail' => $fromEmail,
        'from_name' => $fromName,
        'to_name' => $toName,
        'box' => $mailbox,
        ...((array) $header),
      ];
      $data['header'] = $headers;
      $data['body'] = NULL;
      $data['attachments'] = [];

      if ($structure) {
        if ($structure->subtype === 'ALTERNATIVE') {
          // Handle alternative structure.
          foreach ($structure->parts as $key => $part) {
            if ($part->subtype === 'HTML') {
              // IMAP sections are 1-based.
              $section = $key + 1;
              $body = imap_fetchbody($copy_connection, $message_num, $section);
              $htmlContent = $this->decodeContent($body, $part->encoding);
              $data['body'] = [
                'type' => 'HTML',
                'content' => $this->extractBodyContent($htmlContent),
              ];
              break;
            }
          }
        }
        elseif ($structure->subtype === 'MIXED') {
          // Handle mixed structure.
          foreach ($structure->parts as $key => $part) {
            if (!empty($part->parts)) {
              foreach ($part->parts as $sub_key => $sub_part) {
                if ($sub_part->subtype === 'HTML') {
                  $section = ($key + 1) . '.' . ($sub_key + 1);
                  $body = imap_fetchbody($copy_connection, $message_num, $section);
                  $htmlContent = $this->decodeContent($body, $sub_part->encoding);
                  $data['body'] = [
                    'type' => 'HTML',
                    'content' => $this->extractBodyContent($htmlContent),
                  ];
                  break;
                }
              }
            }
            elseif (isset($part->disposition) && strtoupper($part->disposition) === 'ATTACHMENT') {
              $file_name = $this->getParameter($part->parameters, 'name');
              $list = explode('.', $file_name ?? '');
              $type_icon = FileType::getIconClass(end($list));
              $attachment = [
                'type' => $part->subtype ?? 'UNKNOWN',
                'name' => $file_name,
                'icon' => $type_icon,
                'base64' => FileType::getBase64Type(end($list)),
                'content' => imap_fetchbody($copy_connection, $message_num, $key + 1),
              ];
              $data['attachments'][] = $attachment;
            }
          }
        }
        else {

          // Same emails will fail by structure retrieval.
          $content = imap_body($copy_connection, $message_num);
          $data['body'] = [
            'type' => 'HTML',
            'content' => $content,
          ];
        }
      }
    }

    return $data;
  }

  /**
   * Extract inner html from html body content.
   *
   * @param string $htmlContent
   *   Mail body contents.
   *
   * @return string
   *   Returns inner html of body content.
   */
  private function extractBodyContent(string $htmlContent): string {
    return $htmlContent;
  }

  /**
   * Decoding mail body content.
   *
   * @param string $content
   *   Content from server to be decoded.
   * @param int $encoding
   *   Encoding number.
   *
   * @return string
   *   decoded string.
   */
  private function decodeContent(string $content, int $encoding): string {
    return match ($encoding) {
      ENCBASE64 => base64_decode($content),
      ENCQUOTEDPRINTABLE => quoted_printable_decode($content),
      default => $content,
    };
  }

  /**
   * Retrieve a specific parameter value from IMAP structure parameters.
   *
   * @param array $parameters
   *   Array of retrieved parameters.
   * @param string $key
   *   Key of parameter to get.
   *
   * @return string|null
   *   Returns value of parameter.
   */
  private function getParameter(array $parameters, string $key): ?string {
    foreach ($parameters as $param) {
      if (strtolower($param->attribute) === strtolower($key)) {
        return $param->value;
      }
    }
    return NULL;
  }

  /**
   * Get all email numbers of reply mails.
   *
   * @param string $mailbox
   *   Mailbox namespace where mail is located.
   * @param string $message_uid
   *   String uid of mail.
   *
   * @return array
   *   Returns array of message numbers.
   */
  public function getMessageNumbersByInReplyTo(string $mailbox, string $message_uid): array {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return [];
    }
    $emails = [];
    // Reopen the mailbox.
    if (imap_reopen($copy_connection, $mailbox)) {

      $email_ids = imap_search($copy_connection, "ALL");
      if (!empty($email_ids)) {
        foreach ($email_ids as $email_id) {
          $header = imap_headerinfo($copy_connection, $email_id);
          if (!empty($header->message_id) && trim($header->message_id) !== trim($message_uid)) {
            if (str_contains(trim($header->references ?? ''), $message_uid)) {
              $emails[] = trim($header->Msgno);
            }
          }
        }
        return $emails;
      }
    }
    return $emails;
  }

  /**
   * Apply flag to mail.
   *
   * @param string $mailbox
   *   Mailbox namespace.
   * @param int $message_num
   *   Mail number.
   * @param string $status
   *   Status name Seen,flagged etc.
   *
   * @return bool
   *   True if flag was applied.
   */
  public function updateMessageFlags(string $mailbox, int $message_num, string $status): bool {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return FALSE;
    }
    // Reopen the mailbox.
    if (imap_reopen($copy_connection, $mailbox)) {
      switch ($status) {
        case 'flagged':
          // Mark as flagged.
          imap_setflag_full($copy_connection, $message_num, "\\Flagged");
          break;

        case 'unflagged':
          // Unflag the message.
          imap_clearflag_full($copy_connection, $message_num, "\\Flagged");
          break;

        case 'unread':
          // Mark as unread (unseen)
          imap_setflag_full($copy_connection, $message_num, "\\Unseen");
          break;

        case 'read':
          // Mark as read (seen)
          imap_setflag_full($copy_connection, $message_num, "\\Seen");
          break;

        default:
          // Invalid status.
          return FALSE;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Deleting mail.
   *
   * @param string $mailbox
   *   Mailbox namespace.
   * @param int $message_num
   *   Mailbox number.
   *
   * @return bool
   *   True if mail was deleted.
   */
  public function deleteEmail(string $mailbox, int $message_num): bool {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return FALSE;
    }
    // Reopen the mailbox.
    if (imap_reopen($copy_connection, $mailbox)) {
      // Mark the email for deletion.
      if (imap_delete($copy_connection, $message_num)) {
        // Expunge to permanently remove the email.
        return imap_expunge($copy_connection);
      }
    }
    return FALSE;
  }

  /**
   * Get messages numbers in mailbox namespace.
   *
   * @param string $mailbox
   *   Mailbox namespace name.
   *
   * @return array|bool
   *   Returns array of numbers.
   */
  public function getAllEmailMsgno(string $mailbox): array|bool {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return FALSE;
    }
    if (imap_reopen($copy_connection, $mailbox)) {
      return imap_search($copy_connection, "ALL");
    }
    return FALSE;
  }

  /**
   * Worker handler.
   *
   * @param string $mailbox
   *   Mailbox namespace.
   * @param int $owner_id
   *   Owner uid.
   *
   * @return array
   *   Returns all data from given mailbox namespace.
   *
   * @throws \Exception
   */
  public function worker(string $mailbox, int $owner_id): array {

    $mail_box_emails = $this->getAllEmailMsgno($mailbox);

    if (empty($mail_box_emails)) {
      return [];
    }
    $isolate_old = [];
    /**@var \Drupal\mail_box_management\Plugin\LocalStorage $local_storage**/
    $local_storage = mail_box_management_service('mail_box_management.local.storage');
    foreach ($mail_box_emails as $key => $message_number) {
      $content_found = $local_storage->getMail($mailbox, $message_number, $owner_id);
      if (!empty($content_found)) {
        $content_found = Yaml::parseFile($content_found['content_yml']);
        if (is_array($content_found)) {
          $isolate_old[] = $content_found;
          unset($mail_box_emails[$key]);
        }
      }
    }

    if (!empty($mail_box_emails)) {
      foreach ($mail_box_emails as $mailbox_email) {
        $data = $this->getContentWithReferences($mailbox, $mailbox_email);
        $local_storage->setMail($mailbox, $mailbox_email, $data, $owner_id);
        $isolate_old[] = $data;
      }
    }
    return $isolate_old;
  }

  /**
   * Sending mail.
   *
   * @param array $data
   *   Data of mail to be sent.
   *
   * @return bool
   *   True if mail was sent.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function sendMail(array $data): bool {

    $files = [];
    if ($data['attachments']) {
      $directory = "public://mailbox_management/attachments";
      $file_system = mail_box_management_service('file_system');
      $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      foreach ($data['attachments'] as $attachment) {
        $full_uri = $directory . "/" . $attachment['name'];
        $file_system->saveData(base64_decode($attachment['base64']), $full_uri);
        $new_file = File::create(['uri' => $full_uri]);
        $new_file->setOwnerId(1);
        $new_file->setPermanent();
        $new_file->save();
        $files[] = $new_file->id();
      }
    }
    $params['subject'] = $data['subject'];
    $params['message'] = $data['message'];
    $params['from'] = mail_box_management_service('config.factory')->get('system.site')->get('mail');
    if (!empty($files)) {
      $params['attachments'] = $files;
    }
    $mail_manager = mail_box_management_service('plugin.manager.mail');
    $result = $mail_manager->mail('mail_box_management', 'mail_box_management_key', $data['recipient'], 'en', $params, NULL, TRUE);
    if (empty($result['result'])) {
      $this->message = "Mail failed to send.";
      return FALSE;
    }
    $this->message = "Mail sent to {$data['recipient']} successfully.";
    return TRUE;
  }

  /**
   * Drafting the mail.
   *
   * @param array $data
   *   Mail data to save to draft mailbox.
   *
   * @return bool
   *   True if mail was saved.
   */
  public function saveDraft(array $data): bool {
    $imap_user = $this->connection->configUsername->get('imap_user_name');
    $mailboxes = new MailBoxStorage($this->connection);
    $mailboxes = $mailboxes->getMailboxes();
    $draft_box = NULL;

    // Find the Drafts folder.
    if (!empty($mailboxes)) {
      foreach ($mailboxes as $mailbox) {
        if (str_ends_with($mailbox, 'Draft') || str_ends_with($mailbox, 'Drafts')) {
          $draft_box = $mailbox;
          break;
        }
      }
    }

    if (empty($draft_box)) {
      return FALSE;
    }

    // Email details.
    $to = $data['to'];
    $subject = $data['subject'];
    $body = $data['body'];
    $attachments = $data['attachments'];

    // Prepare the email structure for `imap_mail_compose`.
    $envelope = [
      "from" => $imap_user,
      "to" => $to,
      "subject" => $subject,
    ];

    $body_part = [
      "type" => TYPETEXT,
      "subtype" => "html",
      "charset" => "UTF-8",
      "contents.data" => $body,
    ];

    // Add attachments.
    $parts = [$body_part];
    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        /** @var \Drupal\file\Entity\File $file */
        $file = $attachment;
        // Read file content.
        $file_content = file_get_contents($file->getFileUri());
        $file_name = $file->getFilename();

        $attachment_part = [
          "type" => TYPEAPPLICATION,
          "subtype" => "octet-stream",
          "encoding" => ENCBASE64,
          "disposition.type" => "attachment",
          "disposition" => ["filename" => $file_name],
          "description" => $file_name,
          "contents.data" => base64_encode($file_content),
        ];

        $parts[] = $attachment_part;
      }
    }

    // Compose the email using imap_mail_compose.
    $email_content = imap_mail_compose($envelope, $parts);
    // Save the email as a draft using imap_append.
    $copy_connection = $this->connection->imapConnection;
    $save_result = imap_append($copy_connection, $draft_box, $email_content);
    imap_close($copy_connection);

    if ($save_result) {
      $this->message = "Mail saved to Draft successfully.";
      return TRUE;
    }
    else {
      $this->message = "Mail failed to save to Draft.";
      return FALSE;
    }

  }

  /**
   * Copying mail from box to box.
   *
   * @param int $msgno
   *   Mail number.
   * @param string $source
   *   Source namespace.
   * @param string $destination
   *   Destination namespace.
   *
   * @return bool
   *   Return true if copying was successful.
   */
  public function copyEmails(int $msgno, string $source, string $destination): bool {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return FALSE;
    }
    // Reopen the source mailbox.
    if (!imap_reopen($copy_connection, $source)) {
      $this->message = 'Failed to reopen source folder: ' . imap_last_error();
      return FALSE;
    }

    // Attempt to copy the message.
    if (imap_mail_copy($copy_connection, (string) $msgno, $destination)) {
      return TRUE;
    }
    else {
      $this->message = 'Failed to copy message: ' . imap_last_error();
    }

    return FALSE;
  }

  /**
   * Moving mail from box to box.
   *
   * @param int $msgno
   *   Mail number.
   * @param string $source
   *   Source namespace.
   * @param string $destination
   *   Destination namespace.
   *
   * @return bool
   *   Return true if moving was successful.
   */
  public function moveEmails(int $msgno, string $source, string $destination): bool {
    $copy_connection = $this->connection->imapConnection;
    if (!$copy_connection) {
      return FALSE;
    }
    // Reopen the source mailbox.
    if (!imap_reopen($copy_connection, $source)) {
      $this->message = 'Failed to reopen source folder: ' . imap_last_error();
      return FALSE;
    }

    // Attempt to move the message.
    if (imap_mail_move($copy_connection, (string) $msgno, $destination)) {
      // Expunge messages marked for deletion.
      if (imap_expunge($copy_connection)) {
        return TRUE;
      }
      else {
        $this->message = 'Failed to expunge messages: ' . imap_last_error();
      }
    }
    else {
      $this->message = 'Failed to move message';
    }

    return FALSE;
  }

  /**
   * Sending reply email.
   *
   * @param array $data
   *   Email data to send.
   *
   * @return bool
   *   True if reply was sent.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function sendReplyMail(array $data) {
    $files = [];
    if ($data['attachments']) {
      $directory = "public://mailbox_management/attachments";
      $file_system = mail_box_management_service('file_system');
      $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      foreach ($data['attachments'] as $attachment) {
        $full_uri = $directory . "/" . $attachment['name'];
        $file_system->saveData(base64_decode($attachment['base64']), $full_uri);
        $new_file = File::create(['uri' => $full_uri]);
        $new_file->setOwnerId(1);
        $new_file->setPermanent();
        $new_file->save();
        $files[] = $new_file->id();
      }
    }
    $params['subject'] = $data['subject'];
    $params['message'] = $data['message'];
    $params['from'] = mail_box_management_service('config.factory')->get('system.site')->get('mail');
    $params['headers'] = [
      'In-Reply-To' => $data['message_id'],
      'Reply-To' => $data['recipient'] ,
      'References' => $data['message_id'],
    ];

    if (!empty($files)) {
      $params['attachments'] = $files;
    }

    $mail_manager = mail_box_management_service('plugin.manager.mail');
    $result = $mail_manager->mail('mail_box_management', 'mail_box_management_key', $data['recipient'], 'en', $params, NULL, TRUE);
    if (empty($result['result'])) {
      $this->message = "Mail failed to send.";
      return FALSE;
    }
    $this->message = "Mail sent to {$data['recipient']} successfully.";
    return TRUE;
  }

  /**
   * Get single email content with cache consideration.
   *
   * @param string $mailbox_name
   *   Namespace.
   * @param int $msgno
   *   Message number.
   * @param int $owner_id
   *   Owner uid.
   *
   * @return array[]|null
   *   return array of headers, contents.
   */
  public function getByLoaderCall(string $mailbox_name, int $msgno, int $owner_id): ?array {
    $content = mail_box_management_service('mail_box_management.local.storage')
      ->getMail($mailbox_name, $msgno, $owner_id);
    if (empty($content)) {
      return $this->getContentWithReferences($mailbox_name, $msgno);
    }
    if (!file_exists($content['content_yml'])) {
      return $this->getContentWithReferences($mailbox_name, $msgno);
    }
    return Yaml::parseFile($content['content_yml']);
  }

  /**
   * Search by Mail.
   *
   * @param string $mailbox_name
   *   Mail namespace.
   * @param string $search
   *   Search from email address.
   *
   * @return array
   *   Returns found headers.
   */
  public function searchByMail(string $mailbox_name, string $search): array {
    $copy_connection = $this->connection->imapConnection;
    if (imap_reopen($copy_connection, $mailbox_name)) {

      // Search for emails.
      $emails = $this->getAllEmailMsgno($mailbox_name);
      if (empty($emails)) {
        return [];
      }
      $local = mail_box_management_service('mail_box_management.local.storage');
      $found = [];
      foreach ($emails as $email_number) {
        $mail_content = $local->getMail($mailbox_name, $email_number);
        if (!empty($mail_content)) {
          $parsed_mail_content = Yaml::parseFile($mail_content['content_yml']);
          $header = $parsed_mail_content[0]['header'] ??
            $parsed_mail_content[1]['header'] ??
            $parsed_mail_content[2]['header'] ?? [];
          if (!empty($header['fromaddress'])) {
            $percentage = 0;
            similar_text($header['fromaddress'], $search, $percentage);
            if ($percentage > 70) {
              $found[] = $header;
            }
          }
        }
        else {
          $data = $this->getContentWithReferences($mailbox_name, $email_number);
          if (!empty($data['header']['fromaddress'])) {
            $percentage = 0;
            similar_text($data['header']['fromaddress'], $search, $percentage);
            if ($percentage > 70) {
              $found[] = $data['header'];
            }
            $local->setMail($mailbox_name, $email_number, $data);
          }
        }
      }
      return $found;
    }
    return [];
  }

  /**
   * Search by subject.
   *
   * @param string $mailbox_name
   *   Mailbox namespace.
   * @param string $search
   *   Search string.
   *
   * @return array
   *   Returns array of results.
   *
   * @throws \Exception
   */
  public function searchBySubject(string $mailbox_name, string $search): array {
    $copy_connection = $this->connection->imapConnection;
    if (imap_reopen($copy_connection, $mailbox_name)) {

      // Search for emails.
      $emails = $this->getAllEmailMsgno($mailbox_name);
      if (empty($emails)) {
        return [];
      }
      $local = mail_box_management_service('mail_box_management.local.storage');
      $found = [];
      foreach ($emails as $email_number) {
        $mail_content = $local->getMail($mailbox_name, $email_number);
        if (!empty($mail_content)) {
          $parsed_mail_content = Yaml::parseFile($mail_content['content_yml']);
          $header = $parsed_mail_content[0]['header'] ??
            $parsed_mail_content[1]['header'] ??
            $parsed_mail_content[2]['header'] ?? [];
          if (!empty($header['Subject'])) {
            $percentage = 0;
            similar_text($header['Subject'], $search, $percentage);
            if ($percentage > 50) {
              $found[] = $header;
            }
          }
        }
        else {
          $data = $this->getContentWithReferences($mailbox_name, $email_number);
          if (!empty($data['header']['Subject'])) {
            $percentage = 0;
            similar_text($data['header']['Subject'], $search, $percentage);
            if ($percentage > 50) {
              $found[] = $data['header'];
            }
            $local->setMail($mailbox_name, $email_number, $data);
          }
        }
      }
      return $found;
    }
    return [];
  }

}
