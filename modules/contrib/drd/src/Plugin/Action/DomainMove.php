<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\Core;
use Drupal\drd\Entity\CoreInterface;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'DomainMove' action.
 *
 * @Action(
 *  id = "drd_action_domainmove",
 *  label = @Translation("Move domain to a different core"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class DomainMove extends BaseEntity {

  /**
   * The destination core to which the domain should be moved.
   *
   * @var \Drupal\drd\Entity\CoreInterface|null
   */
  protected ?CoreInterface $core = NULL;

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    if (!isset($this->core)) {
      $this->core = Core::load($this->arguments['dest-core-id']);
    }

    if ($this->core === NULL) {
      $this->setOutput('Give core id is not valid.');
      return FALSE;
    }

    /* @noinspection PhpUnhandledExceptionInspection */
    $entity
      ->setCore($this->core)
      ->save();

    $this->setOutput('Domain moved to core ' . $this->core->getName());
    return TRUE;
  }

}
