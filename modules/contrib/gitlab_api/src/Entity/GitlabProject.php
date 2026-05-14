<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * GitLab Project config entity.
 *
 * @ConfigEntityType(
 *   id = "gitlab_project",
 *   label = @Translation("GitLab Project"),
 *   label_collection = @Translation("GitLab Projects"),
 *   label_singular = @Translation("GitLab Project"),
 *   label_plural = @Translation("GitLab Projects"),
 *   label_count = @PluralTranslation(
 *     singular = "@count GitLab project",
 *     plural = "@count GitLab projects",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\gitlab_api\Entity\ListBuilder\GitlabProjectListBuilder",
 *     "form" = {
 *       "add" = "Drupal\gitlab_api\Form\GitlabProjectForm",
 *       "edit" = "Drupal\gitlab_api\Form\GitlabProjectForm",
 *       "delete" = "Drupal\gitlab_api\Form\GitlabProjectDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   config_prefix = "gitlab_project",
 *   admin_permission = "administer gitlab_project",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/gitlab-server/projects",
 *     "add-form" = "/admin/config/services/gitlab-server/projects/add",
 *     "edit-form" = "/admin/config/services/gitlab-server/projects/{gitlab_project}",
 *     "delete-form" = "/admin/config/services/gitlab-server/projects/{gitlab_project}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "server",
 *     "gitlab_project_id",
 *     "gitlab_project_path",
 *     "access_token_source",
 *     "access_token_key",
 *     "webhook_secret_keys",
 *     "maintainers",
 *     "status"
 *   }
 * )
 */
final class GitlabProject extends ConfigEntityBase implements GitlabProjectInterface {

  public const TOKEN_SOURCE_SERVER = 'server';
  public const TOKEN_SOURCE_KEY = 'key';

  /**
   * The project machine name.
   */
  protected string $id;

  /**
   * Human-readable label.
   */
  protected string $label = '';

  /**
   * The GitLab Server entity ID this project belongs to.
   */
  protected string $server = '';

  /**
   * Numeric GitLab project ID.
   */
  protected int $gitlab_project_id = 0;

  /**
   * GitLab project path with namespace (e.g. "group/project").
   */
  protected string $gitlab_project_path = '';

  /**
   * Where the access token comes from: 'server' (inherit) or 'key' (override).
   */
  protected string $access_token_source = self::TOKEN_SOURCE_SERVER;

  /**
   * Key entity ID used as the GitLab personal/project access token.
   */
  protected string $access_token_key = '';

  /**
   * Key entity IDs whose values are accepted as webhook secrets.
   *
   * @var string[]
   */
  protected array $webhook_secret_keys = [];

  /**
   * GitLab usernames of project maintainers.
   *
   * @var string[]
   */
  protected array $maintainers = [];

  /**
   * {@inheritdoc}
   */
  public function getServerId(): string {
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function getServer(): ?GitlabServer {
    if ($this->server === '') {
      return NULL;
    }
    return GitlabServer::load($this->server);
  }

  /**
   * {@inheritdoc}
   */
  public function getGitLabProjectId(): int {
    return $this->gitlab_project_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getGitLabProjectPath(): string {
    return $this->gitlab_project_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenSource(): string {
    return $this->access_token_source ?: self::TOKEN_SOURCE_SERVER;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenKeyId(): string {
    return $this->access_token_key;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveAccessToken(): string {
    if ($this->getAccessTokenSource() === self::TOKEN_SOURCE_KEY) {
      return $this->resolveKey($this->access_token_key);
    }
    $server = $this->getServer();
    return $server !== NULL ? (string) $server->getAuthToken() : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getWebhookSecretKeyIds(): array {
    return $this->webhook_secret_keys;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveWebhookSecrets(): array {
    return array_values(array_filter(array_map(
      fn (string $id) => $this->resolveKey($id),
      $this->webhook_secret_keys
    )));
  }

  /**
   * {@inheritdoc}
   */
  public function getMaintainers(): array {
    return $this->maintainers;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    parent::calculateDependencies();

    if ($server = $this->getServer()) {
      $this->addDependency('config', $server->getConfigDependencyName());
    }

    $keyStorage = \Drupal::entityTypeManager()->getStorage('key');
    if ($this->getAccessTokenSource() === self::TOKEN_SOURCE_KEY && $this->access_token_key !== '') {
      if ($key = $keyStorage->load($this->access_token_key)) {
        $this->addDependency('config', $key->getConfigDependencyName());
      }
    }
    foreach ($this->webhook_secret_keys as $keyId) {
      if ($keyId !== '' && ($key = $keyStorage->load($keyId))) {
        $this->addDependency('config', $key->getConfigDependencyName());
      }
    }

    return $this;
  }

  /**
   * Resolves a Key entity ID to its plaintext value.
   */
  private function resolveKey(string $id): string {
    if ($id === '') {
      return '';
    }
    /** @var \Drupal\key\KeyRepositoryInterface $repo */
    $repo = \Drupal::service('key.repository');
    $key = $repo->getKey($id);
    return $key ? (string) $key->getKeyValue() : '';
  }

}
