<?php

namespace Drupal\webform_entity_builder\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Entity Builder item annotation object.
 *
 * @see \Drupal\webform_entity_builder\Plugin\EntityBuilderManager
 * @see plugin_api
 *
 * @Annotation
 */
class EntityBuilder extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The type of entity this plugin creates (e.g. "node:article", "node:*", "node").
   *
   * @var string
   */
  public $type;

}
