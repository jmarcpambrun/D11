<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides form for core entities.
 *
 * @package Drupal\drd\Entity\Form
 *
 * @ingroup drd
 */
class HostSettings extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId(): string {
    return 'DrdHost_settings';
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Empty implementation of the abstract submit class.
  }

  /**
   * Defines the settings form for Host entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['DrdHost_settings']['#markup'] = 'Settings form for Host entities. Manage field settings here.';
    return $form;
  }

}
