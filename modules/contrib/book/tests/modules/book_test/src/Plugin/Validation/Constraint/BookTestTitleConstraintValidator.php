<?php

namespace Drupal\book_test\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for validating against the book node's title.
 */
class BookTestTitleConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Creates a new BookTestTitleConstraintValidator instance.
   */
  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    $invalid_title = $this->state->get('book_test.invalid_node_title');
    if (!$invalid_title) {
      return;
    }
    assert($constraint instanceof BookTestTitleConstraint);
    assert($entity instanceof NodeInterface);
    $title = $entity->getTitle();
    if ($invalid_title === $title) {
      // Add a field level violation.
      $this->context
        ->buildViolation($constraint->message, [
          '@title' => $title,
        ])
        ->atPath('title')
        ->setInvalidValue($entity)
        ->addViolation();
      if ($this->state->get('book_test.entity_level_violation')) {
        // Also add an entity level violation.
        $this->context->addViolation($constraint->entityLevelMessage, [
          '@title' => $title,
        ]);
      }
    }
  }

}
