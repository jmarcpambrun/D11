<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for AI ECA Interceptor event plugins.
 *
 * The definitions live on the event plugin because
 * \Drupal\eca\Plugin\ECA\Event\EventInterface declares
 * `public static function definitions(): array` as part of the event plugin's
 * own contract. Every ECA core event plugin therefore owns its definitions and
 * has its deriver delegate to them, which is exactly what happens here.
 */
class InterceptorEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return InterceptorEvent::definitions();
  }

}
