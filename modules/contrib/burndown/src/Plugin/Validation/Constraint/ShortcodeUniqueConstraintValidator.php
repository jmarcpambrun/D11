<?php

namespace Drupal\burndown\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ShortcodeUnique constraint.
 */
class ShortcodeUniqueConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!isset($entity) || !$constraint instanceof ShortcodeUniqueConstraint) {
      return;
    }

    if ($entity->getEntityTypeId() == 'burndown_project') {
      // Get entity id. This will be null for new entities.
      $entity_id = $entity->id();

      // Get the shortcode.
      $shortcode = $entity->getShortCode();

      // Transform to uppercase alphabetical version of the string.
      $shortcode = preg_replace("/[^a-zA-Z]+/", "", $shortcode);
      $shortcode = strtoupper($shortcode);

      // Find out how many projects already have an identical shortcode.
      if (is_null($entity_id)) {
        $project_count = \Drupal::entityQuery('burndown_project')
          ->condition('shortcode', $shortcode)
          ->count()
          ->accessCheck(FALSE)
          ->execute();
      }
      // If id exists, then we are editing. We do not want to count
      // the current entity too, otherwise all edits will result in a
      // violation!
      else {
        $project_count = \Drupal::entityQuery('burndown_project')
          ->condition('shortcode', $shortcode)
          ->condition('id', $entity_id, "!=")
          ->count()
          ->accessCheck(FALSE)
          ->execute();
      }

      if ($project_count > 0) {
        $this->context->addViolation($constraint->message);
      }
    }
  }

}
