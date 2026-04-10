<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\CoreInterface;

/**
 * Base class for DRD Remote Core Action plugins.
 */
abstract class BaseCoreRemote extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if ($entity === NULL) {
      return FALSE;
    }
    $domain = NULL;
    if ($entity instanceof CoreInterface) {
      $domain = $entity->getFirstActiveDomain();
    }
    if ($domain === NULL) {
      return FALSE;
    }

    return parent::executeAction($domain);
  }

}
