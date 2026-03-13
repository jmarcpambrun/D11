<?php

namespace Drupal\counter\Plugin\Block;

use Drupal\counter\CounterUtility;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'counter day' block.
 *
 * @Block(
 *   id = "counter_day_block",
 *   admin_label = @Translation("Counter Day"),
 * )
 */
class CounterDayBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The counter utility service.
   *
   * @var \Drupal\counter\CounterUtility
   */
  protected $counterUtility;

  /**
   * Constructs a CounterDayBlock.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CounterUtility $counter_utility
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->counterUtility = $counter_utility;
  }

  /**
   * {@inheritdoc}
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
      $container->get('counter.counter_utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $count = $this->counterUtility->getTimeRangeData(strtotime('today'));
    $count = $count > 999 ? number_format($count / 1000,
        1,
      ) . t('k', [], ['context' => 'counter']) : number_format($count);
    if (!in_array('administrator', \Drupal::currentUser()->getRoles(), TRUE)) {
      return [];
    }
    return [
      '#attached' => ['library' => ['counter/counter.custom']],
      '#markup' => '<a href="/admin/config/counter/dashboard" class="counter-day__link"><span>' . $count . '</span></a>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['counter_data_refresh']);
  }

}
