<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when a `Merge Request Hook` webhook is received.
 */
final class GitlabMrEvent extends GitlabEventBase {}
