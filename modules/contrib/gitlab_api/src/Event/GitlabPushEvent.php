<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when a `Push Hook` webhook is received.
 */
final class GitlabPushEvent extends GitlabEventBase {}
