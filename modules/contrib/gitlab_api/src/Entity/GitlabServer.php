<?php

namespace Drupal\gitlab_api\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the gitlab server entity type.
 *
 * @ConfigEntityType(
 *   id = "gitlab_server",
 *   label = @Translation("Gitlab Server"),
 *   label_collection = @Translation("Gitlab Servers"),
 *   label_singular = @Translation("gitlab server"),
 *   label_plural = @Translation("gitlab servers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count gitlab server",
 *     plural = "@count gitlab servers",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\gitlab_api\Entity\ListBuilder\GitlabServerListBuilder",
 *     "form" = {
 *       "add" = "Drupal\gitlab_api\Form\GitlabServerForm",
 *       "edit" = "Drupal\gitlab_api\Form\GitlabServerForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "gitlab_server",
 *   admin_permission = "administer gitlab_server",
 *   links = {
 *     "collection" = "/admin/config/services/gitlab-server",
 *     "add-form" = "/admin/config/services/gitlab-server/add",
 *     "edit-form" = "/admin/config/services/gitlab-server/{gitlab_server}",
 *     "delete-form" = "/admin/config/services/gitlab-server/{gitlab_server}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "url",
 *     "auth_token_key",
 *     "default_server",
 *     "status"
 *   }
 * )
 */
class GitlabServer extends ConfigEntityBase {

  /**
   * The gitlab server ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * Human-readable label.
   *
   * @var string
   */
  protected string $label = '';

  /**
   * The gitlab server url.
   *
   * @var string
   */
  protected string $url;

  /**
   * Key entity ID holding the GitLab authentication token.
   *
   * @var string
   */
  protected string $auth_token_key = '';

  /**
   * Is it the default server.
   *
   * @var bool
   */
  protected bool $default_server;

  /**
   * Get the default GitLab server.
   *
   * @return \Drupal\gitlab_api\Entity\GitlabServer|null
   *   The default GitLab server config entity or NULL, if it doesn't exist.
   */
  public static function loadDefaultServer(): ?GitlabServer {
    try {
      /** @var \Drupal\gitlab_api\Entity\GitlabServer[] $servers */
      $servers = \Drupal::entityTypeManager()
        ->getStorage('gitlab_server')
        ->loadByProperties(['default_server' => TRUE]);
      $server = reset($servers);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $server = FALSE;
    }
    return $server ?: NULL;
  }

  /**
   * Get the server URL.
   *
   * @return string
   *   The server URL, with no trailing slash.
   */
  public function getUrl(): string {
    return rtrim((string) $this->get('url'), '/');
  }

  /**
   * Alias for getUrl().
   */
  public function getBaseUrl(): string {
    return $this->getUrl();
  }

  /**
   * Returns the Key entity ID holding the auth token.
   */
  public function getAuthTokenKeyId(): string {
    return (string) $this->get('auth_token_key');
  }

  /**
   * Resolves the Key entity to its plaintext token value.
   *
   * @return string|null
   *   The auth token, or NULL when no key is configured / resolvable.
   */
  public function getAuthToken(): ?string {
    $id = $this->getAuthTokenKeyId();
    if ($id === '') {
      return NULL;
    }
    /** @var \Drupal\key\KeyRepositoryInterface $repo */
    $repo = \Drupal::service('key.repository');
    $key = $repo->getKey($id);
    return $key ? (string) $key->getKeyValue() : NULL;
  }

  /**
   * Determines if this server is the default one.
   *
   * @return bool
   *   TRUE, if this server is marked as default, FALSE otherwise.
   */
  public function isDefault(): bool {
    if ($this->isNew() && !self::loadDefaultServer()) {
      return TRUE;
    }
    return (bool) $this->get('default_server');
  }

  /**
   * Make this server the default one.
   *
   * @param bool $isDefault
   *   Flag to tell, if this server should be default or not.
   */
  public function setDefault(bool $isDefault = TRUE): GitlabServer {
    $this->set('default_server', $isDefault);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    $this->url = rtrim((string) $this->url, '/');
    if ($this->label === '') {
      $this->label = $this->url;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    parent::calculateDependencies();

    $keyId = $this->getAuthTokenKeyId();
    if ($keyId !== '') {
      $key = \Drupal::entityTypeManager()->getStorage('key')->load($keyId);
      if ($key !== NULL) {
        $this->addDependency('config', $key->getConfigDependencyName());
      }
    }

    return $this;
  }

}
