<?php

namespace Drupal\group\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modifies the local action to add a destination.
 *
 * Will either append the already present destination parameter or use the
 * current route's path as the destination parameter.
 *
 * @todo Follow up on https://www.drupal.org/node/2762131.
 */
class WithDestination extends LocalActionDefault {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteProviderInterface $route_provider,
    private RedirectDestinationInterface $redirectDestination,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);
    $options['query']['destination'] = $this->redirectDestination->get();
    return $options;
  }

}
