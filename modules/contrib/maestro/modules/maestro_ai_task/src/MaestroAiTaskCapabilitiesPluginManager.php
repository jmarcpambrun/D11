<?php

namespace Drupal\maestro_ai_task;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin Manager for the Maestro AI Task plugins.
 *
 * Please see maestro_ai_task.services.yml file.
 */
class MaestroAiTaskCapabilitiesPluginManager extends DefaultPluginManager {
  /**
   * Creates the discovery object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $subdir = 'Plugin/MaestroAiTaskCapabilities';
    $plugin_interface = 'Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface';

    // We have defined our own Annotation to support an extended list of annotations that define our plugin.
    $plugin_definition_annotation_name = 'Drupal\maestro_ai_task\Annotation\MaestroAiTaskCapabilities';

    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name);

    $this->alterInfo('maestro_ai_task_capabilities_info');

    $this->setCacheBackend($cache_backend, 'maestro_ai_task_capabilities_info');
  }

}
