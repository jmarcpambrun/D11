<?php

namespace Drupal\drd\Plugin\Block;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'WidgetCores' block.
 *
 * @Block(
 *  id = "drd_cores",
 *  admin_label = @Translation("DRD Cores"),
 *  weight = -7,
 *  tags = {"drd_widget"},
 * )
 */
class WidgetCores extends WidgetEntities {

  /**
   * {@inheritdoc}
   */
  protected function title(): TranslatableMarkup {
    return $this->t('Cores');
  }

  /**
   * {@inheritdoc}
   */
  protected function type(): string {
    return 'core';
  }

}
