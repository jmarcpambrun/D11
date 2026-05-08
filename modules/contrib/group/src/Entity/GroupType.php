<?php

namespace Drupal\group\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\Entity\Exception\ConfigEntityIdLengthException;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\Access\GroupTypeAccessControlHandler;
use Drupal\group\Entity\Controller\GroupTypeListBuilder;
use Drupal\group\Entity\Form\GroupTypeDeleteForm;
use Drupal\group\Entity\Form\GroupTypeForm;
use Drupal\group\PermissionScopeInterface;

/**
 * Defines the Group type configuration entity.
 *
 * @ingroup group
 */
#[ConfigEntityType(
  id: 'group_type',
  label: new TranslatableMarkup('Group type'),
  label_collection: new TranslatableMarkup('Group types'),
  label_singular: new TranslatableMarkup('group type'),
  label_plural: new TranslatableMarkup('group types'),
  config_prefix: 'type',
  static_cache: TRUE,
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'access' => GroupTypeAccessControlHandler::class,
    'list_builder' => GroupTypeListBuilder::class,
    'form' => [
      'add' => GroupTypeForm::class,
      'edit' => GroupTypeForm::class,
      'delete' => GroupTypeDeleteForm::class,
    ],
    'route_provider' => [
      'html' => DefaultHtmlRouteProvider::class,
    ],
  ],
  links: [
    'add-form' => '/admin/group/types/add',
    'collection' => '/admin/group/types',
    'content-plugins' => '/admin/group/types/manage/{group_type}/content',
    'delete-form' => '/admin/group/types/manage/{group_type}/delete',
    'edit-form' => '/admin/group/types/manage/{group_type}',
    'permissions-form' => '/admin/group/types/manage/{group_type}/permissions',
  ],
  admin_permission: 'administer group',
  bundle_of: 'group',
  label_count: [
    'singular' => '@count group type',
    'plural' => '@count group types',
  ],
  config_export: [
    'id',
    'label',
    'description',
    'new_revision',
    'creator_membership',
    'creator_roles',
  ],
)]
class GroupType extends ConfigEntityBundleBase implements GroupTypeInterface {

  /**
   * The machine name of the group type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the group type.
   *
   * @var string
   */
  protected $label;

  /**
   * A brief description of the group type.
   *
   * @var string
   */
  protected $description;

  /**
   * Whether a new revision should be created by default.
   *
   * @var bool
   */
  protected $new_revision = TRUE;

  /**
   * The group creator automatically receives a membership.
   *
   * @var bool
   */
  protected $creator_membership = TRUE;

  /**
   * The IDs of the group roles a group creator should receive.
   *
   * @var string[]
   */
  protected $creator_roles = [];

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($include_synchronized = TRUE) {
    $properties = ['group_type' => $this->id()];

    // Exclude synchronized roles if told to.
    if ($include_synchronized === FALSE) {
      $properties['scope'] = PermissionScopeInterface::INDIVIDUAL_ID;
    }

    return $this->entityTypeManager()
      ->getStorage('group_role')
      ->loadByProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoleIds($include_synchronized = TRUE) {
    $query = $this->entityTypeManager()
      ->getStorage('group_role')
      ->getQuery()
      ->condition('group_type', $this->id());

    // Exclude synchronized roles if told to.
    if ($include_synchronized === FALSE) {
      $query->condition('scope', PermissionScopeInterface::INDIVIDUAL_ID);
    }

    return $query->accessCheck(FALSE)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function shouldCreateNewRevision() {
    return $this->new_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function setNewRevision($new_revision) {
    $this->new_revision = $new_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function creatorGetsMembership() {
    return $this->creator_membership;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatorRoleIds() {
    return $this->creator_roles;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Throw an exception if the group type ID is longer than the limit.
    if (strlen($this->id()) > GroupTypeInterface::ID_MAX_LENGTH) {
      throw new ConfigEntityIdLengthException("Attempt to create a group type with an ID longer than " . GroupTypeInterface::ID_MAX_LENGTH . " characters: {$this->id()}.");
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (!$update) {
      // Store the id in a short variable for readability.
      $group_type_id = $this->id();

      // @todo Remove this line when https://www.drupal.org/node/2645202 lands.
      $this->setOriginalId($group_type_id);

      // The code below will create relationship types for enforced plugins. It
      // is extremely important that we only run this code when we're not
      // dealing with config synchronization.
      //
      // Any of the config entities created here could still be queued up for
      // import in a combined config import. Therefore, we only create them in
      // \Drupal\group\EventSubscriber\ConfigSubscriber after the entire import
      // has finished.
      if (!$this->isSyncing()) {
        // Enable enforced relation plugins for the new group type.
        $this->getGroupRelationTypeManager()->installEnforced($this);
      }
    }
  }

  /**
   * Returns the group relation type manager.
   *
   * @return \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface
   *   The group relation type manager.
   */
  protected function getGroupRelationTypeManager() {
    return \Drupal::service('group_relation_type.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getInstalledPlugins() {
    return $this->getGroupRelationTypeManager()->getInstalled($this);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPlugin($plugin_id) {
    $installed = $this->getGroupRelationTypeManager()->getInstalledIds($this);
    return in_array($plugin_id, $installed);
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin($plugin_id) {
    return $this->getInstalledPlugins()->get($plugin_id);
  }

}
