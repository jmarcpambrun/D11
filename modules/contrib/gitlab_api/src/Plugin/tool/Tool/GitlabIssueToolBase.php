<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\gitlab_api\Entity\GitlabProject;
use Drupal\gitlab_api\Entity\GitlabProjectInterface;
use Drupal\gitlab_api\Service\GitLabApiClientFactory;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared plumbing for the gitlab_api Tool plugins.
 *
 * Subclasses receive the Gitlab\Client factory and a project-resolution helper,
 * and inherit a single permission gate (`use gitlab_api tools`).
 */
abstract class GitlabIssueToolBase extends ToolBase {

  /**
   * GitLab REST/GraphQL client factory.
   */
  protected GitLabApiClientFactory $clientFactory;

  /**
   * Logger for the gitlab_api channel.
   */
  protected LoggerInterface $gitlabLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->clientFactory = $container->get('gitlab_api.client_factory');
    $instance->gitlabLogger = $container->get('logger.channel.gitlab_api');
    return $instance;
  }

  /**
   * Resolves the configured project ID to a GitlabProject entity.
   */
  protected function resolveProject(string $id): ?GitlabProjectInterface {
    $id = trim($id);
    if ($id === '') {
      return NULL;
    }
    return GitlabProject::load($id);
  }

  /**
   * Logs an error and returns a matching ExecutableResult::failure().
   *
   * The caller is expected to pass a literal placeholdered template (so the
   * translation extractor can find it) and an args array. The same template
   * is used for both the dblog error line and the user-facing failure message.
   */
  protected function failWithLog(string $template, array $args = []): ExecutableResult {
    $this->gitlabLogger->error($template, $args);
    // Template strings are always literals at the call site, so the
    // translation extractor still finds them via the per-tool source.
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return ExecutableResult::failure(new TranslatableMarkup($template, $args));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'use gitlab_api tools');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
