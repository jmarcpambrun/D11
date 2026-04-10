<?php

namespace Drupal\tfa_test_plugins\Plugin\Tfa;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tfa\Attribute\Tfa;
use Drupal\tfa\Plugin\TfaSendInterface;

/**
 * TFA Test Send Plugin - FALSE.
 *
 * Provides a plugin that will return FALSE when interrogated.
 *
 * @package Drupal\tfa_test_plugins
 */
#[Tfa(
  "tfa_test_plugins_send_false",
  new TranslatableMarkup("TFA Test Send Plugin - FALSE Response"),
  new TranslatableMarkup("TFA Test Send Plugin - FALSE Response"),
  [],
  []
)]
class TfaTestSendPluginFalse extends TfaTestValidationFalsePlugin implements TfaSendInterface {

  /**
   * {@inheritdoc}
   */
  public function begin(): void {
    // NOOP.
  }

}
