<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\symfony_mailer\Attachment;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Provides the legacy mailer helper service.
 */
class LegacyMailerHelper implements LegacyMailerHelperInterface {

  /**
   * List of lower-cased address headers.
   *
   * Some address headers are stored directly in $message in addition to
   * $message['headers']. The array value indicates whether this is the case.
   */
  protected const array ADDRESS_HEADERS = [
    'from' => TRUE,
    'reply-to' => TRUE,
    'to' => TRUE,
    'cc' => FALSE,
    'bcc' => FALSE,
  ];

  /**
   * List of lower-cased headers to skip copying from the array.
   */
  protected const array SKIP_HEADERS = [
    // Set by Symfony mailer library.
    'content-transfer-encoding' => TRUE,
    'content-type' => TRUE,
    'date' => TRUE,
    'message-id' => TRUE,
    'mime-version' => TRUE,

    // Set by sending MTA.
    'return-path' => TRUE,
  ];

  /**
   * Constructs the LegacyMailerHelper object.
   *
   * @param Drupal\mailer_override\ImportHelperInterface $importHelper
   *   The import helper.
   *
   * @internal
   */
  public function __construct(protected readonly ImportHelperInterface $importHelper) {}

  /**
   * {@inheritdoc}
   */
  public function formatBody(array $body_array): array {
    foreach ($body_array as $part) {
      if ($part instanceof MarkupInterface) {
        $body[] = ['#markup' => $part];
      }
      else {
        $body[] = [
          '#type' => 'processed_text',
          '#text' => $part,
        ];
      }
    }
    return $body ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function emailToArray(EmailInterface $email, array &$message): void {
    $message['subject'] = $email->getSubject();
    if ($email->getPhase() >= EmailInterface::PHASE_POST_RENDER) {
      $message['body'] = $email->getHtmlBody();
    }

    foreach ($email->getHeaders()->all() as $name => $header) {
      // Symfony stores headers in lower-case.
      if (isset(self::SKIP_HEADERS[$name])) {
        continue;
      }

      // Drupal message array stores headers in camel-case, except 'Reply-to'
      // is non-standard.
      $legacy_name = ($name == 'reply-to') ? 'Reply-to' : ucwords($name, '-');

      // Copy to 'headers'.
      $message['headers'][$legacy_name] = $header->getBodyAsString();

      if (!empty(self::ADDRESS_HEADERS[$name])) {
        // Also copy directly to $message, as lower-case.
        $message[$name] = $message['headers'][$legacy_name];
      }
    }

    // Drupal doesn't store the 'To' header in $message['headers'].
    unset($message['headers']['To']);
  }

  /**
   * {@inheritdoc}
   */
  public function emailFromArray(EmailInterface $email, array $message): void {
    $email->setSubject($message['subject']);

    // Attachments.
    $attachments = $message['params']['attachments'] ?? [];
    foreach ($attachments as $attachment) {
      if (!empty($attachment['filepath'])) {
        $at = Attachment::fromPath($attachment['filepath'], $attachment['filename'] ?? NULL, $attachment['filemime'] ?? NULL);
        // On the legacy interface, the code that sets an attachment is
        // responsible for access checking.
        $at->setAccess(AccessResult::allowed());
        $email->attach($at);
      }
      elseif (!empty($attachment['filecontent'])) {
        $email->attach(Attachment::fromData($attachment["filecontent"], $attachment['filename'] ?? NULL, $attachment['filemime'] ?? NULL));
      }
    }

    // Headers.
    $src_headers = $message['headers'];
    $dest_headers = $email->getHeaders();

    // Add in 'To' header which is stored directly in the message.
    // @see \Drupal\Core\Mail\Plugin\Mail\PhpMail::mail()
    if (isset($message['to'])) {
      $src_headers['to'] = $message['to'];
    }

    foreach ($src_headers as $name => $value) {
      $name = strtolower($name);
      if (isset(self::SKIP_HEADERS[$name])) {
        continue;
      }

      if (isset(self::ADDRESS_HEADERS[$name])) {
        $email->setAddress($name, $this->importHelper->parseAddress($value), TRUE);
      }
      else {
        $dest_headers->addHeader($name, $value);
      }
    }

    // Plain-text version.
    if (isset($message['plain'])) {
      // The legacy mail API permits a stringifiable Markup object here (core's
      // PhpMail cast it implicitly); setTextBody() requires a string.
      $email->setTextBody((string) $message['plain']);
    }

    // Parameters.
    foreach ($message['params'] as $key => $value) {
      $email->setParam($key, $value);
    }
  }

}
