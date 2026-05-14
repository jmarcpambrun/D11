<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Service;

use Gitlab\Client;

/**
 * Minimal GraphQL client over an authenticated Gitlab\Client.
 *
 * Reuses the auth + transport layer from the underlying Gitlab\Client so
 * GraphQL calls inherit the same Bearer token, host plugin and HTTP plugin
 * chain as REST calls — without pulling in a separate GraphQL library.
 */
final class GitLabGraphQLClient {

  public function __construct(private readonly Client $client) {}

  /**
   * Executes a GraphQL operation against `/api/graphql`.
   *
   * @param string $query
   *   The GraphQL document.
   * @param array<string, mixed> $variables
   *   Variables for the operation.
   *
   * @return array{data?: array, errors?: array}
   *   The decoded JSON response. May contain top-level `errors` (transport /
   *   schema errors) and/or `data.<mutation>.errors` (mutation-level errors).
   */
  public function execute(string $query, array $variables = []): array {
    $body = json_encode(
      ['query' => $query, 'variables' => (object) $variables],
      JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    $response = $this->client->getHttpClient()->post(
      '/api/graphql',
      ['Content-Type' => 'application/json'],
      $body,
    );
    $decoded = json_decode((string) $response->getBody(), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

}
