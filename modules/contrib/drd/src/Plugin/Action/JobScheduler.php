<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'JobScheduler' action.
 *
 * @Action(
 *  id = "drd_action_job_scheduler",
 *  label = @Translation("JobScheduler"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class JobScheduler extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  protected function getFollowUpAction(): string {
    return 'drd_action_info';
  }

}
