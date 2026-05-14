<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when an Issue Hook with action=close is received.
 */
final class GitlabIssueClosedEvent extends GitlabEventBase {}
