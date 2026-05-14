<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when an Issue Hook with action=open is received.
 */
final class GitlabIssueCreatedEvent extends GitlabEventBase {}
