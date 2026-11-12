<?php

namespace Drupal\page_manager\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\page_manager\Entity\LayoutBuilderStorage;
use Drupal\page_manager\Form\LayoutBuilderForm;
use Drupal\page_manager\Plugin\Derivative\PageManagerLocalTask;

/**
 * Hook implementations for page_manager.
 */
class PageManagerHooks {

  public function __construct(
    protected RouteProviderInterface $routeProvider,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types) {
    if (_page_manager_is_layout_builder_enabled()) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
      $entity_types['page_variant']
        ->setHandlerClass('storage', LayoutBuilderStorage::class)
        ->setFormClass('layout_builder', LayoutBuilderForm::class);
    }
  }

  /**
   * Implements hook_display_variant_plugin_alter().
   */
  #[Hook('display_variant_plugin_alter')]
  public function displayVariantPluginAlter(array &$definitions) {
    // If layout_builder is not installed, remove its display variant.
    if (!_page_manager_is_layout_builder_enabled()) {
      unset($definitions['layout_builder']);
    }
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks) {
    $local_task = new PageManagerLocalTask($this->routeProvider, $this->entityTypeManager);
    $local_task->alterLocalTasks($local_tasks);
  }

}
