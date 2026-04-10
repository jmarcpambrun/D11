<?php

namespace Drupal\drd\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\drd\Widgets;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for DRD Info routes.
 */
final class Dashboard extends ControllerBase {

  /**
   * The widgets service.
   *
   * @var \Drupal\drd\Widgets
   */
  protected Widgets $widgets;

  /**
   * Dashboard constructor.
   *
   * @param \Drupal\drd\Widgets $widgets
   *   The widgets service.
   */
  public function __construct(Widgets $widgets) {
    $this->widgets = $widgets;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Dashboard {
    return new Dashboard(
      $container->get('drd.widgets')
    );
  }

  /**
   * Displays DRD status report.
   *
   * @return array
   *   A render array.
   */
  public function status(): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['drd-dashboard'],
      ],
    ] + $this->widgets->findWidgets(TRUE);
  }

}
