<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\ECA\Condition;

use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;

/**
 * Base condition with easy access to the current AI event.
 */
abstract class AiEventConditionBase extends ConditionBase {

  /**
   * Current AI event.
   */
  protected function aiEvent(): ?AiProviderRequestBaseEvent {
    $event = $this->getEvent();
    return $event instanceof AiProviderRequestBaseEvent ? $event : NULL;
  }

  /**
   * Current AI response-failed event.
   */
  protected function failedEvent(): ?AiExceptionEvent {
    $event = $this->getEvent();
    return $event instanceof AiExceptionEvent ? $event : NULL;
  }

}
