<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when an Issue Hook with action=update is received.
 */
final class GitlabIssueUpdatedEvent extends GitlabEventBase {}
