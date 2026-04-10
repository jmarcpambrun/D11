<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\CoreInterface;

/**
 * Provides a 'ProjectsUpdateLog' action.
 *
 * @Action(
 *  id = "drd_action_projects_update_log",
 *  label = @Translation("Show Logs of Update Projects"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_core",
 * )
 */
class ProjectsUpdateLog extends BaseCoreRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof CoreInterface)) {
      return FALSE;
    }
    $list = $entity->getUpdateLogList();
    if (empty($list)) {
      print('No logs available.' . PHP_EOL);
    }
    elseif ($this->arguments['list']) {
      /* @noinspection ForgottenDebugOutputInspection */
      print_r($list);
    }
    else {
      if (empty($this->arguments['id'])) {
        $item = array_pop($list);
      }
      elseif (isset($list[$this->arguments['id']])) {
        $item = $list[$this->arguments['id']];
      }
      else {
        print('Given id does not exist.' . PHP_EOL);
      }
      if (isset($item)) {
        print($entity->getUpdateLog($item['timestamp']));
      }
    }
    return TRUE;
  }

}
