<?php

namespace Drupal\drd;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements ActionPermissions class.
 */
final class ActionPermissions implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * ActionPermissions constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ActionPermissions {
    return new ActionPermissions(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Provides a list of permissions.
   *
   * @return array
   *   The list of permissions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function permissions(): array {
    $actionStorage = $this->entityTypeManager->getStorage('action');
    $actions = array_filter($actionStorage->loadMultiple(),
      static function ($action) {
        /** @var \Drupal\system\ActionConfigEntityInterface $action */
        return in_array($action->getType(), [
          'drd',
          'drd_host',
          'drd_core',
          'drd_domain',
        ]);
      });

    $permissions = [];
    /** @var \Drupal\system\ActionConfigEntityInterface $action */
    foreach ($actions as $action) {
      /** @var \Drupal\drd\Plugin\Action\BaseInterface $drdAction */
      $drdAction = $action->getPlugin();
      $permissions[$drdAction->getPluginId()] = [
        'title' => t('Execute action @name', ['@name' => $action->getPlugin()->getPluginDefinition()['label']]),
        'restrict access' => $drdAction->restrictAccess(),
        'description' => '',
      ];
    }
    return $permissions;
  }

}
