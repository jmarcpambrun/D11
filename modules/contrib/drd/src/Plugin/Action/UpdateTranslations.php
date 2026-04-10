<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'UpdateTranslations' action.
 *
 * @Action(
 *  id = "drd_action_update_translations",
 *  label = @Translation("Update Translations"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class UpdateTranslations extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  protected function getFollowUpAction(): string {
    return 'drd_action_info';
  }

}
