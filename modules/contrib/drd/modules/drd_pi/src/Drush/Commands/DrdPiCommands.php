<?php

namespace Drupal\drd_pi\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\drd\ActionManagerInterface;
use Drupal\drd\Drush\Commands\DrdCommandsTrait;
use Drupal\drd\Logging;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for DRD Pi commands.
 *
 * @package Drupal\drd_pi
 */
class DrdPiCommands extends DrushCommands {

  use DrdCommandsTrait;

  /**
   * Constructor for Drush commands.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\drd\Logging $logging
   *   The logging channel.
   * @param \Drupal\drd\ActionManagerInterface $actionManager
   *   The DRD action manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Logging $logging, ActionManagerInterface $actionManager) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->logging = $logging;
    if (Drush::verbose()) {
      $this->logging->enforceDebug();
    }
    $this->actionManager = $actionManager;
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\drd_pi\Drush\Commands\DrdPiCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): DrdPiCommands {
    return new DrdPiCommands(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('drd.logging'),
      $container->get('plugin.manager.drd_action'),
    );
  }

  /**
   * Sync DRD inventory with all configured platforms.
   */
  #[Command(name: 'drd:pi:sync', aliases: ['drd-pi-sync'])]
  #[Usage(name: 'drush drd:pi:sync', description: 'Sync DRD inventory with all configured platforms.')]
  public function sync(): void {
    $this->actionKey = 'drd_action_pi_sync';
    $this
      ->prepare()
      ->execute();
  }

}
