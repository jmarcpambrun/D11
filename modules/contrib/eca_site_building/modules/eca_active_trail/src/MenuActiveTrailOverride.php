<?php

namespace Drupal\eca_active_trail;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\eca_active_trail\Plugin\Action\ActiveTrail;

/**
 * Class MenuActiveTrailOverride
 *  Override getActiveLink() of original MenuActiveTrail Service to apply changes to the active trail.
 */
class MenuActiveTrailOverride extends MenuActiveTrail {

  /**
   * Original service object.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $originalService;

  /**
   * MenuActiveTrailOverride constructor.
   *
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $originalService
   *   The original service.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link plugin manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A route match object for finding the active link.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   */
  public function __construct(MenuActiveTrailInterface $originalService,
                              MenuLinkManagerInterface $menu_link_manager,
  RouteMatchInterface $route_match,
  CacheBackendInterface $cache,
  LockBackendInterface $lock) {
    parent::__construct($menu_link_manager, $route_match, $cache, $lock);
    $this->originalService = $originalService;
  }

  /**
   * Implements getActiveLink()
   *  Alter active trail, if there is a new active trail for the menu_name.
   *
   * @param string|null $menu_name
   * @param \Drupal\eca_active_trail\Plugin\Action\ActiveTrail|null $activeTrail
   */
  public function getActiveLink($menu_name = NULL, ?ActiveTrail $activeTrail = NULL) {

    // Is there a new active trail for the menu_name?
    if ($activeTrail !== NULL) {
      // Get the link ID of the new trail.
      if ($linkId = $activeTrail->getLinkId()) {
        try {
          $instance = $this->menuLinkManager->getInstance(['id' => $linkId]);
        }
        catch (PluginNotFoundException $exception) {
          \Drupal::logger('eca_active_trail')->error('Could not find the configured menu link to set active: @error', [
            '@error' => $exception->getMessage(),
          ]);

          if (!empty($menu_name)) {
            // Fall back to the default.
            return $this->originalService->getActiveLink($menu_name);
          }
          else {
            return FALSE;
          }
        }

        // Check that the instance is in the menu.
        if (!empty($menu_name) && $instance->getMenuName() == $menu_name) {
          $newActiveTrail = ['' => ''];
          // Get parents to create new active trail.
          if ($parents = $this->menuLinkManager->getParentIds($instance->getPluginId())) {
            $newActiveTrail = $parents + $newActiveTrail;
          }

          // Get current cache storage for $menuName.
          $menuStorage = $this->get($menu_name);
          // Is new active trail already cached?
          if ($menuStorage !== $newActiveTrail) {
            // Write new active trail to cache.
            $this->storage[$menu_name] = $newActiveTrail;
            $this->tags[] = 'config:system.menu.' . $menu_name;
            $this->persist($menu_name);
            // Rebuild menu.
            $this->menuLinkManager->rebuild();
          }

          return $instance;
        }
      }
    }

    // Fall back to the default.
    return $this->originalService->getActiveLink($menu_name);
  }

}
