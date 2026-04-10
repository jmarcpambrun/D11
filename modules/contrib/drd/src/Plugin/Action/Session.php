<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'Session' action.
 *
 * @Action(
 *  id = "drd_action_session",
 *  label = @Translation("Get a session URL"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Session extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    if (!empty($response['url'])) {
      $this->setOutput($response['url']);
      return $response['url'];
    }
    return FALSE;
  }

}
