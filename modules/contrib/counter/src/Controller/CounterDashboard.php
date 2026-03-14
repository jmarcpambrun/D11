<?php

namespace Drupal\counter\Controller;

use Drupal\counter\CounterUtility;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for the dashboard.
 *
 * @package Drupal\counter\Controller
 */
class CounterDashboard extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The counter utility variable for accessing helper functions.
   *
   * @var Drupal\counter\CounterUtility
   */
  protected $counterUtility;

  /**
   * Constructor for the counter dashboard controller.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory for the form.
   * @param \Drupal\counter\CounterUtility $counter_utility
   *   The counter utility variable to access helper functions.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    CounterUtility $counter_utility,
  ) {
    $this->configFactory = $config_factory;
    $this->counterUtility = $counter_utility;
  }

  /**
   * Create function to assemble the counter dashboard.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('counter.counter_utility'),
    );
  }

  /**
   * The Dashboard page where counter statistics are displayed to the user.
   */
  public function page() {
    $config = $this->configFactory->get('counter.settings');
    $settings = $config->getRawData();

    $counter_initial_counter = $config->get('counter_initial_counter');
    $counter_initial_unique_visitor = $config->get('counter_initial_unique_visitor');
    $counter_initial_since = $config->get('counter_initial_since');

    if (!empty($settings['counter_show_site_counter'])) {
      $site_counter = number_format(
        $counter_initial_counter + $this->counterUtility->getVisitorData(),
      );
    }

    if (!empty($settings['counter_show_unique_visitor'])) {
      $unique_visitor = number_format(
        $counter_initial_unique_visitor + $this->counterUtility->getUniqueVisitorData(
        ),
      );
    }

    if (!empty($settings['counter_registered_user'])) {
      $registered_user = number_format($this->counterUtility->getTotalUsers());
    }

    if (!empty($settings['counter_unregistered_user'])) {
      $unregistered_user = number_format(
        $this->counterUtility->getTotalUsers('='),
      );
    }
    if (!empty($settings['counter_blocked_user'])) {
      $blocked_user = number_format(
        $this->counterUtility->getTotalUsers('=', 0),
      );
    }

    if (!empty($settings['counter_published_node'])) {
      $published_nodes = number_format($this->counterUtility->getTotalNodes());
    }

    if (!empty($settings['counter_unpublished_node'])) {
      $unpublished_nodes = number_format(
        $this->counterUtility->getTotalNodes(0),
      );
    }

    if (!empty($settings['counter_show_counter_since'])) {
      $counter_since = $this->counterUtility->getCounterLastDate('>', 'ASC');
      if ($counter_initial_since != 0) {
        $counter_since = $counter_initial_since;
      }
      $counter_since = date('d M Y', $counter_since);
    }

    if (!empty($settings['counter_statistic_today'])) {
      $statistic_today = number_format(
        $this->counterUtility->getTimeRangeData(strtotime('today')),
      );
    }

    if (!empty($settings['counter_statistic_week'])) {
      $statistic_week = number_format(
        $this->counterUtility->getTimeRangeData(strtotime('-7 days')),
      );
    }

    if (!empty($settings['counter_statistic_month'])) {
      $statistic_month = number_format(
        $this->counterUtility->getTimeRangeData(strtotime('-30 days')),
      );
    }

    if (!empty($settings['counter_statistic_year'])) {
      $statistic_year = number_format(
        $this->counterUtility->getTimeRangeData(strtotime('-1 year')),
      );
    }

    $unique_visitor_today = number_format(
      $this->counterUtility->getUniqueVisitorTimeRangeData(strtotime('today')),
    );
    $unique_visitor_week = number_format(
      $this->counterUtility->getUniqueVisitorTimeRangeData(strtotime('-7 days')),
    );
    $unique_visitor_month = number_format(
      $this->counterUtility->getUniqueVisitorTimeRangeData(
        strtotime('-30 days'),
      ),
    );
    $unique_visitor_year = number_format(
      $this->counterUtility->getUniqueVisitorTimeRangeData(strtotime('-1 year')),
    );

    $top_nodes = $this->counterUtility->getTopNodes();
    $top_urls = $this->counterUtility->getTopUrls();

    return [
      '#theme' => 'counter_dashboard',
      '#attached' => [
        'library' => ['counter/counter.dashboard'],
      ],
      '#site_counter' => $site_counter ?? NULL,
      '#unique_visitor' => $unique_visitor ?? NULL,
      '#registered_user' => $registered_user ?? NULL,
      '#unregistered_user' => $unregistered_user ?? NULL,
      '#blocked_user' => $blocked_user ?? NULL,
      '#published_nodes' => $published_nodes ?? NULL,
      '#unpublished_nodes' => $unpublished_nodes ?? NULL,
      '#counter_since' => $counter_since ?? NULL,
      '#statistic_today' => $statistic_today ?? NULL,
      '#statistic_week' => $statistic_week ?? NULL,
      '#statistic_month' => $statistic_month ?? NULL,
      '#statistic_year' => $statistic_year ?? NULL,
      '#unique_visitor_today' => $unique_visitor_today,
      '#unique_visitor_week' => $unique_visitor_week,
      '#unique_visitor_month' => $unique_visitor_month,
      '#unique_visitor_year' => $unique_visitor_year,
      '#top_nodes' => $top_nodes,
      '#top_urls' => $top_urls,
    ];
  }

}
