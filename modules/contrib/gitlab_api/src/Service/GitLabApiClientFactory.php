<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Service;

use Drupal\gitlab_api\Entity\GitlabProjectInterface;
use Gitlab\Client;

/**
 * Builds a fresh, project-configured GitLab API client.
 *
 * Bearer auth (`Authorization: Bearer <token>`) is used instead of
 * PRIVATE-TOKEN. PATs work for both REST and GraphQL via Bearer, but GitLab
 * rejects PRIVATE-TOKEN for some GraphQL mutations (notably workItemUpdate
 * with statusWidget). Bearer is the safe default.
 */
final class GitLabApiClientFactory {

  /**
   * Returns an authenticated REST client for the given project.
   */
  public function forProject(GitlabProjectInterface $project): Client {
    $server = $project->getServer();
    $baseUrl = $server !== NULL ? $server->getBaseUrl() : '';
    $client = new Client();
    if ($baseUrl !== '') {
      $client->setUrl($baseUrl);
    }
    $client->authenticate($project->resolveAccessToken(), Client::AUTH_OAUTH_TOKEN);
    return $client;
  }

  /**
   * Returns a GraphQL client backed by the same auth as forProject().
   */
  public function graphqlForProject(GitlabProjectInterface $project): GitLabGraphQLClient {
    return new GitLabGraphQLClient($this->forProject($project));
  }

}
