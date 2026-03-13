<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\HostInterface;

/**
 * Provides a 'DnsLookup' action.
 *
 * @Action(
 *  id = "drd_action_dnslookup",
 *  label = @Translation("DNS lookup for all domains"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_host",
 * )
 */
class DnsLookup extends BaseHost {

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
    if (!($entity instanceof HostInterface)) {
      return FALSE;
    }
    $entity->updateIpAddresses();
    return TRUE;
  }

}
