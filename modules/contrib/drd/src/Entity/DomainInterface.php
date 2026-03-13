<?php

namespace Drupal\drd\Entity;

use Drupal\Core\GeneratedLink;
use Drupal\Core\Url;
use Drupal\drd\EncryptionEntityInterface;

/**
 * Provides an interface for defining Domain entities.
 *
 * @ingroup drd
 */
interface DomainInterface extends BaseInterface, EncryptionEntityInterface {

  /**
   * Get the cached cookies to be included into the next request.
   *
   * @return array
   *   The cached cookies.
   */
  public function getCookies(): array;

  /**
   * Cache cookies that have been received from a request.
   *
   * @param array $cookies
   *   The cookies.
   *
   * @return $this
   */
  public function setCookies(array $cookies): DomainInterface;

  /**
   * Get language code of the domain.
   *
   * @return string
   *   Language code.
   */
  public function getLangCode(): string;

  /**
   * Gets the Domain's domain name.
   *
   * @return string
   *   The Domain's domain name.
   */
  public function getDomainName(): string;

  /**
   * Gets the Domain core.
   *
   * @return \Drupal\drd\Entity\CoreInterface|null
   *   Core of the Domain.
   */
  public function getCore(): ?CoreInterface;

  /**
   * Sets the Domain core.
   *
   * @param CoreInterface $core
   *   The Domain core.
   *
   * @return $this
   */
  public function setCore(CoreInterface $core): DomainInterface;

  /**
   * Get the domain's selected authentication type.
   *
   * @return string
   *   The selected authentication type.
   */
  public function getAuth(): string;

  /**
   * Get the domain's authentication settings.
   *
   * @param string|null $type
   *   The authentication type.
   * @param bool $for_remote
   *   Indicates if the auth settings should be received for the remote site.
   *
   * @return array
   *   The authentication settings.
   */
  public function getAuthSetting(?string $type = NULL, bool $for_remote = FALSE): array;

  /**
   * Set the domain's authentication settings.
   *
   * @param array $settings
   *   The authentication settings.
   *
   * @return $this
   */
  public function setAuthSetting(array $settings): DomainInterface;

  /**
   * Get the domain's selected crypt method.
   *
   * @return string
   *   The selected crypt method.
   */
  public function getCrypt(): string;

  /**
   * Get the domain's crypt settings.
   *
   * @param string|null $type
   *   The type of encryption.
   *
   * @return array
   *   The crypt settings.
   */
  public function getCryptSetting(?string $type = NULL): array;

  /**
   * Set the domain's crypt settings.
   *
   * @param array $settings
   *   The crypt settings.
   * @param bool $encrypted
   *   Whether the data is already encrypted or not.
   *
   * @return $this
   */
  public function setCryptSetting(array $settings, bool $encrypted = FALSE): DomainInterface;

  /**
   * Create a domain entity instance by checking if one already exists.
   *
   * @param CoreInterface $core
   *   The core entity to which the domain is attached.
   * @param string $uri
   *   The URI of the domain.
   * @param array $values
   *   Extra values for the entity.
   *
   * @return \Drupal\drd\Entity\Domain
   *   The domain entity for the given url.
   *
   * @throws \Exception
   */
  public static function instanceFromUrl(CoreInterface $core, string $uri, array $values): Domain;

  /**
   * Build the URL for a remote request.
   *
   * @param string $query
   *   The query of the remote request.
   *
   * @return \Drupal\Core\Url
   *   Fully setup URL object.
   */
  public function buildUrl(string $query = ''): Url;

  /**
   * Determine the supported crypt methods for a remote domain.
   *
   * @param bool $cleanUrl
   *   Setting on how to build the URL, either with or without clean URLs.
   *
   * @return mixed
   *   - FALSE if we can't connect to the given domain
   *   - NULL if we were able to connect but without the expected DRD result
   *   - the decoded response from the remote DRD
   */
  public function getSupportedCryptMethods(bool $cleanUrl = TRUE): mixed;

  /**
   * Authorize DRD instance remotely by using server secrets.
   *
   * @param string $method
   *   Name of the authorization method.
   * @param array $secrets
   *   List of secrets.
   *
   * @return bool
   *   Whether the authorization succeeded.
   */
  public function authorizeBySecret(string $method, array $secrets): bool;

  /**
   * Push a one-time-token to a remote domain.
   *
   * @param string $token
   *   The one-time-token.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function pushOtt(string $token): bool;

  /**
   * Returns if domain's agent is installed.
   *
   * @return bool
   *   TRUE if the agent is installed.
   */
  public function isInstalled(): bool;

  /**
   * Find out if domain uses the default port.
   *
   * @return bool
   *   TRUE if the default port (80 for http or 443 for https) is used.
   */
  public function isDefaultPort(): bool;

  /**
   * Get a token which contains all details for configuring a remote domain.
   *
   * @param bool|string $redirect
   *   FALSE if the remote domain should not redirect after configuration has
   *   been completed, e.g. if Drush is being used. Otherwise provide a URL to
   *   which the remote domain should redirect when configuration has been
   *   completed.
   *
   * @return string
   *   The configuration token.
   */
  public function getRemoteSetupToken(bool|string $redirect): string;

  /**
   * Create a renderable link to initiate a remote user session.
   *
   * @param string $label
   *   The label for the link.
   *
   * @return \Drupal\Core\GeneratedLink
   *   The rendered link.
   */
  public function getRemoteLoginLink(string $label): GeneratedLink;

  /**
   * Get a URL which the remote domain should redirect to after configuration.
   *
   * @param bool $initial
   *   Whether this is the initial setup or not.
   *
   * @return \Drupal\Core\Url
   *   The redirect URL after remote configuration.
   */
  public function getRemoteSetupRedirect(bool $initial = FALSE): Url;

  /**
   * Get a rendered link to get to the remote configuration form.
   *
   * @param string $label
   *   The label to show in the link.
   * @param bool $initial
   *   Whether this is the initial setup or not.
   *
   * @return \Drupal\Core\GeneratedLink
   *   The URL to get to the remote configuration form.
   */
  public function getRemoteSetupLink(string $label, bool $initial = FALSE): GeneratedLink;

  /**
   * Get a local domain name used temporarily for updates.
   *
   * @return string
   *   A temporary domain name.
   */
  public function getLocalUrl(): string;

  /**
   * Call the session action to receive a URL which starts a new remote session.
   *
   * @return string
   *   The session URL.
   */
  public function getSessionUrl(): string;

  /**
   * Received the remote database.
   *
   * @return array
   *   List of database definitions.
   */
  public function database(): array;

  /**
   * Download a remote file.
   *
   * @param string $source
   *   Remote filename.
   * @param string $destination
   *   Local filename.
   *
   * @return bool
   *   TRUE if operation was successful.
   */
  public function download(string $source, string $destination): bool;

  /**
   * Ping the remote domain.
   *
   * @return bool
   *   TRUE if operation was successful.
   */
  public function ping(): bool;

  /**
   * Receive and store remote information.
   *
   * @return array|bool
   *   Array of received information.
   */
  public function remoteInfo(): bool|array;

  /**
   * Initialize core for a new domain.
   *
   * @param CoreInterface $core
   *   The core to be initialized.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function initCore(CoreInterface $core): bool;

  /**
   * Initialize authentication and crypt settings with default values.
   *
   * @param string $name
   *   Name of the domain.
   * @param string|null $crypt
   *   Crypt method.
   * @param array $crypt_setting
   *   Crypt settings.
   */
  public function initValues(string $name, ?string $crypt = NULL, array $crypt_setting = []): void;

  /**
   * Callback to retrieve all domains from the remote Drupal installation.
   *
   * @param CoreInterface $core
   *   Retrieve all domains for the given core where the current domain entity
   *   is also allocated to that same core.
   */
  public function retrieveAllDomains(CoreInterface $core): void;

  /**
   * Set domain aliases.
   *
   * @param array $aliase
   *   List of domain aliases.
   *
   * @return $this
   */
  public function setAliase(array $aliase): DomainInterface;

  /**
   * Recheck the URL to get SSL setting and port.
   *
   * @param string $url
   *   URL to check.
   *
   * @return $this
   */
  public function updateScheme(string $url): DomainInterface;

  /**
   * Set project releases being used by this domain.
   *
   * @param \Drupal\drd\Entity\ReleaseInterface[] $releases
   *   List of release entities.
   *
   * @return $this
   */
  public function setReleases(array $releases): DomainInterface;

  /**
   * Get project releases being used by this domain.
   *
   * @return \Drupal\drd\Entity\ReleaseInterface[]
   *   List of releases.
   */
  public function getReleases(): array;

  /**
   * Get messages being created during remote actions.
   *
   * @return array
   *   List of messages.
   */
  public function getMessages(): array;

  /**
   * Get the latest security review.
   *
   * @return array
   *   Renderable array of latest security review.
   */
  public function getReview(): array;

  /**
   * Get the latest monitoring review.
   *
   * @return array
   *   Json array of monitoring review.
   */
  public function getMonitoring(): array;

  /**
   * Get the latest query results from cache.
   *
   * @return array
   *   Contains the keys 'query', 'info', 'headers' and 'rows' which can be
   *   rendered as a table or any other sort of output.
   */
  public function getQueryResult(): array;

  /**
   * Get the current maintenance mode.
   *
   * @param bool $refresh
   *   Whether to refresh status from remote.
   *
   * @return bool
   *   TRUE if maintenance mode is turned on.
   */
  public function getMaintenanceMode(bool $refresh = TRUE): bool;

  /**
   * Get latest Ping status.
   *
   * @param bool $refresh
   *   Whether to refresh status from remote.
   *
   * @return bool|null
   *   TRUE if the remote domain responds properly, FALSE if it doesn't and NULL
   *   if we don't get any response and hence don't know the status.
   */
  public function getLatestPingStatus(bool $refresh = TRUE): ?bool;

  /**
   * Get a remote block.
   *
   * @param string $module
   *   Module from which to receive a block.
   * @param string $delta
   *   ID of the block within that module.
   * @param bool $refresh
   *   Whether to refresh status from remote.
   *
   * @return bool|string
   *   FALSE if we couldn't receive the block or the rendered result as a string
   *   if it's been successful.
   */
  public function getRemoteBlock(string $module, string $delta, bool $refresh = TRUE): bool|string;

  /**
   * Get all remote settings.
   *
   * @return array
   *   List of all remote settings.
   */
  public function getRemoteSettings(): array;

  /**
   * Get all remote GLOBAL variables.
   *
   * @return array
   *   List of all remote GLOBALS.
   */
  public function getRemoteGlobals(): array;

  /**
   * Get remote requirements - in other words the status report.
   *
   * @return array
   *   Renderable status report from remote domain.
   */
  public function getRemoteRequirements(): array;

  /**
   * Store remote messages in cache.
   *
   * @param array $messages
   *   List of remote messages.
   *
   * @return $this
   */
  public function cacheRemoteMessages(array $messages): DomainInterface;

  /**
   * Store remote maintenance mode status in cache.
   *
   * @param bool $mode
   *   The current state.
   *
   * @return $this
   */
  public function cacheMaintenanceMode(bool $mode): DomainInterface;

  /**
   * Store remote block in cache.
   *
   * @param string $module
   *   The module that provides that block.
   * @param string $delta
   *   The ID of the block within that module.
   * @param string $content
   *   The rendered content of the block.
   *
   * @return $this
   */
  public function cacheBlock(string $module, string $delta, string $content): DomainInterface;

  /**
   * Cache the ping results.
   *
   * @param bool|null $status
   *   The current ping response status. TRUE or FALSE if the domain responds
   *   OK or not and NULL if it doesn't respond at all.
   *
   * @return $this
   */
  public function cachePingResult(?bool $status): DomainInterface;

  /**
   * Store remote error log in cache.
   *
   * @param string $log
   *   The error log.
   *
   * @return $this
   */
  public function cacheErrorLog(string $log): DomainInterface;

  /**
   * Store remote requirements (status report) in cache.
   *
   * @param array $requirements
   *   Renderable status report.
   *
   * @return $this
   */
  public function cacheRequirements(array $requirements): DomainInterface;

  /**
   * Store remote variables in cache.
   *
   * @param array $variables
   *   List of remote variables.
   *
   * @return $this
   */
  public function cacheVariables(array $variables): DomainInterface;

  /**
   * Store remote GLOBALS in cache.
   *
   * @param array $globals
   *   List of remote GLOBALS.
   *
   * @return $this
   */
  public function cacheGlobals(array $globals): DomainInterface;

  /**
   * Store remote settings in cache.
   *
   * @param array $settings
   *   List of remote settings.
   *
   * @return $this
   */
  public function cacheSettings(array $settings): DomainInterface;

  /**
   * Store remote security review in cache.
   *
   * @param array $review
   *   Renderable security review results.
   *
   * @return $this
   */
  public function cacheReview(array $review): DomainInterface;

  /**
   * Store remote monitoring in cache.
   *
   * @param array $monitoring
   *   Json formatted monitoring results.
   *
   * @return $this
   */
  public function cacheMonitoring(array $monitoring): DomainInterface;

  /**
   * Store remote SQL query and result in cache.
   *
   * @param string $query
   *   The query that has been executed.
   * @param string $info
   *   Some information about the query result, e.g. "Nothing found".
   * @param array $headers
   *   The column headers for the results.
   * @param array $rows
   *   The query results as an array containing a result array for each row.
   *
   * @return $this
   */
  public function cacheQueryResult(string $query, string $info, array $headers, array $rows): DomainInterface;

  /**
   * Reset crypt settings.
   *
   * Set Crypt method and Cipher to the strongest available values and generate
   * a new key for that new crypto setting.
   *
   * @return $this
   */
  public function resetCryptSettings(): DomainInterface;

  /**
   * Reset domain.
   *
   * Reset the domain so that new authorization with remote site gets available
   * in the UI again.
   *
   * @return $this
   */
  public function reset(): DomainInterface;

  /**
   * Render Ping status.
   *
   * @return string
   *   Rendered string to indicate the ping status of the domain.
   */
  public function renderPingStatus(): string;

}
