<?php

namespace Drupal\gitlab_api\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Dispatches after the Webform handler created a new project.
 *
 * @package Drupal\gitlab_api\Event
 */
class CreateProject extends Event {

  /**
   * The webform submission entity.
   *
   * @var \Drupal\webform\WebformSubmissionInterface
   */
  protected WebformSubmissionInterface $webformSubmission;

  /**
   * The created project from the GitLab API.
   *
   * @var array
   */
  protected array $project;

  /**
   * CreateProject Event constructor.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission entity.
   * @param array $project
   *   The created project from the GitLab API.
   */
  public function __construct(WebformSubmissionInterface $webform_submission, array $project) {
    $this->webformSubmission = $webform_submission;
    $this->project = $project;
  }

  /**
   * Gets the webform submission entity.
   *
   * @return \Drupal\webform\WebformSubmissionInterface
   *   The webform submission entity.
   */
  public function getWebformSubmission(): WebformSubmissionInterface {
    return $this->webformSubmission;
  }

  /**
   * Gets the created project from the GitLab API.
   *
   * @return array
   *   The created project from the GitLab API.
   */
  public function getProject(): array {
    return $this->project;
  }

}
