<?php

namespace Drupal\gitlab_api\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Entity\ConfigEntityBase;

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
 *     "label" = "url",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "url",
 *     "auth_token",
 *     "default_server"
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
   * The gitlab server url.
   *
   * @var string
   */
  protected string $url;

  /**
   * The gitlab_server auth_token.
   *
   * @var string
   */
  protected string $auth_token;

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
   *   The server URL.
   */
  public function getUrl(): string {
    return $this->get('url');
  }

  /**
   * Get the auth token.
   *
   * @return string|null
   *   The auth token or NULL if none is available.
   */
  public function getAuthToken(): ?string {
    return $this->get('auth_token');
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

}
