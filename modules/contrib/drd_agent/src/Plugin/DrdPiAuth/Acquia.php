<?php

namespace Drupal\drd_agent\Plugin\DrdPiAuth;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Acquia PI Auth plugin.
 *
 * @DrdPiAuth(
 *   id = "acquia"
 * )
 */
final class Acquia extends DrdPiAuthBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs an Acquia DRD PI auth object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Acquia {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequired(): array {
    return ['username', 'password'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getLocal(): array {
    return $this->getDbInfo();
  }

  /**
   * Get the database information.
   *
   * @return array
   *   The database connection details.
   */
  protected function getDbInfo(): array {
    return $this->database->getConnectionOptions();
  }

}
