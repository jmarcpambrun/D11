<?php

namespace Drupal\counter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class to define advanced settings for the counter.
 *
 * @package Drupal\counter\Form\CounterSettingsAdvanced.
 */
class CounterSettingsAdvanced extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'counter.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'counter_advanced';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('counter.settings');

    // Generate the form - settings applying to all patterns first.
    $form['counter_advanced'] = [
      '#type' => 'details',
      '#weight' => -20,
      '#title' => $this->t('Advanced settings', [], ['context' => 'counter']),
    ];

    $form['counter_advanced']['counter_skip_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip admin', [], ['context' => 'counter']),
      '#default_value' => $config->get('counter_skip_admin'),
      '#description' => $this->t("Do not count when visitor has an administrator role.", [], ['context' => 'counter']),
    ];

    $form['counter_advanced']['counter_only_pages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only pages', [], ['context' => 'counter']),
      '#default_value' => $config->get('counter_only_pages'),
      '#description' => $this->t('Only considers user visits', [], ['context' => 'counter']),
    ];

    $form['counter_advanced']['counter_refresh_on_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Refresh counter values on cron run.', [], ['context' => 'counter']),
      '#default_value' => $config->get('counter_refresh_on_cron'),
      '#description' => $this->t("It will make sure cache of the counter block is cleared on cron run. Strongly recommended to keep this enabled for performance reasons.", [], ['context' => 'counter']),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('counter.settings')
      ->set('counter_skip_admin', $form_state->getValue('counter_skip_admin'))
      ->set('counter_only_pages', $form_state->getValue('counter_only_pages'))
      ->set('counter_refresh_on_cron', $form_state->getValue('counter_refresh_on_cron'))
      ->save();
  }

}
