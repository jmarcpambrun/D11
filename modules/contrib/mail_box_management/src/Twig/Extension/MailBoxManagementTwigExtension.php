<?php

namespace Drupal\mail_box_management\Twig\Extension;

use Drupal\mail_box_management\Server\Imap\Flag;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * MailBoxManagementTwigExtension. contains MailBoxManagementTwigExtension.
 *
 * @file
 * MailBoxManagementTwigExtension. contains MailBoxManagementTwigExtension.
 */

/**
 * MailBoxManagementTwigExtension is for twig function registration.
 *
 * @class
 * MailBoxManagementTwigExtension is for twig function registration.
 */
class MailBoxManagementTwigExtension extends AbstractExtension {

  /**
   * Registers custom Twig functions.
   *
   * @return \Twig\TwigFunction[]
   *   Array of TwigFunction
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('mail_box_color', [$this, 'getColor']),
      new TwigFunction('is_unseen', [$this, 'isUnseen']),
      new TwigFunction('is_flagged', [$this, 'isFlagged']),
      new TwigFunction('is_recent', [$this, 'isRecent']),
      new TwigFunction('json', [$this, 'json']),
      new TwigFunction('box_icon', [$this, 'getIcon']),
      new TwigFunction('attachment_size', [$this, 'getSize']),
      new TwigFunction('mail_actions', [$this, 'getActions']),
      new TwigFunction('mail_strip', [$this, 'mailStrip']),
    ];
  }

  /**
   * Any sting data.
   *
   * @param string|null $any_string
   *   String data.
   *
   * @return string
   *   Returns color name.
   */
  public function getColor(?string $any_string): string {
    if (is_null($any_string)) {
      return 'Blue';
    }
    $colors = [
      "A" => [
        "Almond",
      ],
      "B" => ["Beige"],
      "C" => [
        "Carmine",
      ],
      "D" => ["Denim"],
      "E" => ["Emerald"],
      "F" => ["Fuchsia"],
      "G" => ["Green"],
      "H" => ["Harlequin"],
      "I" => ["Indigo"],
      "J" => ["Jasmine"],
      "K" => ["Khaki"],
      "L" => ["Lavender"],
      "M" => ["Magenta"],
      "N" => ["Navy"],
      "O" => ["Ochre"],
      "P" => [
        "Purple",
      ],
      "Q" => ["Quick Silver"],
      "R" => ["Red"],
      "S" => ["Silver"],
      "T" => ["Turquoise"],
      "U" => ["Ultramarine"],
      "V" => ["Violet"],
      "W" => ["White"],
      "Y" => ["Yellow"],
      "Z" => ["Zambezi"],
    ];

    $first_letter = strtoupper(substr($any_string, 0, 1));
    $picked_color = $colors[$first_letter];
    $index = array_rand($picked_color);
    return $picked_color[$index];
  }

  /**
   * Check for mail has unseen flag.
   *
   * @param mixed $any_string
   *   Unseen or seen flags.
   *
   * @return bool
   *   True if mail is unseen.
   */
  public function isUnseen(mixed $any_string): bool {
    return !empty(trim($any_string));
  }

  /**
   * Check if mail is flagged.
   *
   * @param mixed $any_string
   *   Flagged string.
   *
   * @return bool
   *   True if mail is flagged.
   */
  public function isFlagged(mixed $any_string): bool {
    return !empty(trim($any_string));
  }

  /**
   * Check if mail is recent.
   *
   * @param mixed $any_string
   *   Recent flag.
   *
   * @return bool
   *   True is recent mail.
   */
  public function isRecent(mixed $any_string): bool {
    return !empty(trim($any_string));
  }

  /**
   * Make data into json.
   *
   * @param mixed $any
   *   Any data.
   *
   * @return string
   *   Json encoded data is returned.
   */
  public function json(mixed $any): string {
    return json_encode($any, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Getting icon class for mailbox name.
   *
   * @param string $box_name
   *   Mailbox name.
   *
   * @return string
   *   class name is return.
   */
  public function getIcon(string $box_name): string {
    $box_name = strtolower($box_name);
    $remixIcons = [
      'mailboxes' => [
    // Inbox.
        'inbox' => 'ri-inbox-line',
    // Sent.
        'sent' => 'ri-send-plane-line',
    // Drafts.
        'drafts' => 'ri-file-list-2-line',
    // Trash.
        'trash' => 'ri-delete-bin-line',
    // Spam/Junk.
        'spam' => 'ri-error-warning-line',
    // Archive.
        'archive' => 'ri-archive-line',
    // Outbox.
        'outbox' => 'ri-send-plane-2-line',
    // All Mail.
        'all_mail' => 'ri-mail-line',
    // Important.
        'important' => 'ri-star-line',
    // Starred.
        'starred' => 'ri-star-fill',
    // Starred Mail.
        'starred_mail' => 'ri-star-half-line',
        'junk' => 'ri-error-warning-line',
        'default_folder' => 'ri-folder-line',
      ],
      'labels' => [
      // Work.
        'work' => 'ri-briefcase-line',
      // Personal.
        'personal' => 'ri-user-line',
      // Finance.
        'finance' => 'ri-wallet-line',
      // Shopping.
        'shopping' => 'ri-shopping-cart-line',
      // Social.
        'social' => 'ri-share-box-line',
      // Notifications.
        'notifications' => 'ri-notification-line',
      // Projects.
        'projects' => 'ri-folder-line',
      // Follow Up.
        'follow_up' => 'ri-checkbox-circle-line',
      // Urgent.
        'urgent' => 'ri-alert-line',
      ],
    ];
    return $remixIcons['mailboxes'][$box_name] ?? $remixIcons['labels'][$box_name] ?? $remixIcons['mailboxes']['default_folder'];
  }

  /**
   * Get file size.
   *
   * @param mixed $any_string
   *   File base64.
   *
   * @return string
   *   Returns string rg 1MB
   */
  public function getSize(mixed $any_string): string {
    $length = strlen($any_string);

    if ($length < 1024) {
      // Size in bytes.
      return $length . ' bytes';
    }

    $kb = $length / 1024;
    if ($kb < 1024) {
      // Size in kilobytes.
      return number_format($kb, 2) . ' KB';
    }

    $mb = $kb / 1024;
    if ($mb < 1024) {
      // Size in megabytes.
      return number_format($mb, 2) . ' MB';
    }

    $gb = $mb / 1024;
    if ($gb < 1024) {
      // Size in gigabytes.
      return number_format($gb, 2) . ' GB';
    }

    $tb = $gb / 1024;
    // Size in terabytes.
    return number_format($tb, 2) . ' TB';
  }

  /**
   * Returns supported action flags.
   *
   * @return string[]
   *   Array of action flags.
   */
  public function getActions(): array {
    return [
      Flag::FLAGGED => 'Flagged',
      Flag::UNFLAGGED => 'Unflagged',
      Flag::SEEN => 'Seen',
      Flag::UNSEEN => 'Unseen',
    ];
  }

  /**
   * Get plain email from to address string and from address string.
   *
   * @param string|null $any_string
   *   String collected from to address or from address.
   *
   * @return string|null
   *   Returns plain mail without <>
   */
  public function mailStrip(string|null $any_string): ?string {
    if (is_null($any_string)) {
      return NULL;
    }
    $start = strpos($any_string, '<');
    $end = strpos($any_string, '>');

    if ($start !== FALSE && $end !== FALSE) {
      return substr($any_string, $start + 1, $end - $start - 1);
    }
    return $any_string;
  }

}
