<?php

declare(strict_types=1);

namespace Drupal\eca_gitlab_api\Service;

/**
 * Result of a successful slash-command parse.
 */
final class SlashCommandMatch {

  public function __construct(
    public readonly string $command,
    public readonly array $args,
  ) {}

}
