<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'Update' action.
 *
 * @Action(
 *  id = "drd_action_update",
 *  label = @Translation("Run update.php"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Update extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  protected function getFollowUpAction(): string {
    return 'drd_action_info';
  }

}
