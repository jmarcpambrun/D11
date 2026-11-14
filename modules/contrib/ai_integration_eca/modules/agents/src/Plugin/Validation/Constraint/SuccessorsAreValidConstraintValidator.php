<?php

namespace Drupal\ai_integration_eca_agents\Plugin\Validation\Constraint;

use Drupal\ai_integration_eca_agents\EcaElementType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator of the SuccessorsAreValid-constraint.
 */
class SuccessorsAreValidConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof SuccessorsAreValidConstraint);

    $lookup = [
      EcaElementType::Event->getPlural() => array_flip(array_column($value['events'] ?? [], 'element_id')),
      EcaElementType::Condition->getPlural() => array_flip(array_column($value['conditions'] ?? [], 'element_id')),
      EcaElementType::Action->getPlural() => array_flip(array_column($value['actions'] ?? [], 'element_id')),
      EcaElementType::Gateway->getPlural() => array_flip(array_column($value['gateways'] ?? [], 'gateway_id')),
    ];

    foreach (EcaElementType::getPluralMap() as $plural => $type) {
      if (empty($value[$plural])) {
        continue;
      }

      foreach ($value[$plural] as $element) {
        $this->validateSuccessor($constraint, $element, $type, $lookup);
      }
    }
  }

  /**
   * Validate the successors of an element.
   *
   * @param \Drupal\ai_integration_eca_agents\Plugin\Validation\Constraint\SuccessorsAreValidConstraint $constraint
   *   The constraint.
   * @param array $element
   *   The element to validate.
   * @param \Drupal\ai_integration_eca_agents\EcaElementType $type
   *   The type of the element.
   * @param array $lookup
   *   The lookup-array containing all the referencable IDs.
   */
  protected function validateSuccessor(SuccessorsAreValidConstraint $constraint, array $element, EcaElementType $type, array $lookup): void {
    if (empty($element['successors'])) {
      return;
    }

    // A gateway is identified by "gateway_id", everything else by
    // "element_id". Never let this end up as NULL, because the violation
    // messages are rendered with FormattableMarkup, which only takes strings.
    $elementId = $element['element_id'] ?? $element['gateway_id'] ?? '(unnamed)';

    foreach ($element['successors'] as $successor) {
      $successorId = $successor['element_id'] ?? NULL;
      $conditionId = $successor['condition'] ?? NULL;

      // Check if the successor ID is valid (must be a gateway or an action).
      if (
        $successorId === NULL
        || (
          !isset($lookup[EcaElementType::Action->getPlural()][$successorId])
          && !isset($lookup[EcaElementType::Gateway->getPlural()][$successorId])
        )
      ) {
        $this->context->addViolation($constraint->invalidSuccessorMessage, [
          '@successorId' => $successorId ?? '(missing)',
          '@type' => $type->name,
          '@elementId' => $elementId,
        ]);
      }

      // Check if the condition ID is valid.
      if ($conditionId && !isset($lookup[EcaElementType::Condition->getPlural()][$conditionId])) {
        $this->context->addViolation($constraint->invalidConditionMessage, [
          '@conditionId' => $conditionId,
          '@type' => $type->name,
          '@elementId' => $elementId,
        ]);
      }

      // Check for disallowed successor relationships.
      if (
        ($type === EcaElementType::Action || $type === EcaElementType::Gateway)
        && isset($lookup[EcaElementType::Event->getPlural()][$successorId])
      ) {
        $this->context->addViolation($constraint->disallowedSuccessor, [
          '@type' => $type->name,
          '@elementId' => $elementId,
        ]);
      }
      if ($type === EcaElementType::Event && isset($lookup[EcaElementType::Event->getPlural()][$successorId])) {
        $this->context->addViolation($constraint->disallowedSuccessorEvent, [
          '@elementId' => $elementId,
        ]);
      }
    }
  }

}
