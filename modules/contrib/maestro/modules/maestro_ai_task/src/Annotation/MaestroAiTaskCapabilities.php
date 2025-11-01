<?php

namespace Drupal\maestro_ai_task\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Maestro AI Task Capability plugin annotation object.
 *
 * @Annotation
 */
class MaestroAiTaskCapabilities extends Plugin {
  
  /**
   * The AI provider type.
   *
   * @var string
   */
  public $ai_provider;

  /**
   * The Maestro AI Task's capability description.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public $capability_description;
}
