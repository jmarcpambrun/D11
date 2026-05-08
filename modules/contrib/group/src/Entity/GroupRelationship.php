<?php

namespace Drupal\group\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\Access\GroupRelationshipAccessControlHandler;
use Drupal\group\Entity\Controller\GroupRelationshipListBuilder;
use Drupal\group\Entity\Form\GroupRelationshipDeleteForm;
use Drupal\group\Entity\Form\GroupRelationshipForm;
use Drupal\group\Entity\Routing\GroupRelationshipRouteProvider;
use Drupal\group\Entity\Storage\GroupRelationshipStorage;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageSchema;
use Drupal\group\Entity\Storage\GroupRoleStorageInterface;
use Drupal\group\Entity\Views\GroupRelationshipViewsData;
use Drupal\group\Form\GroupJoinForm;
use Drupal\group\Form\GroupLeaveForm;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;
use Drupal\workspaces\Entity\Handler\IgnoredWorkspaceHandler;

/**
 * Defines the relationship entity.
 *
 * @ingroup group
 */
#[ContentEntityType(
  id: 'group_relationship',
  label: new TranslatableMarkup('Group relationship'),
  label_collection: new TranslatableMarkup('Group relationships'),
  label_singular: new TranslatableMarkup('group relationship'),
  label_plural: new TranslatableMarkup('group relationships'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
    'langcode' => 'langcode',
    'bundle' => 'type',
    'label' => 'label',
  ],
  handlers: [
    'access' => GroupRelationshipAccessControlHandler::class,
    'storage' => GroupRelationshipStorage::class,
    'storage_schema' => GroupRelationshipStorageSchema::class,
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => GroupRelationshipListBuilder::class,
    'views_data' => GroupRelationshipViewsData::class,
    'form' => [
      'add' => GroupRelationshipForm::class,
      'edit' => GroupRelationshipForm::class,
      'delete' => GroupRelationshipDeleteForm::class,
      'group-join' => GroupJoinForm::class,
      'group-leave' => GroupLeaveForm::class,
    ],
    'route_provider' => [
      'html' => GroupRelationshipRouteProvider::class,
    ],
    'workspace' => IgnoredWorkspaceHandler::class,
  ],
  links: [
    'add-form' => '/group/{group}/content/add/{plugin_id}',
    'add-page' => '/group/{group}/content/add',
    'canonical' => '/group/{group}/content/{group_relationship}',
    'collection' => '/group/{group}/content',
    'create-form' => '/group/{group}/content/create/{plugin_id}',
    'create-page' => '/group/{group}/content/create',
    'delete-form' => '/group/{group}/content/{group_relationship}/delete',
    'edit-form' => '/group/{group}/content/{group_relationship}/edit',
  ],
  permission_granularity: 'bundle',
  bundle_entity_type: 'group_relationship_type',
  bundle_label: new TranslatableMarkup('Group relationship type'),
  base_table: 'group_relationship',
  data_table: 'group_relationship_field_data',
  translatable: TRUE,
  label_count: [
    'singular' => '@count group relationship',
    'plural' => '@count group relationships',
  ],
  field_ui_base_route: 'entity.group_relationship_type.edit_form',
  constraints: [
    'GroupRelationshipCardinality' => [],
    'GroupMembershipRoles' => [],
  ],
)]
class GroupRelationship extends ContentEntityBase implements GroupRelationshipInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getRelationshipType() {
    return $this->get('type')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->get('gid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupId() {
    return $this->get('gid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupType() {
    return $this->get('group_type')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupTypeId() {
    return $this->get('group_type')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    if ($this->getPlugin()->getRelationType()->handlesConfigEntityType()) {
      if ($entity = $this->get('entity_id')->entity) {
        assert($entity instanceof ConfigWrapperInterface);
        return $entity->getConfigEntity();
      }
    }
    return $this->get('entity_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityId() {
    if ($this->getPlugin()->getRelationType()->handlesConfigEntityType()) {
      if ($entity = $this->get('entity_id')->entity) {
        assert($entity instanceof ConfigWrapperInterface);
        return $entity->getConfigEntityId();
      }
    }
    return $this->get('entity_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getRelationshipType()->getPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->get('plugin_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByPluginId($plugin_id) {
    $storage = \Drupal::entityTypeManager()->getStorage('group_relationship');
    assert($storage instanceof GroupRelationshipStorageInterface);
    return $storage->loadByPluginId($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByEntity(EntityInterface $entity) {
    $storage = \Drupal::entityTypeManager()->getStorage('group_relationship');
    assert($storage instanceof GroupRelationshipStorageInterface);
    return $storage->loadByEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return static::groupRelationTypeManager()
      ->getUiTextProvider($this->getPluginId())
      ->getRelationshipLabel($this);
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['group'] = $this->getGroupId();

    // These routes depend on the plugin ID.
    $is_form_rel = in_array($rel, ['add-form', 'create-form']);
    if ($is_form_rel) {
      $uri_route_parameters['plugin_id'] = $this->getPluginId();
    }

    // These parameters are not needed here so let's remove them or else they'll
    // get added as query arguments for no reason.
    if ($is_form_rel || $rel == 'create-page') {
      unset($uri_route_parameters['group_relationship'], $uri_route_parameters['group_relationship_type']);
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage);

    // Set the denormalized data from the bundle entity.
    $this->set('plugin_id', $this->getRelationshipType()->getPluginId());
    $this->set('group_type', $this->getRelationshipType()->getGroupTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set the denormalized data from the bundle entity. We repeat this after
    // having set it in ::postCreate() because it's imperative that no-one
    // changes this at all.
    $this->set('plugin_id', $this->getRelationshipType()->getPluginId());
    $this->set('group_type', $this->getRelationshipType()->getGroupTypeId());

    // Set the label so the DB also reflects it.
    $this->set('label', $this->label());
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // For memberships, we generally need to rebuild the group role cache for
    // the member's user account in the target group.
    $rebuild_group_role_cache = $this->getPluginId() == 'group_membership';

    if ($update === FALSE) {
      // We want to make sure that the entity we just added to the group behaves
      // as a grouped entity. Any code that runs "live" should get the new
      // information, but we want to invalidate cache entries that listed the
      // entity as a cacheable dependency.
      //
      // One drawback to the above is that not everything gets cached. Some
      // things get calculated and stored as entities (or other DB entries) of
      // their own without any cacheable metadata attached. One example being
      // the Pathauto module generating path aliases upon entity save.
      //
      // It is up to these modules to listen to hook_group_relationship_insert()
      // and call ::getEntity() on the relationship to also run their code on
      // the entity that got added to a group.
      Cache::invalidateTags($this->getEntity()->getCacheTags());
    }

    // If a membership gets updated, but the member's roles haven't changed, we
    // do not need to rebuild the group role cache for the member's account.
    elseif ($rebuild_group_role_cache && isset($this->original)) {
      assert($this->original instanceof GroupRelationshipInterface);

      $new = array_column($this->get('group_roles')->getValue(), 'target_id');
      $old = array_column($this->original->get('group_roles')->getValue(), 'target_id');
      sort($new);
      sort($old);
      $rebuild_group_role_cache = ($new != $old);
    }

    if ($rebuild_group_role_cache) {
      $role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      assert($role_storage instanceof GroupRoleStorageInterface);
      $role_storage->resetUserGroupRoleCache($this->getEntity(), $this->getGroup());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    foreach ($entities as $group_relationship) {
      assert($group_relationship instanceof GroupRelationshipInterface);
      if ($entity = $group_relationship->getEntity()) {
        // For the same reason we invalidate the cache tags of entities that are
        // added to a group, we need to invalidate the cache tags of those that
        // were removed from one. See ::postSave().
        //
        // We can only invalidate the cache tags of entities that still exist
        // and not the ones that were just deleted and triggered the deletion of
        // their group relationship entities.
        Cache::invalidateTags($entity->getCacheTags());

        // If a membership gets deleted, we need to reset the internal group
        // roles cache for the member in that group, but only if the user still
        // exists. Otherwise, it doesn't matter as the user ID will become void.
        if ($group_relationship->getPluginId() == 'group_membership') {
          $role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
          assert($role_storage instanceof GroupRoleStorageInterface);
          assert($entity instanceof UserInterface);
          $role_storage->resetUserGroupRoleCache($entity, $group_relationship->getGroup());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getListCacheTagsToInvalidate() {
    $tags = parent::getListCacheTagsToInvalidate();

    $group_id = $this->getGroupId();
    $plugin_id = $this->getRelationshipType()->getPluginId();
    $entity_id = $this->getEntityId();

    // A specific group gets any content, regardless of plugin used.
    // E.g.: A group's list of entities can be flushed with this.
    $tags[] = "group_relationship_list:group:$group_id";

    // A specific entity gets added to any group, regardless of plugin used.
    // E.g.: An entity's list of groups can be flushed with this.
    $tags[] = "group_relationship_list:entity:$entity_id";

    // Any entity gets added to any group using a specific plugin.
    // E.g.: A list of all memberships anywhere can be flushed with this.
    $tags[] = "group_relationship_list:plugin:$plugin_id";

    // A specific group gets any content using a specific plugin.
    // E.g.: A group's list of members can be flushed with this.
    $tags[] = "group_relationship_list:plugin:$plugin_id:group:$group_id";

    // A specific entity gets added to any group using a specific plugin.
    // E.g.: A user's list of memberships can be flushed with this.
    $tags[] = "group_relationship_list:plugin:$plugin_id:entity:$entity_id";

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['gid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent group'))
      ->setDescription(t('The group containing the entity.'))
      ->setSetting('target_type', 'group')
      ->setReadOnly(TRUE)
      ->setRequired(TRUE);

    // Borrowed this logic from the Comment module.
    // Warning! May change in the future: https://www.drupal.org/node/2346347
    $fields['entity_id'] = BaseFieldDefinition::create('group_relationship_target')
      ->setLabel(t('Content'))
      ->setDescription(t('The entity to add to the group.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ]);

    assert($fields['uid'] instanceof BaseFieldDefinition);
    $fields['uid']
      ->setLabel(t('Group relationship creator'))
      ->setDescription(t('The username of the group relationship creator.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the group relationship was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed on'))
      ->setDescription(t('The time that the group relationship was last edited.'))
      ->setTranslatable(TRUE);

    // The following fields are denormalizations of info found on the bundle,
    // but often necessary to write performant joins. As we can't join config
    // entity tables, we explicitly store the info again on this entity type.
    $fields['plugin_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plugin ID'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['group_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Group type'))
      ->setSetting('target_type', 'group_type')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    if (\Drupal::moduleHandler()->moduleExists('path')) {
      $fields['path'] = BaseFieldDefinition::create('path')
        ->setLabel(t('URL alias'))
        ->setTranslatable(TRUE)
        ->setDisplayOptions('form', [
          'type' => 'path',
          'weight' => 30,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setComputed(TRUE);
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    if ($relationship_type = GroupRelationshipType::load($bundle)) {
      assert($relationship_type instanceof GroupRelationshipTypeInterface);
      $fields['entity_id'] = clone $base_field_definitions['entity_id'];
      static::groupRelationTypeManager()
        ->getEntityReferenceHandler($relationship_type->getPluginId())
        ->configureField($fields['entity_id']);

      return $fields;
    }

    return [];
  }

  /**
   * Gets the group relation type manager.
   *
   * @return \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface
   *   The group relation type manager.
   *
   * @internal Try to properly inject the service when possible.
   */
  protected static function groupRelationTypeManager() {
    return \Drupal::service('group_relation_type.manager');
  }

}
