<?php

namespace Drupal\drd\Plugin\Auth;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drd\Encryption;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for DRD Auth plugins.
 */
abstract class Base extends PluginBase implements BaseInterface, ContainerFactoryPluginInterface {

  /**
   * The encryption service.
   *
   * @var \Drupal\drd\Encryption
   */
  protected Encryption $encryption;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, Encryption $encryption) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->encryption = $encryption;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Base {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('drd.encrypt')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function storeSettingRemotely(): bool {
    return TRUE;
  }

}
