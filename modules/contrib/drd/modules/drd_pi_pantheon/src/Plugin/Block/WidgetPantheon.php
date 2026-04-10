<?php

namespace Drupal\drd_pi_pantheon\Plugin\Block;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drd_pi\Plugin\Block\WidgetPlatforms;

/**
 * Provides a 'WidgetPlatforms' block.
 *
 * @Block(
 *  id = "drd_pi_pantheon",
 *  admin_label = @Translation("DRD PI Pantheon"),
 *  weight = -17,
 *  tags = {"drd_widget"},
 *  account_type = "pantheon_account",
 * )
 */
class WidgetPantheon extends WidgetPlatforms {

  /**
   * {@inheritdoc}
   */
  protected function title(): TranslatableMarkup {
    return $this->t('Pantheon');
  }

  /**
   * {@inheritdoc}
   */
  protected function content(): array|string|TranslatableMarkup {
    return $this->t('@table<p><a href="@link">Settings</a></p>', [
      '@table' => $this->entitiesTable(),
      '@link' => (new Url('drd_pi_pantheon.drd_pi_pantheon_settings'))->toString(),
    ]);
  }

}
