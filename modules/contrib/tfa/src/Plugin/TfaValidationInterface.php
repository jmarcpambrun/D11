<?php

namespace Drupal\tfa\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface TfaValidationInterface.
 *
 * Validation plugins interact with the Tfa form processes to provide code entry
 * and validate submitted codes.
 *
 * @api
 */
interface TfaValidationInterface {

  /**
   * Get TFA process form from plugin.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form API array.
   */
  public function getForm(array $form, FormStateInterface $form_state): array;

  /**
   * Validate form.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether form passes validation or not
   */
  public function validateForm(array $form, FormStateInterface $form_state): bool;

  /**
   * Check whether the user has setup Tfa for this validation plugin.
   *
   * @return bool
   *   Whether or not the user has setup this validation plugin.
   */
  public function ready(): bool;

  /**
   * Validate a token.
   *
   * @param string $code
   *   The code/token to validate.
   *
   * @return bool
   *   Is the token valid.
   */
  public function validateRequest(#[\SensitiveParameter] string $code): bool;

  /**
   * Return the expected length of the token.
   *
   * @param string $password
   *   The full 'password' as submitted.
   *
   * @return int<0, max>
   *   This may be the expected length of the token based on internal
   *   knowledge or may be determined by analyzing the submitted password.
   *   A 0 may be returned if no token matching an expected format can be
   *   found or if another error occurs during the process.
   */
  public function tokenLength(#[\SensitiveParameter] string $password): int;

}
