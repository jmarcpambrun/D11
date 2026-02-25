<?php

namespace Drupal\drd_pi_pantheon\Entity;

use Drupal\drd_agent\Agent\Action\Base as ActionBase;
use Drupal\drd_pi\DrdPiAccount;
use Drupal\drd_pi\DrdPiCore;
use Drupal\drd_pi\DrdPiDomain;
use Drupal\drd_pi\DrdPiHost;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Defines the Pantheon Account entity.
 *
 * @ConfigEntityType(
 *   id = "pantheon_account",
 *   label = @Translation("Pantheon Account"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\drd_pi\DrdPiAccountListBuilder",
 *     "form" = {
 *       "add" = "Drupal\drd_pi_pantheon\Entity\AccountForm",
 *       "edit" = "Drupal\drd_pi_pantheon\Entity\AccountForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "pantheon_account",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/drd/settings/pantheon/accounts/{pantheon_account}",
 *     "add-form" = "/drd/settings/pantheon/accounts/add",
 *     "edit-form" = "/drd/settings/pantheon/accounts/{pantheon_account}/edit",
 *     "delete-form" = "/drd/settings/pantheon/accounts/{pantheon_account}/delete",
 *     "collection" = "/drd/settings/pantheon/accounts"
 *   },
 *   config_export = {
 *     "status",
 *     "id",
 *     "label",
 *     "machine_token"
 *   }
 * )
 */
class Account extends DrdPiAccount implements AccountInterface {

  public const ENDPOINT     = 'https://terminus.pantheon.io:443/api/';
  public const CLIENT       = 'terminus';
  public const USER_AGENT   = 'Terminus/1.6.0 (php_version=' . PHP_VERSION . '&script=bin/terminus';
  public const CONTENT_TYPE = 'application/json';

  /**
   * Status if account has been authenticated.
   *
   * @var bool
   */
  private bool $authenticated = FALSE;

  /**
   * Stores the authentication object.
   *
   * @var object
   */
  private object $authentication;

  /**
   * {@inheritdoc}
   */
  public function getEncryptedFieldNames(): array {
    return [
      'machine_token',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getModuleName(): string {
    return 'drd_pi_pantheon';
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfigName(): string {
    return self::getModuleName() . '.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformName(): string {
    return 'Pantheon';
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineToken(): ?string {
    return $this->getDecrypted('machine_token');
  }

  /**
   * {@inheritdoc}
   */
  public function setMachineToken(string $machineToken): AccountInterface {
    $this->setEncrypted('machine_token', $machineToken);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationMethod(): string {
    return ActionBase::SEC_AUTH_PANTHEON;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationSecrets(DrdPiDomain $domain): array {
    return [
      'PANTHEON_SITE' => $domain->host()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformHosts(): array {
    $this->hosts = [];

    $this->auth();
    $result = $this->request('users/' . $this->authentication->user_id . '/memberships/sites');
    if ($result !== NULL) {
      foreach ($result as $item) {
        if (empty($item->site->frozen) && in_array($item->site->framework, [
          'drupal',
          'drupal8',
        ])) {
          $name = implode(' ', [
            $this->getPlatformName(),
            $this->label(),
            $item->site->name,
          ]);
          $this->hosts[$item->site->id] = new DrdPiHost($this, $name, $item->site->id);
        }
      }
    }
    return $this->hosts;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformCores(DrdPiHost $host): array {
    $this->cores = [];

    $sites = $this->request('sites/' . $host->id());
    $result = $this->request('sites/' . $host->id() . '/environments');
    if ($sites !== NULL && $result !== NULL) {
      foreach ($result as $id => $item) {
        if ($item->is_initialized) {
          $core = new DrdPiCore($this, $id, $id);
          $core->setHost($host);
          $domain = new DrdPiDomain($this, $id, $id);

          // Check if the domainn ame redirects to grab the real domain name.
          $domainname = "$id-$sites->name.$item->dns_zone";
          $client = $this->httpClientFactory->fromOptions(['base_uri' => 'https://' . $domainname]);
          try {
            $response = $client->request('HEAD', 'https://' . $domainname, ['allow_redirects' => FALSE]);
            $locaction = $response->getHeader('Location');
            if (!empty($locaction)) {
              $domainname = parse_url(array_pop($locaction), PHP_URL_HOST);
            }
          }
          catch (GuzzleException) {
            // @todo Log this exception.
          }

          $domain->setDetails($core, $domainname);
          $core->addDomain($domain);
          $this->cores[$id] = $core;
        }
      }
    }
    return $this->cores;
  }

  /**
   * Authenticate remotely if required.
   */
  private function auth(): void {
    if ($this->authenticated) {
      return;
    }
    $this->authenticated = TRUE;
    $this->authentication = $this->post(['machine_token' => $this->getMachineToken()]);
  }

  /**
   * Send a POST request with form values in $form.
   *
   *   API path to post to.
   *
   * @param array $form
   *   Array with form values to post.
   *
   * @return object|null
   *   Object with values from the response.
   */
  private function post(array $form): ?object {
    $form['client'] = self::CLIENT;
    try {
      return $this->request('authorize/machine-token', ['body' => json_encode($form, JSON_THROW_ON_ERROR)], 'POST', FALSE);
    }
    catch (\JsonException) {
      // @todo Log this exception.
    }
    return NULL;
  }

  /**
   * Send a request to the API endpoint.
   *
   * @param string $path
   *   API path to send the request to.
   * @param array $options
   *   Options for the request.
   * @param string $method
   *   Request method, GET or POST.
   * @param bool $auth_first
   *   Whether to authenticate first.
   *
   * @return object|null
   *   Object with values from the response.
   */
  private function request(string $path, array $options = [], string $method = 'GET', bool $auth_first = TRUE): ?object {
    if ($auth_first) {
      $this->auth();
    }
    $options['headers']['Content-type'] = self::CONTENT_TYPE;
    $options['headers']['User-Agent'] = self::USER_AGENT;
    if ($this->authenticated) {
      $options['headers']['Authorization'] = 'Bearer ' . $this->authentication->session;
    }
    $client = $this->httpClientFactory->fromOptions(['base_uri' => self::ENDPOINT . $path]);
    try {
      $response = $client->request($method, self::ENDPOINT . $path, $options);
      return json_decode($response->getBody()
        ->getContents(), FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException | GuzzleException) {
      // @todo Log this exception.
    }
    return NULL;
  }

}
