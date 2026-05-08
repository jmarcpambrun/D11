<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\group\QueryAccess\EntityQueryAlter;
use Drupal\group\QueryAccess\GroupQueryAlter;
use Drupal\group\QueryAccess\GroupRelationshipQueryAlter;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;

/**
 * Query hook implementations for Group.
 */
final class QueryHooks {

  public function __construct(
    protected ClassResolverInterface $classResolver,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_query_TAG_alter().
   */
  #[Hook('query_entity_query_alter', order: Order::Last)]
  public function entityQueryAlter(AlterableInterface $query): void {
    if (!$query instanceof SelectInterface) {
      return;
    }

    $entity_type_id = $query->getMetaData('entity_type');
    if ($query->hasTag($entity_type_id . '_access')) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Add specific access checks based on the entity type. Please note that
      // we first check for the group and relationship entity types because we
      // have full control over those and can therefore optimize the query more.
      // If the query is for a different entity type, we fall back to the
      // default query alter.
      //
      // PLEASE KEEP IN MIND that this means that a plugin that allows you to
      // nest groups will have no query access added for nested groups
      // whatsoever. This is by design as those plugins should opt to use the
      // permission calculator system, as it's way faster and automatically
      // included in GroupQueryAlter.
      switch ($entity_type_id) {
        case 'group':
          $class_name = GroupQueryAlter::class;
          break;

        case 'group_relationship':
          $class_name = GroupRelationshipQueryAlter::class;
          break;

        default:
          $class_name = EntityQueryAlter::class;
      }

      $this->classResolver
        ->getInstanceFromDefinition($class_name)
        ->alter($query, $entity_type);
    }
  }

  /**
   * Implements hook_query_TAG_alter().
   *
   * This uses the custom 'views_entity_query' tag added in :viewsQueryAlter().
   */
  #[Hook('query_views_entity_query_alter')]
  public function viewsEntityQueryAlter(AlterableInterface $query): void {
    if (!$query instanceof SelectInterface) {
      return;
    }

    $entity_type_id = $query->getMetaData('entity_type');
    $query->addTag($entity_type_id . '_access');
    $this->entityQueryAlter($query);
  }

  /**
   * Implements hook_views_query_alter().
   */
  #[Hook('views_query_alter', order: Order::Last)]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    if (!$query instanceof Sql || !empty($query->options['disable_sql_rewrite'])) {
      return;
    }

    $table_info = $query->getEntityTableInfo();
    $base_table = reset($table_info);
    if (empty($base_table['entity_type']) || $base_table['relationship_id'] != 'none') {
      return;
    }

    // Add a custom tag so we don't trigger all other 'entity_query' tag alters.
    $entity_type_id = $base_table['entity_type'];
    $query->addTag('views_entity_query');

    // Build the Views query already so we can access the DB query.
    $query->build($view);
    $view->built = TRUE;

    // Add metadata to the DB query.
    $query = $view->build_info['query'];
    $count_query = $view->build_info['count_query'];
    $query->addMetaData('entity_type', $entity_type_id);
    $count_query->addMetaData('entity_type', $entity_type_id);
  }

}
