<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Service;

use Drupal\gitlab_api\Entity\GitlabProjectInterface;

/**
 * Verifies the `X-Gitlab-Token` header.
 *
 * The header is checked against any of a project's resolved webhook secrets
 * using `hash_equals`. Multi-secret support allows zero-downtime rotation.
 */
final class SignatureVerifier {

  /**
   * Returns TRUE when $header matches any of the project's webhook secrets.
   */
  public function verify(GitlabProjectInterface $project, ?string $header): bool {
    if ($header === NULL || trim($header) === '') {
      return FALSE;
    }
    foreach ($project->resolveWebhookSecrets() as $secret) {
      if ($secret === '') {
        continue;
      }
      if (hash_equals($secret, $header)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
