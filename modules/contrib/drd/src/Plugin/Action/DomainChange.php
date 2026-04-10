<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\Domain;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'DomainChange' action.
 *
 * @Action(
 *  id = "drd_action_domainchange",
 *  label = @Translation("Change domain, protocol and port of a domain"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class DomainChange extends BaseEntity {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    if (isset($this->arguments['secure'])) {
      $entity->set('secure', $this->arguments['secure']);
    }
    if (isset($this->arguments['port'])) {
      $entity->set('port', $this->arguments['port']);
    }
    if (isset($this->arguments['newdomain'])) {
      $domainname = trim($this->arguments['newdomain'], '/');
      $entity->set('domain', $domainname);
      try {
        $existing = Domain::instanceFromUrl($entity->getCore(), $entity->buildUrl()
          ->toString(TRUE)
          ->getGeneratedUrl(), []);
      }
      catch (\Exception $e) {
        return FALSE;
      }
      if (!$existing->isNew()) {
        $this->setOutput('Another domain already exists.');
        return FALSE;
      }
    }

    $url = $entity->buildUrl()->toString(TRUE)->getGeneratedUrl();
    if ($this->arguments['force'] || $entity->ping()) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $entity->save();
      $this->setOutput('Domain changed to ' . $url);
      return TRUE;
    }

    $this->setOutput('Domain does not respond: ' . $url);
    return FALSE;
  }

}
