<?php

namespace Drupal\event_scheduler\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class EventSchedulerSettingsForm.
 */
class EventSchedulerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_scheduler_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('event_scheduler.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Uncheck this box to disable the event scheduling process.'),
      '#default_value' => $settings->get('enabled') ?? TRUE,
    ];

    $form['logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Logging'),
      '#description' => $this->t('When checked this will log event scheduling actions to help with debugging. Busy sites can generate a lot of messages.'),
      '#default_value' => $settings->get('logging') ?? FALSE,
      '#states' => [
        'disabled' => [
          ':input[name="enabled"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->config('event_scheduler.settings');

    $settings
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('logging', (bool) $form_state->getValue('logging'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'event_scheduler.settings',
    ];
  }

}
