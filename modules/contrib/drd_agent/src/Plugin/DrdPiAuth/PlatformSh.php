<?php

namespace Drupal\drd_agent\Plugin\DrdPiAuth;

/**
 * Provides the Platform.sh PI Auth plugin.
 *
 * @DrdPiAuth(
 *   id = "platformsh"
 * )
 */
class PlatformSh extends DrdPiAuthBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequired(): array {
    return ['PLATFORM_PROJECT'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getLocal(): array {
    return $_ENV;
  }

}
