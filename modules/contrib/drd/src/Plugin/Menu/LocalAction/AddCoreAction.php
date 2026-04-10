<?php

namespace Drupal\drd\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\drd\Entity\Host;

/**
 * Modifies the 'Add Core' local action to specify the host.
 */
class AddCoreAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = parent::getRouteParameters($route_match);
    // @phpstan-ignore-next-line
    $request = \Drupal::request();
    if (($host = $request->get('drd_host')) && $host instanceof Host) {
      // Attach query param to pre-fill the host widget.
      $parameters['drd_host'] = $host->id();
    }
    return $parameters;
  }

}
