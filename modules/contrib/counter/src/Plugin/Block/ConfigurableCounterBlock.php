<?php

namespace Drupal\counter\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\counter\CounterUtility;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'configurable counter' block.
 *
 * @Block(
 *   id = "configurable_counter_block",
 *   admin_label = @Translation("Configurable Counter"),
 * )
 */
class ConfigurableCounterBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    CounterUtility $counter_utility
  ) {
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
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
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

  public function defaultConfiguration(): array {
    return [
      'counter_show_site_counter' => TRUE,
      'counter_show_unique_visitor' => FALSE,
      'counter_registered_user' => FALSE,
      'counter_unregistered_user' => FALSE,
      'counter_blocked_user' => FALSE,
      'counter_published_node' => FALSE,
      'counter_unpublished_node' => FALSE,
      'counter_show_server_ip' => FALSE,
      'counter_show_ip' => FALSE,
      'counter_show_counter_since' => FALSE,
      'counter_initial_counter' => FALSE,
      'counter_initial_unique_visitor' => FALSE,
      'counter_initial_since' => FALSE,
      'counter_statistic_today' => FALSE,
      'counter_statistic_week' => FALSE,
      'counter_statistic_month' => FALSE,
      'counter_statistic_year' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $fields = [
      'counter_show_site_counter' => 'Show Site Counter',
      'counter_show_unique_visitor' => 'Show Unique Visitors',
      'counter_registered_user' => 'Show Registered Users',
      'counter_unregistered_user' => 'Show Unregistered Users',
      'counter_blocked_user' => 'Show Blocked Users',
      'counter_published_node' => 'Show Published Nodes',
      'counter_unpublished_node' => 'Show Unpublished Nodes',
      'counter_show_server_ip' => 'Show Server IP',
      'counter_show_ip' => 'Show IP',
      'counter_show_counter_since' => 'Show Counter Since',
      'counter_initial_counter' => 'Show Initial Counter',
      'counter_initial_unique_visitor' => 'Show Initial Unique Visitor',
      'counter_initial_since' => 'Show Initial Since',
      'counter_statistic_today' => 'Show Statistic Today',
      'counter_statistic_week' => 'Show Statistic Week',
      'counter_statistic_month' => 'Show Statistic Month',
      'counter_statistic_year' => 'Show Statistic Year',
    ];

    foreach ($fields as $key => $title) {
      $form[$key] = [
        '#type' => 'checkbox',
        '#title' => $this->t($title),
        '#default_value' => $config[$key] ?? FALSE,
      ];
    }

    return $form;
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
    $configBlock = $this->getConfiguration();

    $build = [
      '#attached' => [
        'library' => ['counter/counter.custom'],
      ],
      '#theme' => 'configure_counter',
    ];

    if (!empty($configBlock['counter_show_site_counter']) && $counter_show_site_counter) {
      $build['#site_counter'] = number_format($counter_initial_counter + $this->counterUtility->getVisitorData());
    }

    if (!empty($configBlock['counter_show_unique_visitor']) && $counter_show_unique_visitor) {
      $build['#unique_visitor'] = number_format($counter_initial_unique_visitor + $this->counterUtility->getUniqueVisitorData());
    }

    if (!empty($configBlock['counter_registered_user']) && $counter_registered_user) {
      $build['#registered_user'] = number_format($this->counterUtility->getTotalUsers());
    }

    if (!empty($configBlock['counter_unregistered_user']) && $counter_unregistered_user) {
      $build['#unregistered_user'] = number_format($this->counterUtility->getTotalUsers('='));
    }

    if (!empty($configBlock['counter_blocked_user']) && $counter_blocked_user) {
      $build['#blocked_user'] = number_format($this->counterUtility->getTotalUsers('=',
        0));
    }

    if (!empty($configBlock['counter_published_node']) && $counter_published_node) {
      $build['#published_nodes'] = number_format($this->counterUtility->getTotalNodes());
    }

    if (!empty($configBlock['counter_unpublished_node']) && $counter_unpublished_node) {
      $build['#unpublished_nodes'] = number_format($this->counterUtility->getTotalNodes(0));
    }

    if (!empty($configBlock['counter_show_server_ip']) && $counter_show_server_ip) {
      $build['#server_ip'] = $this->requestStack->getCurrentRequest()->server->get('SERVER_ADDR');
    }

    if (!empty($configBlock['counter_show_ip']) && $counter_show_ip) {
      $build['#ip'] = $this->requestStack->getCurrentRequest()
        ->getClientIp();
    }

    if (!empty($configBlock['counter_show_counter_since']) && $counter_show_counter_since) {
      $counter_since = $this->counterUtility->getCounterLastDate('>', 'ASC');

      if ($counter_initial_since != 0) {
        $counter_since = $counter_initial_since;
      }
      $build['#counter_since'] = date('d M Y', $counter_since);
    }

    if (!empty($configBlock['counter_statistic_today']) && $counter_statistic_today) {
      $build['#statistic_today'] = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d'))));
    }

    if (!empty($configBlock['counter_statistic_week']) && $counter_statistic_week) {
      $build['#statistic_week'] = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d')) - 7 * 24 * 60 * 60,
        time()));
    }

    if (!empty($configBlock['counter_statistic_month']) && $counter_statistic_month) {
      $build['#statistic_month'] = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d')) - 30 * 24 * 60 * 60,
        time()));
    }

    if (!empty($configBlock['counter_statistic_year']) && $counter_statistic_year) {
      $build['#statistic_year'] = number_format($this->counterUtility->getTimeRangeData(strtotime(date('Y-m-d')) - 365 * 24 * 60 * 60,
        time()));
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $fields = [
      'counter_show_site_counter',
      'counter_show_unique_visitor',
      'counter_registered_user',
      'counter_unregistered_user',
      'counter_blocked_user',
      'counter_published_node',
      'counter_unpublished_node',
      'counter_show_server_ip',
      'counter_show_ip',
      'counter_show_counter_since',
      'counter_initial_counter',
      'counter_initial_unique_visitor',
      'counter_initial_since',
      'counter_statistic_today',
      'counter_statistic_week',
      'counter_statistic_month',
      'counter_statistic_year',
    ];

    foreach ($fields as $field) {
      $this->configuration[$field] = $form_state->getValue($field);
    }

    parent::blockSubmit($form, $form_state);
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

  /**
   * {@inheritdoc}
   */

  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
