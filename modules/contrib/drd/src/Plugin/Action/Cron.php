<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'Cron' action.
 *
 * @Action(
 *  id = "drd_action_cron",
 *  label = @Translation("Cron"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Cron extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  protected function getFollowUpAction(): array|string|bool {
    return 'drd_action_info';
  }

}
