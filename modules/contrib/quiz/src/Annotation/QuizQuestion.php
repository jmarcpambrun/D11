<?php

namespace Drupal\quiz\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Quiz Question annotation object.
 *
 * @see QuizQuestionPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class QuizQuestion extends Plugin {

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

}
