<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface for GitLab Project config entities.
 */
interface GitlabProjectInterface extends ConfigEntityInterface {

  /**
   * Returns the GitLab Server config entity ID this project belongs to.
   */
  public function getServerId(): string;

  /**
   * Returns the GitLab Server config entity, if it exists.
   */
  public function getServer(): ?GitlabServer;

  /**
   * Returns the numeric GitLab project ID.
   */
  public function getGitLabProjectId(): int;

  /**
   * Returns the GitLab project path with namespace (e.g. "group/project").
   */
  public function getGitLabProjectPath(): string;

  /**
   * Returns the access-token source: 'server' (inherit) or 'key' (per project).
   */
  public function getAccessTokenSource(): string;

  /**
   * Returns the per-project access-token Key entity ID, if any.
   */
  public function getAccessTokenKeyId(): string;

  /**
   * Returns the resolved access token (server's auth_token or Key value).
   */
  public function resolveAccessToken(): string;

  /**
   * Returns the configured webhook secret Key entity IDs.
   *
   * @return string[]
   *   List of values, in declaration order.
   */
  public function getWebhookSecretKeyIds(): array;

  /**
   * Returns resolved webhook secret values (in declaration order).
   *
   * @return string[]
   *   List of values, in declaration order.
   */
  public function resolveWebhookSecrets(): array;

  /**
   * Returns the configured maintainer GitLab usernames.
   *
   * @return string[]
   *   List of values, in declaration order.
   */
  public function getMaintainers(): array;

}
