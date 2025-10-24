<?php

namespace Drupal\webform_quiz\Model;

use Drupal\webform\WebformInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Webform submission helper class.
 */
class WebformSubmissionsHelper {

  /**
   * Webform entity object.
   *
   * @var \Drupal\webform\Entity\Webform
   *   The Webform entity object.
   */
  protected $webform;

  /**
   * WebformSubmissions constructor.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The WebformInterface object.
   */
  public function __construct(WebformInterface $webform) {
    $this->webform = $webform;
  }

  /**
   * {@inheritdoc}
   */
  public function loadWebformSubmissions() {
    // @todo perform efq on webform submissions.
    $eq = \Drupal::entityQuery('webform_submission')
      ->condition('webform_id', $this->webform->id())
      ->accessCheck(FALSE);

    $ids = $eq->execute();
    $webform_submissions = WebformSubmission::loadMultiple($ids);

    return $webform_submissions;
  }

}
