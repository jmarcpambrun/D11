<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when a `Tag Push Hook` webhook is received.
 */
final class GitlabTagPushEvent extends GitlabEventBase {}
