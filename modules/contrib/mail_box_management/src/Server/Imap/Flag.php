<?php

namespace Drupal\mail_box_management\Server\Imap;

/**
 * File Flag contains class Flag.
 *
 * @file
 * Flag contains class Flag.
 */

/**
 * Class Flag contains Flags to be used for flagged emails.
 *
 * @class
 * Flag contains Flags to be used for flagged emails.
 */
class Flag {

  /**
   * Email Seen flag.
   */
  const SEEN = "\\Seen";

  /**
   * Email not seen flag.
   */
  const UNSEEN = "\\Unseen";

  /**
   * Email flagged, Starred flag.
   */
  const FLAGGED = "\\Flagged";

  /**
   * Email removed flagged, starred flag.
   */
  const UNFLAGGED = "\\Unflagged";

}
