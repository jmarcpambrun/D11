<?php

namespace Drupal\counter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\counter\Service\StatisticsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for counter statistics page and data endpoint.
 */
class CounterStatistics extends ControllerBase {

  protected StatisticsService $statisticsService;

  public function __construct(StatisticsService $statistics_service) {
    $this->statisticsService = $statistics_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('counter.statistics_service'),
    );
  }

  /**
   * Render page via twig template.
   */
  public function statisticsPage(): array {
    $build = [];
    $build['#theme'] = 'counter_statistics';
    $build['#attached']['library'][] = 'counter/statistics';

    $build['#counter_menu'] = [
      [
        'title' => $this->t('Counter Dashboard', [], ['context' => 'counter']),
        'url' => '/admin/config/counter/dashboard',
        'active' => FALSE,
      ],
      [
        'title' => $this->t('Statistics views', [], ['context' => 'counter']),
        'url' => '/admin/config/counter/statistics',
        'active' => TRUE,
      ],
    ];

    $build['#attached']['drupalSettings']['counter'] = [
      'dataUrl' => '/admin/config/counter/statistics/data',
      'defaultRange' => '7days',
    ];
    return $build;
  }

  /**
   * Data endpoint (JSON).
   */
  public function statisticsData(Request $request): JsonResponse {
    $range = $request->query->get('range', '7days');
    $compare = $request->query->get('compare', 0) ? TRUE : FALSE;
    $start = $request->query->get('start');
    $end = $request->query->get('end');

    $result = $this->statisticsService->getStats(
      $range,
      $compare,
      $start,
      $end,
    );

    return new JsonResponse($result);
  }

}
