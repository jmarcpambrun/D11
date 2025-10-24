<?php

namespace Drupal\maestro\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Maestro Set Process Variable Task Action plugin annotation object.
 *
 * @Annotation
 */
class MaestroSetProcessVariablePlugin extends Plugin {
  
  /**
   * The id.
   *
   * @var string
   */
  public $id;

  /**
   * The short description.  
   *   Used for drop downs, radio buttons, labels.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public $short_description;

  /**
   * The Maestro AI Task's capability description.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public $description;
}
