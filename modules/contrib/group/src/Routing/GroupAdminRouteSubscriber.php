<?php

namespace Drupal\group\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Sets the _admin_route for specific group-related routes.
 */
class GroupAdminRouteSubscriber extends RouteSubscriberBase {

  public function __construct(protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($this->configFactory->get('group.settings')->get('use_admin_theme')) {
      foreach ($collection->all() as $route) {
        if ($route->hasOption('_group_operation_route')) {
          $route->setOption('_admin_route', TRUE);
        }
      }
    }
  }

}
