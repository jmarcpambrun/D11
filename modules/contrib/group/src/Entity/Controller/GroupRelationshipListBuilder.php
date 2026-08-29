<?php

namespace Drupal\group\Entity\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for relationship entities.
 *
 * @ingroup group
 */
class GroupRelationshipListBuilder extends EntityListBuilder {

  /**
   * The group to show the content for.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    RedirectDestinationInterface $redirect_destination,
    RouteMatchInterface $route_match,
    EntityTypeInterface $entity_type,
  ) {
    parent::__construct($entity_type, $entityTypeManager->getStorage($entity_type->id()));
    $this->redirectDestination = $redirect_destination;
    // There should always be a group on the route for relationship lists.
    $this->group = $route_match->getParameters()->get('group');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('redirect.destination'),
      $container->get('current_route_match'),
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery();
    $query->sort($this->entityType->getKey('id'));

    // Only show relationships for the group on the route.
    $query->condition('gid', $this->group->id());

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->accessCheck()->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'id' => $this->t('ID'),
      'label' => $this->t('Content label'),
      'entity_type' => $this->t('Entity type'),
      'plugin' => $this->t('Plugin used'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof GroupRelationshipInterface);
    $row['id'] = $entity->id();

    // EntityListBuilder sets the table rows using the #rows property, so we
    // need to add links as render arrays using the 'data' key.
    $row['label']['data'] = $entity->toLink()->toRenderable();
    $group_relation_type = $entity->getPlugin()->getRelationType();
    $row['entity_type'] = $this->entityTypeManager->getDefinition($group_relation_type->getEntityTypeId())->getLabel();
    $row['plugin'] = $group_relation_type->getLabel();

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no entities related to this group yet.');
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Make second parameter required when minimum supported Drupal is 12.
   */
  protected function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    $cacheability ??= new CacheableMetadata();

    assert($entity instanceof GroupRelationshipInterface);
    $operations = parent::getDefaultOperations($entity, $cacheability);

    // Improve the edit and delete operation labels.
    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Edit relation');
    }
    if (isset($operations['delete'])) {
      $operations['delete']['title'] = $this->t('Delete relation');
    }

    // Slap on redirect destinations for the administrative operations.
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }

    // Add an operation to view the actual entity.
    $target_entity = $entity->getEntity();
    if ($target_entity->hasLinkTemplate('canonical')) {
      $target_view_access = $target_entity->access('view', return_as_object: TRUE);
      $cacheability->addCacheableDependency($target_view_access);

      if ($target_view_access->isAllowed()) {
        $operations['view'] = [
          'title' => $this->t('View entity'),
          'weight' => 101,
          'url' => $entity->getEntity()->toUrl('canonical'),
        ];
      }
    }

    return $operations;
  }

}
