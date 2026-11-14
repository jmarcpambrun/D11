<?php

namespace Drupal\ai_integration_eca_agents\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if all successors are valid.
 */
#[Constraint(
  id: 'SuccessorsAreValid',
  label: new TranslatableMarkup('Successor are valid')
)]
class SuccessorsAreValidConstraint extends SymfonyConstraint {

  /**
   * The error message if the successor is invalid.
   *
   * @var string
   */
  public string $invalidSuccessorMessage = "Invalid successor ID '@successorId' for @type '@elementId'. Must reference a gateway or action.";

  /**
   * The error message if the condition is invalid.
   *
   * @var string
   */
  public string $invalidConditionMessage = "Invalid condition '@conditionId' for successor of @type '@elementId'. Must be in the list of conditions.";

  /**
   * The error message if the event is succeeded by another event.
   *
   * @var string
   */
  public string $disallowedSuccessorEvent = "Event '@elementId' cannot be succeeded by another event.";

  /**
   * The error message if a non-event element is succeeded by an event.
   *
   * @var string
   */
  public string $disallowedSuccessor = "@type '@elementId' cannot be succeeded by an event.";

}
