<?php

namespace Drupal\tfa_test_plugins\Plugin\Tfa;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tfa\Attribute\Tfa;
use Drupal\tfa\Plugin\TfaLoginInterface;
use Drupal\tfa\TfaBasePlugin;

/**
 * A test login plugin.
 */
#[Tfa(
  "tfa_test_login_plugin",
  new TranslatableMarkup("TFA Test Plugin"),
  new TranslatableMarkup("TFA Test Plugin"),
  [],
  []
)]
class TfaTestLoginPlugin extends TfaBasePlugin implements TfaLoginInterface {

  /**
   * Set state for whether we allow login via this plugin.
   *
   * @param bool $allowed
   *   If it should be allowed.
   */
  public static function setIsLoginAllowed(bool $allowed = TRUE): void {
    \Drupal::state()->set('tfa_test_login_plugin_allowed', $allowed);
  }

  /**
   * Check state for whether we allow login via this plugin.
   *
   * @return bool
   *   TRUE if login is allowed.
   */
  public static function isLoginAllowed(): bool {
    return \Drupal::state()->get('tfa_test_login_plugin_allowed', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function loginAllowed(): bool {
    return self::isLoginAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state): array {
    $form['tfa_test_login_plugin_checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This is a test plugin!'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state): bool {
    return TRUE;
  }

}
