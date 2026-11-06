<?php

declare(strict_types=1);

namespace Drupal\ai_search\EventSubscriber;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigRenameEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears function call definitions when index configuration changes.
 *
 * The filterable RAG tool derives one function call per AI Search index,
 * with its filterable attributes, from config that drupal/ai caches.
 *
 * @see \Drupal\ai_search\Plugin\AiFunctionCall\Derivative\FilterableRagToolDeriver
 */
class FunctionCallDefinitionsEventSubscriber implements EventSubscriberInterface {

  /**
   * Config name prefixes the function call definitions depend on.
   */
  protected const CONFIG_PREFIXES = ['ai_search.index.', 'search_api.index.'];

  /**
   * Constructs a FunctionCallDefinitionsEventSubscriber.
   *
   * @param \Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface $functionCallManager
   *   The function call plugin manager.
   */
  public function __construct(
    protected CachedDiscoveryInterface $functionCallManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigChange',
      ConfigEvents::DELETE => 'onConfigChange',
      ConfigEvents::RENAME => 'onConfigChange',
    ];
  }

  /**
   * Clears the function call definitions for relevant config changes.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config event.
   */
  public function onConfigChange(ConfigCrudEvent $event): void {
    $names = [$event->getConfig()->getName()];
    if ($event instanceof ConfigRenameEvent) {
      $names[] = $event->getOldName();
    }
    foreach ($names as $name) {
      foreach (self::CONFIG_PREFIXES as $prefix) {
        if (str_starts_with($name, $prefix)) {
          $this->functionCallManager->clearCachedDefinitions();
          return;
        }
      }
    }
  }

}
