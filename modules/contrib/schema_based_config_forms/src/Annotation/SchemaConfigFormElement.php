<?php

namespace Drupal\schema_based_config_forms\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Schema Config Form Element annotation object.
 *
 * @Annotation
 */
final class SchemaConfigFormElement extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The schema data type(s) this element is a default for.
   */
  public array $types = [];

  /**
   * The priority of this plugin.
   *
   * Affects which plugin is used based on config schema type, if multiple
   * plugins support the same schema type.
   */
  public int $weight = 0;

}
