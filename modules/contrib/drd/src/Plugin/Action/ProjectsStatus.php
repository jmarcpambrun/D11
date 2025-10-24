<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'ProjectStatus' action.
 *
 * @Action(
 *  id = "drd_action_projects_status",
 *  label = @Translation("Check status for all projects"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd",
 * )
 */
class ProjectsStatus extends BaseGlobal {

  /**
   * {@inheritdoc}
   */
  public function executeAction(): mixed {
    try {
      $this->updateProcessor->fetchData();
    }
    catch (\Exception $ex) {
      return FALSE;
    }
    return TRUE;
  }

}
