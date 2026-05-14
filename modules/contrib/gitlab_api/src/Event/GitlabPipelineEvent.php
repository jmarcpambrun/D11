<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

/**
 * Dispatched when a `Pipeline Hook` webhook is received.
 */
final class GitlabPipelineEvent extends GitlabEventBase {}
