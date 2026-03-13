<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'Ping' action.
 *
 * @Action(
 *  id = "drd_action_ping",
 *  label = @Translation("Ping"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Ping extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function restrictAccess(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    $result = ($response && $response['data'] === 'pong');
    if (!empty($response['data'])) {
      $this->setOutput($response['data']);
    }
    if ($result !== $entity->getLatestPingStatus(FALSE)) {
      $entity->cachePingResult($result);
    }

    $entity->set('installed', $result);
    if (!empty($this->arguments['save'])) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $entity->save();
    }
    return $result;
  }

}
