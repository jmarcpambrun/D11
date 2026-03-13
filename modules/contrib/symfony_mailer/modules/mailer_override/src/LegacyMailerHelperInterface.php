<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Provides the legacy mailer helper service.
 */
interface LegacyMailerHelperInterface {

  /**
   * Formats a legacy message body as a render array.
   *
   * The message body is received as an array of lines that are either strings
   * or objects implementing \Drupal\Component\Render\MarkupInterface. Any lines
   * that are not already safe markup are escaped using the fallback filter
   * format.
   *
   * @param array $body_array
   *   The array of lines.
   *
   * @return array
   *   The render array.
   *
   * @see \Drupal\Core\Mail\MailInterface::format()
   */
  public function formatBody(array $body_array): array;

  /**
   * Fills a message array from an Email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to fill from.
   * @param array $message
   *   The array to fill.
   */
  public function emailToArray(EmailInterface $email, array &$message): void;

  /**
   * Fills an Email from a message array.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to fill.
   * @param array $message
   *   The array to fill from.
   */
  public function emailFromArray(EmailInterface $email, array $message): void;

}
