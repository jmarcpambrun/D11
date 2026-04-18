<?php

namespace Drupal\book\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears cache tag when Book settings is saved.
 */
class BookSettingsSaveEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {}

  /**
   * Acts on changes to book settings to cache tag.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() === 'book.settings') {
      // Now that the block is cached it needs to be invalidated.
      Cache::invalidateTags(['book_settings']);

      // Clear bundle info cache when allowed_types changes so entity classes
      // are updated for the changed bundles.
      // @see \Drupal\book\Hook\BookHooks::entityBundleInfoAlter()
      if ($event->isChanged('allowed_types')) {
        $this->entityTypeBundleInfo->clearCachedBundles();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onConfigSave'];
  }

}
