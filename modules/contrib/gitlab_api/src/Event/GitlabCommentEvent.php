<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when a `Note Hook` (comment) webhook is received.
 */
final class GitlabCommentEvent extends GitlabEventBase {}
