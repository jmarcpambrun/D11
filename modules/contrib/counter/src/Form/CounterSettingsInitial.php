<?php

namespace Drupal\counter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AddForm.
 *
 * @package Drupal\counter\Form\CounterSettingsInitial.
 */
class CounterSettingsInitial extends ConfigFormBase {

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
    return 'counter_initial';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('counter.settings');

    // Generate the form - settings applying to all patterns first.
    $form['counter_initial'] = [
      '#type' => 'details',
      '#weight' => -30,
      '#title' => $this->t('Basic settings'),
    ];

    $form['counter_initial'] = [
      '#type' => 'details',
      '#weight' => -10,
      '#title' => $this->t('Initial Values'),
      '#description' => $this->t("Set initial values for Site Counter."),
    ];

    $form['counter_initial']['counter_initial_counter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Initial value of Site Counter'),
      '#default_value' => $config->get('counter_initial_counter'),
      '#description' => $this->t('Initial value of Site Counter'),
    ];

    $form['counter_initial']['counter_initial_unique_visitor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Initial value of Unique Visitor'),
      '#default_value' => $config->get('counter_initial_unique_visitor'),
      '#description' => $this->t('Initial value of Unique Visitor'),
    ];

    $form['counter_initial']['counter_initial_since'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Replace 'Since' value with this Unix timestamp"),
      '#default_value' => $config->get('counter_initial_since'),
      '#description' => $this->t("This field type is Unix timestamp, so you must enter like: 1404671462."),
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
      ->set('counter_initial_counter', $form_state->getValue('counter_initial_counter'))
      ->set('counter_initial_unique_visitor', $form_state->getValue('counter_initial_unique_visitor'))
      ->set('counter_initial_since', $form_state->getValue('counter_initial_since'))
      ->save();
  }

}
