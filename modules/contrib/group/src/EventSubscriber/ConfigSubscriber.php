<?php

namespace Drupal\group\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to configuration imports.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GroupRelationTypeManagerInterface $pluginManager,
  ) {
  }

  /**
   * Causes a group role synchronization after importing config.
   *
   * @param \Drupal\Core\Config\ConfigImporterEvent $event
   *   The configuration event.
   */
  public function onConfigImport(ConfigImporterEvent $event) {
    $this->pluginManager->installEnforced();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::IMPORT] = 'onConfigImport';
    return $events;
  }

}
