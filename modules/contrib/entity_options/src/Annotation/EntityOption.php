<?php

namespace Drupal\entity_options\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Entity Option annotation object.
 *
 * Plugin Namespace: Plugin\EntityOption.
 *
 * Entity Option plugin implementations need to define a plugin definition array
 * through annotation. The definition includes the following keys:
 *
 * - id: The unique, system-wide identifier of the node option.
 * - label: The human-readable name of the node option, translated.
 * - description: Plugin description, translated.
 * - allow_overrides: Whether this option should allow node overrides.
 *
 * @see \Drupal\entity_options\Plugin\EntityOptionPluginInterface
 * @see \Drupal\entity_options\Plugin\EntityOptionBase
 * @see \Drupal\entity_options\Plugin\EntityOptionManager
 * @see plugin_api
 *
 * @Annotation
 */
class EntityOption extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

  /**
   * Flag indicating whether this option allows per-node overrides.
   *
   * @var bool
   */
  public $allow_overrides = FALSE;

}
