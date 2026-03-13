<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\CoreInterface;
use Drupal\drd\Entity\Domain;

/**
 * Provides a 'DomainsReceive' action.
 *
 * @Action(
 *  id = "drd_action_domains_receive",
 *  label = @Translation("Receive domains"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_core",
 * )
 */
class DomainsReceive extends BaseCoreRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof CoreInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    if (!$response) {
      return FALSE;
    }

    $known_domains = $entity->getDomains();

    /** @var \Drupal\drd\Entity\DomainInterface $domain */
    $domain = $this->drdEntity;
    $crypt = $domain->getCrypt();
    $crypt_setting = $domain->getCryptSetting();

    $newDomains = 0;
    foreach ($response as $shortname => $item) {
      // Find out scheme.
      $url = 'https://' . $item['uri'];
      $location = '';
      try {
        $client = $this->httpRequest->getHttpClientFactory()->fromOptions();
        $response = $client->head($url, ['allow_redirects' => FALSE]);
        $status_code = $response->getStatusCode();
        if ($status_code === 301) {
          $location = $response->getHeaderLine('location');
        }
      }
      catch (\Exception $ex) {
        $status_code = $ex->getCode();
      }
      /* @noinspection PhpStatementHasEmptyBodyInspection */
      /* @noinspection MissingOrEmptyGroupStatementInspection */
      if (($status_code >= 200 && $status_code < 300) || $status_code === 401) {
        // This seems OK.
        // Note: 401 will be received for urls with basic auth.
      }
      elseif ($status_code === 301) {
        $exists = FALSE;
        foreach ($known_domains as $known_domain) {
          $existingUrl = $known_domain->buildUrl()->toString();
          if ($existingUrl === $location) {
            $url = $existingUrl;
            $exists = TRUE;
            break;
          }
        }
        if (!$exists) {
          $url = $location;
        }
      }
      else {
        // Lets use http instead.
        $url = 'http://' . $item['uri'];
      }

      try {
        $d = Domain::instanceFromUrl($entity, $url, []);
      }
      catch (\Exception $e) {
        continue;
      }
      if ($d->isNew()) {
        if ($d->ping()) {
          $d->remoteInfo();
        }
        else {
          // Leave name of domain empty such that we can see that it is not
          // enabled yet.
          $d->initValues('', $crypt, $crypt_setting);
          $newDomains++;
        }
      }
      else {
        // Remove this domain from the list of $known_domains.
        foreach ($known_domains as $key => $known_domain) {
          if ($known_domain->id() === $d->id()) {
            unset($known_domains[$key]);
            break;
          }
        }
      }
      /* @noinspection PhpUnhandledExceptionInspection */
      $d
        ->setAliase($item['aliase'])
        ->updateScheme($url)
        ->save();
    }

    // Delete domains that no longer exist.
    foreach ($known_domains as $known_domain) {
      if ($known_domain->id() === $domain->id()) {
        // Do not delete the current domain, it's probably the first one which
        // was used to create the host but isn't included in the remote
        // sites.php, @see https://www.drupal.org/node/2840203
        continue;
      }
      /* @noinspection PhpUnhandledExceptionInspection */
      $known_domain->delete();
    }

    // Make sure all new domains are enabled as well.
    if ($newDomains) {
      $this->actionManager->response('drd_action_domains_enableall', $entity);
    }

    return TRUE;
  }

}
