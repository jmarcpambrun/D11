<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\ConfigWrapperStorageInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;

/**
 * Entity hook implementations for Group.
 */
final class EntityHooks {

  public function __construct(
    protected GroupRelationTypeManagerInterface $groupRelationTypeManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {}

  /**
   * Implements hook_entity_bundle_info().
   */
  #[Hook('entity_bundle_info')]
  public function entityBundleInfo(): array {
    $plugin_manager = $this->groupRelationTypeManager;
    $installed_ids = array_unique(array_merge(...array_values($plugin_manager->getGroupTypePluginMap())));
    $installed_ids = array_combine($installed_ids, $installed_ids);

    $installed_config_entity_types = [];
    foreach (array_intersect_key($plugin_manager->getDefinitions(), $installed_ids) as $group_relation_type) {
      if ($group_relation_type->handlesConfigEntityType()) {
        $config_entity_type_id = $group_relation_type->getEntityTypeId();
        $installed_config_entity_types[$config_entity_type_id] = $config_entity_type_id;
      }
    }

    $config_entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->entityClassImplements(ConfigEntityInterface::class)) {
        $config_entity_types[$entity_type_id] = $entity_type->getLabel();
      }
    }

    $bundles = [];
    foreach (array_intersect_key($config_entity_types, $installed_config_entity_types) as $entity_type_id => $entity_type_label) {
      $bundles['group_config_wrapper'][$entity_type_id]['label'] = $entity_type_label;
    }

    return $bundles;
  }

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    // Some modules, including the code in \Drupal\node\NodeForm::access() may
    // check for 'view', 'update' or 'delete' access on new entities, even
    // though that makes little sense. We need to account for it to avoid
    // crashes because we would otherwise query the DB with a non-existent node
    // ID.
    if ($entity->isNew()) {
      return AccessResult::neutral();
    }

    // Find all of the group relations that define access.
    $plugin_manager = $this->groupRelationTypeManager;
    $plugin_ids = $plugin_manager->getPluginIdsByEntityTypeAccess($entity->getEntityTypeId());
    if (empty($plugin_ids)) {
      return AccessResult::neutral();
    }

    // If any new relationship entity is added using any of the retrieved
    // plugins, it might change access.
    $plugin_cache_tags = [];
    foreach ($plugin_ids as $plugin_id) {
      $plugin_cache_tags[] = "group_relationship_list:plugin:$plugin_id";
    }
    $access = AccessResult::neutral()->addCacheTags($plugin_cache_tags);

    // If the entity is config, we need to find the wrapper for it.
    if ($entity instanceof ConfigEntityInterface) {
      $wrapper_storage = $this->entityTypeManager->getStorage('group_config_wrapper');
      assert($wrapper_storage instanceof ConfigWrapperStorageInterface);
      $entity_id = $wrapper_storage->wrapEntity($entity)->id();
    }
    else {
      $entity_id = $entity->id();
    }

    // Reduce the plugin IDs to those that are actually in use.
    $data_table = $this->entityTypeManager
      ->getDefinition('group_relationship')
      ->getDataTable();

    $plugin_ids_used = $this->database
      ->select($data_table, 'd')
      ->fields('d', ['plugin_id'])
      ->condition('entity_id', $entity_id)
      ->condition('plugin_id', $plugin_ids, 'IN')
      ->distinct(TRUE)
      ->execute()
      ->fetchCol();

    // Loop over the plugin handlers and add their access check to the result.
    foreach ($plugin_ids_used as $plugin_id) {
      $handler = $plugin_manager->getAccessControlHandler($plugin_id);
      $access = $access->orIf($handler->entityAccess($entity, $operation, $account, TRUE));

      // No need to continue if access is denied.
      if ($access->isForbidden()) {
        break;
      }
    }

    return $access;
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    assert($storage instanceof GroupRelationshipStorageInterface);

    // See if any relationships exist for the entity and delete them as well.
    if ($relationships = $storage->loadByEntity($entity)) {
      $storage->delete($relationships);
    }

    // Now that we've deleted all of the relationships, we can also see if the
    // entity was wrapped and delete the wrapper if necessary.
    if ($entity instanceof ConfigEntityInterface) {
      $storage = $this->entityTypeManager->getStorage('group_config_wrapper');
      assert($storage instanceof ConfigWrapperStorageInterface);

      if ($storage->supportsEntity($entity) && $wrapper = $storage->wrapEntity($entity, FALSE)) {
        $storage->delete([$wrapper]);
      }
    }
  }

  /**
   * Implements hook_entity_field_access().
   *
   * @todo Move this to a form controller so we can hide the field if it has no
   *       options available to it?
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(
    string $operation,
    FieldDefinitionInterface $field_definition,
    AccountInterface $account,
    ?FieldItemListInterface $items = NULL,
  ): AccessResultInterface {
    // Can't retrieve an entity from an empty item list.
    if (!isset($items)) {
      return AccessResult::neutral();
    }

    // We only care about the group_roles field when on a form.
    if ($field_definition->getName() != 'group_roles' || $operation !== 'edit') {
      return AccessResult::neutral();
    }

    // We only care if it is attached to a relationship type entity.
    if ($items->getEntity()->getEntityTypeId() != 'group_relationship') {
      return AccessResult::neutral();
    }

    $group_relationship = $items->getEntity();
    assert($group_relationship instanceof GroupRelationshipInterface);

    // We only care if the relationship entity is a group membership.
    if ($group_relationship->getPluginId() != 'group_membership') {
      return AccessResult::neutral();
    }

    // Now that we know we're dealing with a group_roles field on a group
    // membership form, we need to check whether the group membership belongs to
    // a group yet. If not, we can't check for access and should always hide it.
    if (!$group = $group_relationship->getGroup()) {
      return AccessResult::forbidden();
    }

    // Only group administrators should be able to change the membership roles.
    return AccessResult::forbiddenIf(!$group->hasPermission('administer members', $account));
  }

}
