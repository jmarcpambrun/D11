<?php

namespace Drupal\burndown\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Default Swimlane entity.
 *
 * @ConfigEntityType(
 *   id = "default_swimlane",
 *   label = @Translation("Default Swimlane"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\burndown\DefaultSwimlaneListBuilder",
 *     "form" = {
 *       "add" = "Drupal\burndown\Form\DefaultSwimlaneForm",
 *       "edit" = "Drupal\burndown\Form\DefaultSwimlaneForm",
 *       "delete" = "Drupal\burndown\Form\DefaultSwimlaneDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\burndown\DefaultSwimlaneHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "default_swimlane",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "sort_order",
 *     "show_backlog",
 *     "show_project_board",
 *     "show_completed"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/burndown/default_swimlane/{default_swimlane}",
 *     "add-form" = "/admin/config/burndown/default_swimlane/add",
 *     "edit-form" = "/admin/config/burndown/default_swimlane/{default_swimlane}/edit",
 *     "delete-form" = "/admin/config/burndown/default_swimlane/{default_swimlane}/delete",
 *     "collection" = "/admin/config/burndown/default_swimlane"
 *   }
 * )
 */
class DefaultSwimlane extends ConfigEntityBase implements DefaultSwimlaneInterface {

  /**
   * The Default Swimlane ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Default Swimlane label.
   *
   * @var string
   */
  protected $label;

  /**
   * Default sort order.
   *
   * @var int
   */
  protected $sort_order;

  /**
   * Show in backlog display?
   *
   * @var bool
   */
  protected $show_backlog;

  /**
   * Show on project board?
   *
   * @var bool
   */
  protected $show_project_board;

  /**
   * Show on completed board?
   *
   * @var bool
   */
  protected $show_completed;

  /**
   * {@inheritdoc}
   */
  public function getSortOrder() {
    return $this->sort_order;
  }

  /**
   * {@inheritdoc}
   */
  public function getShowBacklog() {
    return $this->show_backlog;
  }

  /**
   * {@inheritdoc}
   */
  public function getShowProjectBoard() {
    return $this->show_project_board;
  }

  /**
   * {@inheritdoc}
   */
  public function getShowCompleted() {
    return $this->show_completed;
  }

}
