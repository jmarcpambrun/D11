<?php

namespace Drupal\drd_eca\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for DRD events.
 */
class DrdEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return DrdEvent::definitions();
  }

}
