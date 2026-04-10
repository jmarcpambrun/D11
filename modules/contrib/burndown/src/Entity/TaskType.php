<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Task type entity.
 *
 * @ConfigEntityType(
 *   id = "burndown_task_type",
 *   label = @Translation("Task type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\burndown\TaskTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\burndown\Form\TaskTypeForm",
 *       "edit" = "Drupal\burndown\Form\TaskTypeForm",
 *       "delete" = "Drupal\burndown\Form\TaskTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\burndown\TaskTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "burndown_task_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "burndown_task",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/burndown_task_type/{burndown_task_type}",
 *     "add-form" = "/admin/structure/burndown_task_type/add",
 *     "edit-form" = "/admin/structure/burndown_task_type/{burndown_task_type}/edit",
 *     "delete-form" = "/admin/structure/burndown_task_type/{burndown_task_type}/delete",
 *     "collection" = "/admin/structure/burndown_task_type"
 *   }
 * )
 */
class TaskType extends ConfigEntityBundleBase implements TaskTypeInterface {

  /**
   * The Task type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Task type label.
   *
   * @var string
   */
  protected $label;

}
