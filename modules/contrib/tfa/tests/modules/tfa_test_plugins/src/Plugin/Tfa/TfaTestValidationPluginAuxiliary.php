<?php

namespace Drupal\tfa_test_plugins\Plugin\Tfa;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tfa\Attribute\Tfa;

/**
 * TFA Test Validation Plugin.
 *
 * @package Drupal\tfa_test_plugins
 */
#[Tfa(
  "tfa_test_plugins_validation_auxiliary",
  new TranslatableMarkup("Auxiliary TFA Test Validation Plugin"),
  new TranslatableMarkup("Auxiliary TFA Test Validation Plugin"),
  [],
  []
)]
class TfaTestValidationPluginAuxiliary extends TfaTestValidationPlugin {
}
