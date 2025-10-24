<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'DrdActionErrorLogs' action.
 *
 * @Action(
 *  id = "drd_action_error_logs",
 *  label = @Translation("Error Logs"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class ErrorLogs extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    if ($response !== FALSE) {
      if (!empty($response['php error log'])) {
        $entity->cacheErrorLog($response['php error log']);
      }
      return TRUE;
    }
    return FALSE;
  }

}
