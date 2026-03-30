<?php

declare(strict_types=1);

namespace Drupal\book\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the ExistsIn constraint.
 */
class ExistsInConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof ExistsInConstraint) {
      throw new UnexpectedTypeException($constraint, ExistsInConstraint::class);
    }

    $this_property = $this->context->getObject();
    assert($this_property instanceof TypedDataInterface);
    $choices_raw = self::find($constraint->selector, $this_property)->getValue();

    $allowed_types = [];
    foreach ($choices_raw as $choice) {
      $allowed_types[] = $choice['content_type'];
    }

    if (!in_array($value, $allowed_types)) {
      $this->context->addViolation($constraint->message, [
        '@child_type' => $value,
      ]);
    }
  }

  /**
   * Resolves an expression that selects other typed data in the same root.
   *
   * The expression may contain the following special strings:
   * - '%root', to reference the root of the array.
   *
   * Example expressions:
   * - '%root.allowed_types', indicators the key to check.
   *
   * @param string $expression
   *   Expression to be resolved.
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   Configuration data for the element.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The selected typed data elsewhere in the same config object.
   *
   * @see \Drupal\Core\Config\Schema\TypeResolver::resolveExpression()
   */
  private static function find(string $expression, TypedDataInterface $data): TypedDataInterface {
    $parts = explode('.', $expression);
    // Process each value part, one at a time.
    while ($name = array_shift($parts)) {

      if ($name === '%root') {
        $data = $data->getRoot();
        continue;
      }

      if (!$data instanceof ArrayElement) {
        $data = $data->getRoot();
        if (!$data instanceof ArrayElement) {
          throw new \LogicException(sprintf('This is not an array element, so cannot get `%s`.', $name));
        }
      }

      $elements = $data->getElements();
      if (!array_key_exists($name, $elements)) {
        throw new \LogicException(sprintf('`%s` does not exist at `%s`.', $name, $data->getPropertyPath()));
      }

      $data = $elements[$name];
    }

    return $data;
  }

}
