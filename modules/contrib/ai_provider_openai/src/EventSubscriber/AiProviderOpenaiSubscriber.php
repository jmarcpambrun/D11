<?php

declare(strict_types=1);

namespace Drupal\ai_provider_openai\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Amends settings for the o1 models if in use.
 */
final class AiProviderOpenaiSubscriber implements EventSubscriberInterface {

  /**
   * Kernel request event handler.
   */
  public function updateConfig(PreGenerateResponseEvent $event): void {
    if ($event->getProviderId() == 'openai') {
      if (str_starts_with($event->getModelId(), 'o1')) {
        $config = $event->getConfiguration();

        if (array_key_exists('max_tokens', $config)) {
          $config['max_completion_tokens'] = $config['max_tokens'];
          unset($config['max_tokens']);
        }

        $event->setConfiguration($config);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => ['updateConfig'],
    ];
  }

}