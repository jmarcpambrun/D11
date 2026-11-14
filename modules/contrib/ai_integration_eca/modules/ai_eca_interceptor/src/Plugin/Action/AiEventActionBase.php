<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Base class for AI event mutation actions.
 */
abstract class AiEventActionBase extends ConfigurableActionBase {

  /**
   * Returns the currently triggered AI request/response event, or NULL.
   */
  protected function aiEvent(): ?AiProviderRequestBaseEvent {
    $event = $this->getEvent();
    return $event instanceof AiProviderRequestBaseEvent ? $event : NULL;
  }

  /**
   * Returns the currently triggered pre-request event, or NULL.
   */
  protected function preEvent(): ?PreGenerateResponseEvent {
    $event = $this->getEvent();
    return $event instanceof PreGenerateResponseEvent ? $event : NULL;
  }

  /**
   * Returns the currently triggered response event, or NULL.
   */
  protected function responseEvent(): ?AiProviderResponseBaseEvent {
    $event = $this->getEvent();
    return $event instanceof AiProviderResponseBaseEvent ? $event : NULL;
  }

  /**
   * Returns the currently triggered exception (failover) event, or NULL.
   */
  protected function failedEvent(): ?AiExceptionEvent {
    $event = $this->getEvent();
    return $event instanceof AiExceptionEvent ? $event : NULL;
  }

}
