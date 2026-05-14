<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Event;

use Drupal\gitlab_api\Entity\GitlabProjectInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for all GitLab API webhook Symfony events.
 *
 * Each event carries the local GitLab project (config entity) and the raw
 * webhook payload. There is no local mirror — actions and ECA conditions
 * read everything they need from the payload.
 */
abstract class GitlabEventBase extends Event {

  /**
   * Constructs a GitLab webhook event.
   */
  public function __construct(
    private readonly GitlabProjectInterface $project,
    private readonly array $payload,
  ) {}

  /**
   * Returns the GitLab project this event belongs to.
   */
  public function getProject(): GitlabProjectInterface {
    return $this->project;
  }

  /**
   * Returns the local GitLab project config entity ID.
   */
  public function getProjectId(): string {
    return $this->project->id();
  }

  /**
   * Returns the raw webhook payload.
   *
   * @return array<string, mixed>
   *   The decoded JSON webhook payload.
   */
  public function getPayload(): array {
    return $this->payload;
  }

}
