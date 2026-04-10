<?php

namespace Drupal\drd\Plugin\Block;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drd\Form\Actions;

/**
 * Provides a 'WidgetGlobalActions' block.
 *
 * @Block(
 *  id = "drd_global_actions",
 *  admin_label = @Translation("DRD Global actions"),
 *  weight = -5,
 *  tags = {"drd_widget"},
 * )
 */
class WidgetGlobalActions extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  protected function title(): TranslatableMarkup {
    return $this->t('Action');
  }

  /**
   * {@inheritdoc}
   */
  protected function content(): array|string|TranslatableMarkup {
    return $this->formBuilder->getForm(Actions::class);
  }

}
