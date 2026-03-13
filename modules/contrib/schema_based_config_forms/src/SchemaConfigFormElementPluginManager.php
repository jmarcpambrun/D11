<?php

namespace Drupal\schema_based_config_forms;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use function array_key_exists;
use function in_array;

/**
 * SchemaConfigFormElement plugin manager.
 */
final class SchemaConfigFormElementPluginManager extends DefaultPluginManager {

  /**
   * Already instantiated schema config form element plugins.
   */
  protected array $instances = [];

  /**
   * Constructs a Schema Config Form ElementP plugin manager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/SchemaConfigFormElement',
      $namespaces,
      $module_handler,
      'Drupal\schema_based_config_forms\SchemaConfigFormElementInterface',
      'Drupal\schema_based_config_forms\Annotation\SchemaConfigFormElement'
    );
    $this->alterInfo('schema_config_form_element_info');
    $this->setCacheBackend($cache_backend, 'schema_config_form_element_plugins');
  }

  /**
   * Get a schema config form element plugin by its ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getInstanceByPluginId(string $plugin_id): SchemaConfigFormElementInterface {
    if (!array_key_exists($plugin_id, $this->instances)) {
      $this->instances[$plugin_id] = $this->createInstance($plugin_id);
    }
    return $this->instances[$plugin_id];
  }

  /**
   * Get a schema config form element plugin by schema type.
   */
  public function getInstanceBySchemaType(string $type): ?SchemaConfigFormElementInterface {
    if ($plugin_id = $this->getPluginIdForSchemaType($type)) {
      return $this->getInstanceByPluginId($plugin_id);
    }

    return NULL;
  }

  /**
   * Get the plugin ID for a given config schema data type.
   */
  protected function getPluginIdForSchemaType(string $schema_type): ?string {
    if (!$schema_type) {
      throw new \InvalidArgumentException('$schema_type must be provided.');
    }

    $definitions = $this->getDefinitions();
    foreach ($definitions as $plugin_id => $plugin_definition) {
      if (in_array($schema_type, $plugin_definition['types'], TRUE)) {
        return $plugin_id;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, function ($a, $b) {
      return $a['weight'] ?? 0 <=> $b['weight'] ?? 0;
    });
    return $definitions;
  }

}
