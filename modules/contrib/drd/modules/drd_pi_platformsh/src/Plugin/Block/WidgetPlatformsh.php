<?php

namespace Drupal\drd_pi_platformsh\Plugin\Block;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drd_pi\Plugin\Block\WidgetPlatforms;

/**
 * Provides a 'WidgetPlatforms' block.
 *
 * @Block(
 *  id = "drd_pi_platformsh",
 *  admin_label = @Translation("DRD PI PlatformSH"),
 *  weight = -16,
 *  tags = {"drd_widget"},
 *  account_type = "platformsh_account",
 * )
 */
class WidgetPlatformsh extends WidgetPlatforms {

  /**
   * {@inheritdoc}
   */
  protected function title(): TranslatableMarkup {
    return $this->t('PlatformSH');
  }

  /**
   * {@inheritdoc}
   */
  protected function content(): array|string|TranslatableMarkup {
    return $this->t('@table<p><a href="@link">Settings</a></p>', [
      '@table' => $this->entitiesTable(),
      '@link' => (new Url('drd_pi_platformsh.drd_pi_platformsh_settings'))->toString(),
    ]);
  }

}
