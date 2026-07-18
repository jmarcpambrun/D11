<?php

namespace Drupal\burndown\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds custom access checks to My Tasks view display routes.
 */
class MyTasksRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach (['view.my_tasks.my_asssigned_tasks', 'view.my_tasks.my_watchlist'] as $route_name) {
      $route = $collection->get($route_name);
      if ($route) {
        $route->setRequirement('_custom_access', '\\Drupal\\burndown\\Controller\\TaskController::checkTaskViewAccess');
        $requirements = $route->getRequirements();
        unset($requirements['_permission']);
        $route->setRequirements($requirements);
      }
    }
  }

}
