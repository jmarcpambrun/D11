<?php

namespace Drupal\book_test\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint against the book title.
 */
#[Constraint(
  id: 'BookTestTitle',
  label: new TranslatableMarkup('Book test outline.', [], ['context' => 'Validation']),
  type: ['entity'],
)]
class BookTestTitleConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $message = 'An invalid book node title "@title" was used.';

  /**
   * The violation message to use for entity level violations.
   *
   * @var string
   */
  public string $entityLevelMessage = 'The book node is using an invalid title "@title".';

}
