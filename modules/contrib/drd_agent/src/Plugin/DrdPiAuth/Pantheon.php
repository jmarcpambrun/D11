<?php

namespace Drupal\drd_agent\Plugin\DrdPiAuth;

/**
 * Provides the Pantheon PI Auth plugin.
 *
 * @DrdPiAuth(
 *   id = "pantheon"
 * )
 */
class Pantheon extends DrdPiAuthBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequired(): array {
    return ['PANTHEON_SITE'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getLocal(): array {
    return $_ENV;
  }

}
