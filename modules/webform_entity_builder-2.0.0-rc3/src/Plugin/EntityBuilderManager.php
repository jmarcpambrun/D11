<?php

namespace Drupal\webform_entity_builder\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Entity builder plugin manager.
 */
class EntityBuilderManager extends DefaultPluginManager {

  use LoggerChannelTrait;

  /**
   * Constructs a new EntityBuilderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/EntityBuilder', $namespaces, $module_handler, 'Drupal\webform_entity_builder\Plugin\EntityBuilderInterface', 'Drupal\webform_entity_builder\Annotation\EntityBuilder');

    $this->alterInfo('webform_entity_builder_entity_builder_info');
    $this->setCacheBackend($cache_backend, 'webform_entity_builder_entity_builder_plugins');
  }

  /**
   * Get a matching builder instance, if there is one.
   *
   * @param string $entity_type (e.g. "node:article")
   *
   * @param array $configuration
   *
   * @return null | EntityBuilderInterface
   *
   * @throws PluginException
   */
  public function fetchBuilder($entity_type, array $configuration = []): ?EntityBuilderInterface {
    /** @var EntityBuilderInterface $entityBuilder */
    $entityBuilder = NULL;

    foreach ($this->getDefinitions() as $plugin_name => $definition) {
      if ($this->matches($entity_type, $definition['type'])) {
        $entityBuilder = $this->createInstance($plugin_name, $configuration);
        break;
      }
    }

    return $entityBuilder;
  }

    /**
     * Compare the incoming entity type with the plugin definition entity type.
     *
     * @param string $entity_type
     * @param string $defn_type
     * @return bool
     */
  protected function matches($entity_type, $defn_type): bool
  {
    // Extract the parts first...
    [$dType, $dBundle, ] = explode(':', $defn_type . ':');
    $dBundle = $dBundle ?: '*';

    [$eType, $eBundle, ] = explode(':', $entity_type . ':');
    $eBundle = $eBundle ?: '*';

    if ($dType === '*' || $dType === $eType) {
        return $dBundle === '*' || $dBundle === $eBundle;
    }
    return false;
  }
}
