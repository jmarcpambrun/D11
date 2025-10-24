<?php

namespace Drupal\burndown\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevent project creation if shortcode is not unique.
 *
 * @Constraint(
 *   id = "ShortcodeUnique",
 *   label = @Translation("Prevent project creation if shortcode is not unique", context = "Validation"),
 *   type = "entity"
 * )
 */
class ShortcodeUniqueConstraint extends Constraint {

  /**
   * Message shown when trying create project if shortcode is not unique.
   *
   * @var string
   */
  public $message = 'Project creation failed: shortcode must be unique.';

}
