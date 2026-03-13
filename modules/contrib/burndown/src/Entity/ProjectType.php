<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Project type entity.
 *
 * @ConfigEntityType(
 *   id = "burndown_project_type",
 *   label = @Translation("Project type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\burndown\ProjectTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\burndown\Form\ProjectTypeForm",
 *       "edit" = "Drupal\burndown\Form\ProjectTypeForm",
 *       "delete" = "Drupal\burndown\Form\ProjectTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\burndown\ProjectTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "burndown_project_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "burndown_project",
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
 *     "canonical" = "/admin/structure/burndown/project_type/{burndown_project_type}",
 *     "add-form" = "/admin/structure/burndown/project_type/add",
 *     "edit-form" = "/admin/structure/burndown/project_type/{burndown_project_type}/edit",
 *     "delete-form" = "/admin/structure/burndown/project_type/{burndown_project_type}/delete",
 *     "collection" = "/admin/structure/burndown/project_type"
 *   }
 * )
 */
class ProjectType extends ConfigEntityBundleBase implements ProjectTypeInterface {

  /**
   * The Project type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Project type label.
   *
   * @var string
   */
  protected $label;

}
