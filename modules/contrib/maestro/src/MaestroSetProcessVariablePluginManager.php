<?php

namespace Drupal\maestro;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages Maestro Set Process Variable Plugins.
 *
 * Please see maestro.services.yml file.
 */
class MaestroSetProcessVariablePluginManager extends DefaultPluginManager {

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
    $subdir = 'Plugin/MaestroSetProcessVariablePlugins';

    $plugin_interface = 'Drupal\maestro\MaestroSetProcessVariablePluginInterface';

    // The name of the annotation class that contains the plugin definition.
    $plugin_definition_annotation_name = 'Drupal\maestro\Annotation\MaestroSetProcessVariablePlugin';

    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name);

    $this->alterInfo('maestro_set_process_variable_plugin_info');

    $this->setCacheBackend($cache_backend, 'maestro_set_process_variable_plugin_info');
  }

}
