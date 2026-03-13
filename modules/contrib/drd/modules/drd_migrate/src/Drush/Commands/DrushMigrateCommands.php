<?php

namespace Drupal\drd_migrate\Drush\Commands;

use Drupal\drd_migrate\Import;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Drush commands for the DRD migrate module.
 *
 * @package Drupal\drd
 */
class DrushMigrateCommands extends DrushCommands {

  /**
   * The import service.
   *
   * @var \Drupal\drd_migrate\Import
   */
  protected Import $service;

  /**
   * Drush constructor.
   *
   * @param \Drupal\drd_migrate\Import $service
   *   The import service.
   */
  public function __construct(Import $service) {
    parent::__construct();
    $this->service = $service;
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\drd_migrate\Drush\Commands\DrushMigrateCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): DrushMigrateCommands {
    return new DrushMigrateCommands(
      $container->get('drd_migrate.import'),
    );
  }

  /**
   * Configure this domain for communication with a DRD instance.
   *
   * @param string $inventory
   *   Filename containing the json with you DRD 7 inventory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  #[Command(name: 'drd:migratefromd7', aliases: ['drd-migrate-from-d7'])]
  #[Argument(name: 'inventory', description: 'Filename containing the json with you DRD 7 inventory.')]
  #[Usage(name: 'drush drd:migratefromd7', description: 'Configure this domain for communication with a DRD instance.')]
  public function migrate(string $inventory): void {
    $this->service->execute($inventory);
  }

}
