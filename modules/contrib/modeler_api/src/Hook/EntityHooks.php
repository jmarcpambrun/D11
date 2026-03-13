<?php

namespace Drupal\modeler_api\Hook;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\modeler_api\Entity\AccessControlHandler;
use Drupal\modeler_api\Entity\ListBuilder;
use Drupal\modeler_api\Form\DeleteForm;
use Drupal\modeler_api\ModelerApiPermissions;

/**
 * Hooks implementation for modeler_api.
 */
class EntityHooks {

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {

    $modelOwnerPluginManager = \Drupal::service('plugin.manager.modeler_api.model_owner');

    foreach ($modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {

      $basePath = $owner->configEntityBasePath();
      if ($basePath === NULL) {
        continue;
      }

      $type = $owner->configEntityTypeId();

      if (!isset($entity_types[$type])) {
        continue;
      }

      $entity_types[$type]
        ->setAccessClass(AccessControlHandler::class)
        ->setListBuilderClass(ListBuilder::class)
        ->setLinkTemplate('collection', '/' . $basePath)
        ->setFormClass('delete', DeleteForm::class)
        ->setLinkTemplate('edit-form', '/' . $basePath . '/{' . $type . '}/edit')
        ->setLinkTemplate('delete-form', '/' . $basePath . '/{' . $type . '}/delete');
    }

  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {

    $operations = [];

    if (!$entity instanceof ConfigEntityInterface) {
      return $operations;
    }

    $modelerApi = \Drupal::service('modeler_api.service');
    $modelerManager = \Drupal::service('plugin.manager.modeler_api.modeler');
    $currentUser = \Drupal::currentUser();

    $modelers = $modelerManager->getAllInstances();

    if (count($modelers) === 1) {
      return $operations;
    }

    $owner = $modelerApi->findOwner($entity);

    if (!$owner || !$owner->configEntityBasePath()) {
      return $operations;
    }

    $type = $entity->getEntityTypeId();
    $modelerId = $owner->getModelerId($entity);

    foreach ($modelers as $id => $modeler) {

      if ($modelerId !== $id && $modeler->isEditable()) {

        if ($currentUser->hasPermission(
          ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $id)
        )) {

          $operations['open_with_' . $id] = [
            'title' => t('Edit with :label', [':label' => $modeler->label()]),
            'url' => Url::fromRoute('entity.' . $type . '.edit_with.' . $id, [$type => $entity->id()]),
            'weight' => 40,
          ];

        }

      }

    }

    return $operations;

  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {

    if ($is_syncing) {
      return;
    }

    $modelOwnerPluginManager = \Drupal::service('plugin.manager.modeler_api.model_owner');
    $entityTypeManager = \Drupal::entityTypeManager();

    foreach ($modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {

      $provider = $owner->getPluginDefinition()['provider'];

      if (in_array($provider, $modules)) {

        $entityTypeManager->clearCachedDefinitions();
        return;

      }

    }

  }

}