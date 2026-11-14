<?php

namespace Drupal\ai_integration_eca_agents;

use Drupal\Core\Config\ConfigException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * An exception thrown when violations against the schema are captured.
 */
class EntityViolationException extends ConfigException {

  /**
   * The constraint violations associated with this exception.
   *
   * @var \Symfony\Component\Validator\ConstraintViolationListInterface|null
   */
  protected ?ConstraintViolationListInterface $violations;

  /**
   * EntityViolationException constructor.
   *
   * @param string $message
   *   The Exception message to throw.
   * @param int $code
   *   The Exception code.
   * @param \Throwable|null $previous
   *   The previous throwable used for the exception chaining.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface|null $violations
   *   The constraint violations associated with this exception.
   */
  public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = NULL, ?ConstraintViolationListInterface $violations = NULL) {
    $this->violations = $violations;

    if (empty($message)) {
      $message = 'Validation of the entity failed.';
    }
    if ($violations !== NULL) {
      foreach ($violations as $violation) {
        $message .= PHP_EOL . $violation->getMessage();
      }
    }

    parent::__construct($message, $code, $previous);
  }

  /**
   * Gets the constraint violations associated with this exception.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface|null
   *   The constraint violations.
   */
  public function getViolations(): ?ConstraintViolationListInterface {
    return $this->violations;
  }

}
