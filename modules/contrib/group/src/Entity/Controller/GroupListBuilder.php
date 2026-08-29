<?php

namespace Drupal\group\Entity\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides a list controller for group entities.
 *
 * @ingroup group
 */
class GroupListBuilder extends EntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    // @phpstan-ignore-next-line drupal.entityStoragePropertyAssignment
    EntityStorageInterface $storage,
    RedirectDestinationInterface $redirect_destination,
    protected AccountInterface $currentUser,
    ModuleHandlerInterface $module_handler,
    protected RouterInterface $router,
  ) {
    parent::__construct($entity_type, $storage);
    $this->redirectDestination = $redirect_destination;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('redirect.destination'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('router.no_access_checks')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'gid' => [
        'data' => $this->t('Group ID'),
        'specifier' => 'id',
        'field' => 'id',
      ],
      'label' => [
        'data' => $this->t('Name'),
        'specifier' => 'label',
        'field' => 'label',
      ],
      'type' => [
        'data' => $this->t('Type'),
        'specifier' => 'type',
        'field' => 'type',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'specifier' => 'status',
        'field' => 'status',
      ],
      'uid' => [
        'data' => $this->t('Owner'),
      ],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof GroupInterface);
    $row['id'] = $entity->id();
    // EntityListBuilder sets the table rows using the #rows property, so we
    // need to add the render array using the 'data' key.
    $row['name']['data'] = $entity->toLink()->toRenderable();
    $row['type'] = $entity->getGroupType()->label();
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    $row['uid'] = $entity->getOwner()->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no groups yet.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery();

    // Add a simple table sort by header, see ::buildHeader().
    $header = $this->buildHeader();
    $query->tableSort($header);

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->accessCheck()->execute();
  }

  /**
   * {@inheritdoc}
   *
   * @todo Make second parameter required when minimum supported Drupal is 12.
   */
  protected function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    $cacheability ??= new CacheableMetadata();

    assert($entity instanceof GroupInterface);
    $operations = parent::getDefaultOperations($entity, $cacheability);

    if ($this->moduleHandler->moduleExists('views') && $this->router->getRouteCollection()->get('view.group_members.page_1') !== NULL) {
      $cacheability->addCacheContexts(['user.group_permissions']);
      if ($entity->hasPermission('administer members', $this->currentUser)) {
        $operations['members'] = [
          'title' => $this->t('Members'),
          'weight' => 15,
          'url' => Url::fromRoute('view.group_members.page_1', ['group' => $entity->id()]),
        ];
      }
    }

    $group_type = $entity->getGroupType();

    $cacheability->addCacheableDependency($group_type);
    if ($group_type->shouldCreateNewRevision()) {
      $cacheability->addCacheContexts(['user.group_permissions']);
      if ($entity->hasPermission('view group revisions', $this->currentUser)) {
        $operations['revisions'] = [
          'title' => $this->t('Revisions'),
          'weight' => 20,
          'url' => $entity->toUrl('version-history'),
        ];
      }
    }

    // Add the current path or destination as a redirect to the operation links.
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }

    return $operations;
  }

}
