<?php

namespace Drupal\drd\Plugin\Auth;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\drd\Annotation\Auth;

/**
 * Provides the DRD Auth plugin manager.
 */
class Manager extends DefaultPluginManager {

  /**
   * Constructor for DrdAuthManager objects.
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
    parent::__construct('Plugin/Auth', $namespaces, $module_handler, BaseInterface::class, Auth::class);

    $this->alterInfo('drd_drd_auth_info');
    $this->setCacheBackend($cache_backend, 'drd_drd_auth_plugins');
  }

  /**
   * Callback to provide an select list of available plugins for the form API.
   *
   * @return array
   *   Associated array of available authentication methods.
   */
  public function selectList(): array {
    $list = [];

    foreach ($this->getDefinitions() as $def) {
      $list[$def['id']] = $def['label'];
    }

    return $list;
  }

}
