<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\CoreInterface;

/**
 * Provides a 'DomainsEnableAll' action.
 *
 * @Action(
 *  id = "drd_action_domains_enableall",
 *  label = @Translation("Enable all domains"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_core",
 * )
 */
class DomainsEnableAll extends BaseCoreRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof CoreInterface) || !($host = $entity->getHost())) {
      return FALSE;
    }

    // Get drush and drupalconsole settings from host.
    $drush = $host->getDrush();
    if (empty($drush)) {
      return FALSE;
    }
    $this->setActionArgument('drush', $drush);

    // Get all disabled domains from same core.
    $domains = $entity->getDomains(['installed' => 0]);
    if (empty($domains)) {
      return TRUE;
    }
    $urls = [];
    $local = [];
    foreach ($domains as $domain) {
      $url = $domain->buildUrl()->toString(TRUE)->getGeneratedUrl();
      $urls[$url] = $domain->getRemoteSetupToken(FALSE);
      $local[$url] = $domain;
    }
    $this->setActionArgument('urls', $urls);

    // Submit command to remote domain.
    $response = parent::executeAction($entity);
    if ($response) {
      foreach ($response as $url) {
        /** @var \Drupal\drd\Entity\DomainInterface $domain */
        $domain = $local[$url];
        $domain->set('installed', 1);
        /* @noinspection PhpUnhandledExceptionInspection */
        $domain->save();
        $domain->remoteInfo();
      }
      return TRUE;
    }
    return FALSE;
  }

}
