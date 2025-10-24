<?php

namespace Drupal\drd\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for drd remote blocks.
 *
 * @see \Drupal\drd\Plugin\Block\Remote
 */
final class RemoteBlock extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * RemoteBlock constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): RemoteBlock {
    return new RemoteBlock(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $site_config = $this->configFactory->get('drd.general');
    foreach ($site_config->get('remote_blocks') as $module => $blocks) {
      foreach ($blocks as $delta => $label) {
        $id = implode(':', [$module, $delta]);
        $this->derivatives[$id] = $base_plugin_definition;
        $this->derivatives[$id]['admin_label'] = t('Remote block: @label', ['@label' => $label]);
      }
    }
    return $this->derivatives;
  }

}
