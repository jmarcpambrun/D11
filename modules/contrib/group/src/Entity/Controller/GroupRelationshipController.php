<?php

namespace Drupal\group\Entity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for GroupRelationship routes.
 */
class GroupRelationshipController extends ControllerBase {

  public function __construct(
    protected PrivateTempStoreFactory $privateTempStoreFactory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    protected GroupRelationTypeManagerInterface $groupRelationTypeManager,
    protected RendererInterface $renderer,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('group_relation_type.manager'),
      $container->get('renderer'),
    );
  }

  /**
   * Provides the relationship creation overview page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   * @param bool $create_mode
   *   (optional) Whether the target entity still needs to be created. Defaults
   *   to FALSE, meaning the target entity is assumed to exist already.
   * @param ?string $base_plugin_id
   *   (optional) A base plugin ID to filter the bundles on. This can be useful
   *   when you want to show the add page for just a single plugin that has
   *   derivatives for the target entity type's bundles.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The relationship creation overview page or a redirect to the form for
   *   adding relationships if there is only one relationship type.
   */
  public function addPage(GroupInterface $group, $create_mode = FALSE, ?string $base_plugin_id = NULL) {
    $build = ['#theme' => 'entity_add_list', '#bundles' => []];
    $form_route = $this->addPageFormRoute($group, $create_mode);
    $relationship_types = $this->addPageBundles($group, $create_mode, $base_plugin_id);

    // Set the add bundle message if available.
    $add_bundle_message = $this->addPageBundleMessage($group, $create_mode);
    if ($add_bundle_message !== FALSE) {
      $build['#add_bundle_message'] = $add_bundle_message;
    }

    // Filter out the bundles the user doesn't have access to.
    foreach ($relationship_types as $relationship_type_id => $relationship_type) {
      $access_control_handler = $this->groupRelationTypeManager->getAccessControlHandler($relationship_type->getPluginId());
      $access = $create_mode
        ? $access_control_handler->entityCreateAccess($group, $this->currentUser(), TRUE)
        : $access_control_handler->relationshipCreateAccess($group, $this->currentUser(), TRUE);

      if (!$access->isAllowed()) {
        unset($relationship_types[$relationship_type_id]);
      }

      $this->renderer->addCacheableDependency($build, $access);
    }

    // Redirect if there's only one bundle available.
    if (count($relationship_types) == 1) {
      $route_params = [
        'group' => $group->id(),
        'plugin_id' => reset($relationship_types)->getPluginId(),
      ];
      $url = Url::fromRoute($form_route, $route_params, ['absolute' => TRUE]);
      return new RedirectResponse($url->toString());
    }

    // Set the info for all of the remaining bundles.
    foreach ($relationship_types as $relationship_type_id => $relationship_type) {
      $ui_text_provider = $this->groupRelationTypeManager->getUiTextProvider($relationship_type->getPluginId());

      $label = $ui_text_provider->getAddPageLabel($create_mode);
      $build['#bundles'][$relationship_type_id] = [
        'label' => $label,
        'description' => $ui_text_provider->getAddPageDescription($create_mode),
        'add_link' => Link::createFromRoute($label, $form_route, [
          'group' => $group->id(),
          'plugin_id' => $relationship_type->getPluginId(),
        ]),
      ];
    }

    // Add the list cache tags for the GroupRelationshipType entity type.
    $bundle_entity_type = $this->entityTypeManager->getDefinition('group_relationship_type');
    $build['#cache']['tags'] = $bundle_entity_type->getListCacheTags();

    return $build;
  }

  /**
   * Retrieves a list of available relationship types for the add page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   * @param bool $create_mode
   *   Whether the target entity still needs to be created.
   * @param string|null $base_plugin_id
   *   (optional) A base plugin ID to filter the bundles on. This can be useful
   *   when you want to show the add page for just a single plugin that has
   *   derivatives for the target entity type's bundles.
   *
   * @return \Drupal\group\Entity\GroupRelationshipTypeInterface[]
   *   An array of relationship types, keyed by their ID.
   *
   * @see ::addPage()
   */
  protected function addPageBundles(GroupInterface $group, $create_mode, $base_plugin_id) {
    $storage = $this->entityTypeManager->getStorage('group_relationship_type');
    assert($storage instanceof GroupRelationshipTypeStorageInterface);

    $relationship_types = $storage->loadByGroupType($group->getGroupType());
    foreach ($relationship_types as $relationship_type_id => $relationship_type) {
      $relation = $relationship_type->getPlugin();

      // Check the base plugin ID if a plugin filter was specified.
      if ($base_plugin_id && $relation->getBaseId() !== $base_plugin_id) {
        unset($relationship_types[$relationship_type_id]);
      }
      // Skip the bundle if we are listing bundles that allow you to create an
      // entity in the group and the bundle's plugin does not support that.
      elseif ($create_mode && !$relation->getRelationType()->definesEntityAccess()) {
        unset($relationship_types[$relationship_type_id]);
      }
    }

    return $relationship_types;
  }

  /**
   * Returns the 'add_bundle_message' string for the add page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   * @param bool $create_mode
   *   Whether the target entity still needs to be created.
   *
   * @return string|false
   *   The translated string or FALSE if no string should be set.
   *
   * @see ::addPage()
   */
  protected function addPageBundleMessage(GroupInterface $group, $create_mode) {
    // We do not set the 'add_bundle_message' variable because we deny access to
    // the page if no bundle is available. This method exists so that modules
    // that extend this controller may specify a message should they decide to
    // allow access to their page even if it has no bundles.
    return FALSE;
  }

  /**
   * Returns the route name of the form the add page should link to.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   * @param bool $create_mode
   *   Whether the target entity still needs to be created.
   *
   * @return string
   *   The route name.
   *
   * @see ::addPage()
   */
  protected function addPageFormRoute(GroupInterface $group, $create_mode) {
    return $create_mode
      ? 'entity.group_relationship.create_form'
      : 'entity.group_relationship.add_form';
  }

  /**
   * Provides the relationship submission form.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   * @param string $plugin_id
   *   The group relation to add content with.
   *
   * @return array
   *   A group submission form.
   */
  public function addForm(GroupInterface $group, $plugin_id) {
    $storage = $this->entityTypeManager()->getStorage('group_relationship_type');
    assert($storage instanceof GroupRelationshipTypeStorageInterface);

    $values = [
      'type' => $storage->getRelationshipTypeId($group->bundle(), $plugin_id),
      'gid' => $group->id(),
    ];
    $group_relationship = $this->entityTypeManager()->getStorage('group_relationship')->create($values);

    return $this->entityFormBuilder->getForm($group_relationship, 'add');
  }

  /**
   * The _title_callback for the entity.group_relationship.add_form route.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   * @param string $plugin_id
   *   The group relation to add content with.
   *
   * @return string
   *   The page title.
   */
  public function addFormTitle(GroupInterface $group, $plugin_id) {
    return $this->groupRelationTypeManager->getUiTextProvider($plugin_id)->getAddFormTitle(FALSE);
  }

  /**
   * The _title_callback for the entity.group_relationship.edit_form route.
   *
   * Overrides the Drupal\Core\Entity\Controller\EntityController::editTitle().
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param ?\Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the entity edit page, if an entity was found.
   */
  public function editFormTitle(RouteMatchInterface $route_match, ?EntityInterface $_entity = NULL) {
    if ($entity = $route_match->getParameter('group_relationship')) {
      return $this->t('Edit %label', ['%label' => $entity->label()]);
    }
    return NULL;
  }

  /**
   * The _title_callback for the entity.group_relationship.collection route.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   *
   * @return string
   *   The page title.
   */
  public function collectionTitle(GroupInterface $group) {
    return $this->t('All entity relations for @group', ['@group' => $group->label()]);
  }

  /**
   * Provides the relationship creation form.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to add the relationship to.
   * @param string $plugin_id
   *   The group relation to add content with.
   *
   * @return array
   *   A relationship creation form.
   */
  public function createForm(GroupInterface $group, $plugin_id) {
    $group_relation_type = $group->getGroupType()->getPlugin($plugin_id)->getRelationType();

    // Tell the form state we are on a create form that needs to be enhanced.
    $extra['group__create_form_should_enhance'] = TRUE;
    $extra['group_relation'] = $plugin_id;
    $extra['group'] = $group;

    // Figure out what entity type the plugin is serving.
    $entity_type_id = $group_relation_type->getEntityTypeId();
    $entity_type = $this->entityTypeManager()->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);

    // Create a new entity with bundle if available.
    $values = [];
    if (($key = $entity_type->getKey('bundle')) && ($bundle = $group_relation_type->getEntityBundle())) {
      $values[$key] = $bundle;
    }
    $entity = $storage->create($values);

    // Use the add form handler if available.
    $operation = 'default';
    if ($entity_type->getFormClass('add')) {
      $operation = 'add';
    }

    // Return the entity form with the configuration gathered above.
    return $this->entityFormBuilder()->getForm($entity, $operation, $extra);
  }

  /**
   * The _title_callback for the entity.group_relationship.create_form route.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to create the relationship for.
   * @param string $plugin_id
   *   The group relation to create the relationship with.
   *
   * @return string
   *   The page title.
   */
  public function createFormTitle(GroupInterface $group, $plugin_id) {
    return $this->groupRelationTypeManager->getUiTextProvider($plugin_id)->getAddFormTitle(TRUE);
  }

}
