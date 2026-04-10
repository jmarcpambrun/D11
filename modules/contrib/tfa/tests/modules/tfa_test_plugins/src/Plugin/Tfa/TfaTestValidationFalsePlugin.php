<?php

namespace Drupal\tfa_test_plugins\Plugin\Tfa;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tfa\Attribute\Tfa;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaBasePlugin;

/**
 * TFA Test Validation Plugin - FALSE.
 *
 * Provides a plugin that will return FALSE when interrogated.
 *
 * @package Drupal\tfa_test_plugins
 */
#[Tfa(
  "tfa_test_plugins_validation_false",
  new TranslatableMarkup("TFA Test Validation Plugin - FALSE Response"),
  new TranslatableMarkup("TFA Test Validation Plugin - FALSE Response"),
  [],
  []
)]
class TfaTestValidationFalsePlugin extends TfaBasePlugin implements TfaValidationInterface {

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function ready(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateRequest(#[\SensitiveParameter] string $code): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function tokenLength(#[\SensitiveParameter] string $password): int {
    return 6;
  }

}
