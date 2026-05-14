<?php

declare(strict_types=1);

namespace Drupal\eca_gitlab_api\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for eca_gitlab_api ECA event plugins.
 */
class GitlabIntegrationEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return GitlabIntegrationEvent::definitions();
  }

}
