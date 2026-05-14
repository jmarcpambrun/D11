<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when an Issue Hook with action=reopen is received.
 */
final class GitlabIssueReopenedEvent extends GitlabEventBase {}
