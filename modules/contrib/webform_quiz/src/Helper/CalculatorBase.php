<?php

namespace Drupal\webform_quiz\Helper;

use Drupal\webform\Entity\WebformSubmission;

/**
 * Calculator base abstract class.
 */
abstract class CalculatorBase {

  /**
   * WebformSubmission object.
   *
   * @var \Drupal\webform\Entity\WebformSubmission
   */
  protected $webformSubmission;

  /**
   * {@inheritdoc}
   */
  protected $results;

  /**
   * ScoreCalculator constructor.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $webformSubmission
   *   The Webform submission entity class.
   */
  public function __construct(WebformSubmission $webformSubmission) {
    $this->webformSubmission = $webformSubmission;
    $this->calculate();
  }

  /**
   * {@inheritdoc}
   */
  abstract protected function calculate();

  /**
   * {@inheritdoc}
   */
  public function getResults() {
    return $this->results;
  }

}
