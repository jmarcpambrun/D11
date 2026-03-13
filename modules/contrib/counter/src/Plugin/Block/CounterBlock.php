<?php

namespace Drupal\counter\Plugin\Block;

use Drupal\counter\CounterUtility;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'counter' block.
 *
 * @Block(
 *   id = "counter_block",
 *   admin_label = @Translation("Counter"),
 * )
 */
class CounterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The counter utility variable for accessing helper functions.
   *
   * @var Drupal\counter\CounterUtility
   */
  protected $counterUtility;

  /**
   * Constructor for the counter block.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID of the block.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory for the form.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack for accessing request information.
   * @param \Drupal\counter\CounterUtility $counter_utility
   *   The counter utility variable to access helper functions.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, RequestStack $request_stack, CounterUtility $counter_utility) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->counterUtility = $counter_utility;
  }

  /**
   * Create function to assemble the counter block.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   * @param array $configuration
   *   The block plugin configuration.
   * @param string $plugin_id
   *   The plugin ID of the block.
   * @param mixed $plugin_definition
   *   Definition of the plugin block.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('counter.counter_utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->configFactory->get('counter.settings');
    $counter_show_site_counter = $config->get('counter_show_site_counter');
    $counter_show_unique_visitor = $config->get('counter_show_unique_visitor');
    $counter_registered_user = $config->get('counter_registered_user');
    $counter_unregistered_user = $config->get('counter_unregistered_user');
    $counter_blocked_user = $config->get('counter_blocked_user');
    $counter_published_node = $config->get('counter_published_node');
    $counter_unpublished_node = $config->get('counter_unpublished_node');
    $counter_show_server_ip = $config->get('counter_show_server_ip');
    $counter_show_ip = $config->get('counter_show_ip');
    $counter_show_counter_since = $config->get('counter_show_counter_since');
    $counter_initial_counter = $config->get('counter_initial_counter');
    $counter_initial_unique_visitor = $config->get('counter_initial_unique_visitor');
    $counter_initial_since = $config->get('counter_initial_since');
    $counter_statistic_today = $config->get('counter_statistic_today');
    $counter_statistic_week = $config->get('counter_statistic_week');
    $counter_statistic_month = $config->get('counter_statistic_month');
    $counter_statistic_year = $config->get('counter_statistic_year');

    if ($counter_show_site_counter) {
      $site_counter = number_format($counter_initial_counter + $this->counterUtility->getVisitorData());
    }

    if ($counter_show_unique_visitor) {
      $unique_visitor = number_format($counter_initial_unique_visitor + $this->counterUtility->getUniqueVisitorData());
    }

    if ($counter_registered_user) {
      $registered_user = number_format($this->counterUtility->getTotalUsers());
    }

    if ($counter_unregistered_user) {
      $unregistered_user = number_format($this->counterUtility->getTotalUsers('='));
    }

    if ($counter_blocked_user) {
      $blocked_user = number_format($this->counterUtility->getTotalUsers('=', 0));
    }

    if ($counter_published_node) {
      $published_nodes = number_format($this->counterUtility->getTotalNodes());
    }

    if ($counter_unpublished_node) {
      $unpublished_nodes = number_format($this->counterUtility->getTotalNodes(0));
    }

    if ($counter_show_server_ip) {
      $server_ip = $this->requestStack->getCurrentRequest()->server->get('SERVER_ADDR');
    }

    if ($counter_show_ip) {
      $ip = $this->requestStack->getCurrentRequest()->getClientIp();
    }

    if ($counter_show_counter_since) {
      $counter_since = $this->counterUtility->getCounterLastDate('>', 'ASC');

      if ($counter_initial_since != 0) {
        $counter_since = $counter_initial_since;
      }
      $counter_since = date('d M Y', $counter_since);
    }

    if ($counter_statistic_today) {
      $statistic_today = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d'))));
    }
    if ($counter_statistic_week) {
      $statistic_week = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d')) - 7 * 24 * 60 * 60, time()));
    }
    if ($counter_statistic_month) {
      $statistic_month = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d')) - 30 * 24 * 60 * 60, time()));
    }
    if ($counter_statistic_year) {
      $statistic_year = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d')) - 365 * 24 * 60 * 60, time()));
    }

    return [
      '#attached' => [
        'library' => ['counter/counter.custom'],
      ],
      '#theme' => 'counter',
      '#site_counter' => $site_counter,
      '#unique_visitor' => $unique_visitor,
      '#registered_user' => $registered_user,
      '#unregistered_user' => $unregistered_user,
      '#blocked_user' => $blocked_user,
      '#published_nodes' => $published_nodes,
      '#unpublished_nodes' => $unpublished_nodes,
      '#server_ip' => $server_ip,
      '#ip' => $ip,
      '#counter_since' => $counter_since,
      '#statistic_today' => $statistic_today,
      '#statistic_week' => $statistic_week,
      '#statistic_month' => $statistic_month,
      '#statistic_year' => $statistic_year,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Set cache tag for counter block.
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:counter.settings',
      'counter_data_refresh',
    ]);
  }

}
