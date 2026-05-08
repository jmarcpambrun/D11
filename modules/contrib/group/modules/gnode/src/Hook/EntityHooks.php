<?php

declare(strict_types=1);

namespace Drupal\gnode\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Entity hook implementations for Group Node.
 */
final class EntityHooks {

  use StringTranslationTrait;

  public function __construct(
    protected GroupRelationTypeManagerInterface $groupRelationTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountProxyInterface $currentUser,
    protected AccessAwareRouterInterface $router,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('node_type_insert')]
  public function nodeTypeInsert(NodeTypeInterface $node_type): void {
    $this->groupRelationTypeManager->clearCachedDefinitions();
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];

    if ($entity->getEntityTypeId() == 'group' && $this->moduleHandler->moduleExists('views')) {
      assert($entity instanceof GroupInterface);
      if ($entity->hasPermission('access group_node overview', $this->currentUser)) {
        if ($this->router->getRouteCollection()->get('view.group_nodes.page_1') !== NULL) {
          $operations['nodes'] = [
            'title' => $this->t('Nodes'),
            'weight' => 20,
            'url' => Url::fromRoute('view.group_nodes.page_1', ['group' => $entity->id()]),
          ];
        }
      }
    }

    return $operations;
  }

}
