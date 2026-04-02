<?php

namespace Drupal\modeler_api\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Entity\AccessControlHandler;
use Drupal\modeler_api\Entity\ListBuilder;
use Drupal\modeler_api\Form\DeleteForm;
use Drupal\modeler_api\ModelerApiPermissions;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modeler API entity hooks (Drupal 11 safe version).
 */
class EntityHooks {

  public function __construct(
    protected Api $modelerApiService,
    protected ModelerPluginManager $modelerManager,
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('modeler_api.api'),
      $container->get('plugin.manager.modeler_api.modeler'),
      $container->get('plugin.manager.modeler_api.model_owner'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {

    $modelOwnerPluginManager = $this->modelOwnerPluginManager;

    static $alreadyRunning = FALSE;
    if ($alreadyRunning) {
      return;
    }
    $alreadyRunning = TRUE;

    foreach ($modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {

      $basePath = $owner->configEntityBasePath();
      if ($basePath === NULL) {
        continue;
      }

      $type = $owner->configEntityTypeId();

      if (isset($entity_types[$type])) {
        $entity_types[$type]
          ->setAccessClass(AccessControlHandler::class)
          ->setListBuilderClass(ListBuilder::class)
          ->setLinkTemplate('collection', '/' . $basePath)
          ->setFormClass('delete', DeleteForm::class)
          ->setLinkTemplate('edit-form', '/' . $basePath . '/{' . $type . '}/edit')
          ->setLinkTemplate('delete-form', '/' . $basePath . '/{' . $type . '}/delete');
      }
    }

    $alreadyRunning = FALSE;
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {

    $operations = [];

    $modelers = $this->modelerManager->getAllInstances();
    $numberOfModelers = count($modelers);

    if ($numberOfModelers <= 1) {
      return [];
    }

    if (
      $entity instanceof \Drupal\Core\Config\Entity\ConfigEntityInterface &&
      ($owner = $this->modelerApiService->findOwner($entity)) &&
      $owner->configEntityBasePath()
    ) {

      $type = $entity->getEntityTypeId();
      $modelerId = $owner->getModelerId($entity);

      foreach ($modelers as $id => $modeler) {

        if (
          $modelerId !== $id &&
          $modeler->isEditable() &&
          $this->currentUser->hasPermission(
            ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $id)
          )
        ) {
          $operations['open_with_' . $id] = [
            'title' => t('Edit with :label', [':label' => $modeler->label()]),
            'url' => Url::fromRoute('entity.' . $type . '.edit_with.' . $id, [$type => $entity->id()]),
            'weight' => 40,
          ];
        }

        if (
          $id !== 'fallback' &&
          $modelerId !== $id &&
          $this->currentUser->hasPermission(
            ModelerApiPermissions::getPermissionKey('view', $owner->getPluginId(), $id)
          )
        ) {
          $operations['view_with_' . $id] = [
            'title' => t('View with :label', [':label' => $modeler->label()]),
            'url' => Url::fromRoute('entity.' . $type . '.view_with.' . $id, [$type => $entity->id()]),
            'weight' => 45,
          ];
        }
      }

      $route = 'entity.' . $type . '.canonical';

      if (
        $this->modelerApiService->getRouteByName($route) &&
        $this->currentUser->hasPermission(
          ModelerApiPermissions::getPermissionKey('view', $owner->getPluginId())
        )
      ) {
        $operations['view'] = [
          'title' => t('View'),
          'url' => Url::fromRoute($route, [$type => $entity->id()]),
          'weight' => 44,
        ];
      }

      if ($owner->supportsStatus()) {
        $canEdit = $this->currentUser->hasPermission(
          ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $modelerId)
        );

        if ($canEdit) {
          if (!$entity->status()) {
            $operations['enable'] = [
              'title' => t('Enable'),
              'url' => Url::fromRoute('entity.' . $type . '.enable', [$type => $entity->id()]),
              'weight' => 50,
            ];
          }
          else {
            $operations['disable'] = [
              'title' => t('Disable'),
              'url' => Url::fromRoute('entity.' . $type . '.disable', [$type => $entity->id()]),
              'weight' => 51,
            ];
          }
        }
      }

      $cloneRoute = 'entity.' . $type . '.clone';

      if (
        $this->modelerApiService->getRouteByName($cloneRoute) &&
        $this->ownerCanEdit($owner, $entity, $modelerId)
      ) {
        $operations['clone'] = [
          'title' => t('Clone'),
          'url' => Url::fromRoute($cloneRoute, [$type => $entity->id()]),
          'weight' => 52,
        ];
      }
    }

    return $operations;
  }

  private function ownerCanEdit($owner, EntityInterface $entity, string $modelerId): bool {
    return $owner->isEditable($entity) &&
      $this->currentUser->hasPermission(
        ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $modelerId)
      );
  }

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {

    if (!isset($operations['edit'])) {
      return;
    }

    $modelers = $this->modelerManager->getAllInstances();

    if (count($modelers) <= 2) {
      return;
    }

    if (
      $entity instanceof \Drupal\Core\Config\Entity\ConfigEntityInterface &&
      ($owner = $this->modelerApiService->findOwner($entity)) &&
      $owner->configEntityBasePath()
    ) {
      $modeler = $owner->getModeler($entity);

      if ($modeler === NULL || $modeler->getPluginId() === 'fallback') {
        unset($operations['edit']);
      }
    }
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {

    if ($is_syncing) {
      return;
    }

    foreach ($this->modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {

      $provider = $owner->getPluginDefinition()['provider'] ?? NULL;

      if ($provider && in_array($provider, $modules, TRUE)) {
        $this->entityTypeManager->clearCachedDefinitions();
        return;
      }
    }
  }

}
