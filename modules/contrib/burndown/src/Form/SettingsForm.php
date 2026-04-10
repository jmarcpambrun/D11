<?php

namespace Drupal\burndown\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the settings form for configuring the Burndown module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'burndown_config_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'burndown.config_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('burndown.config_settings');

    // Do we want email notifications to be enabled?
    $form['enable_email_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable email notifications?'),
      '#description' => $this->t('This will enable notifications of task changes to users on the watchlist for the task.'),
      '#default_value' => $config->get('enable_email_notifications'),
    ];

    // Geometric estimates.
    // i.e. 0.5, 1, 2, 3, 5, 8, 13.
    $form['geometric_size_defaults'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default geometric estimate sizes'),
      '#description' => $this->t('Use one per line, in the format "size|label".'),
      '#default_value' => $config->get('geometric_size_defaults'),
    ];

    // T-shirt size estimates.
    // i.e. XS, S, M, L, XL.
    $form['tshirt_size_defaults'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default T-shirt estimate sizes'),
      '#description' => $this->t('Use one per line, in the format "size|label".'),
      '#default_value' => $config->get('tshirt_size_defaults'),
    ];

    // Resolution statuses.
    $form['resolution_statuses'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Task resolution statuses'),
      '#description' => $this->t('Use one per line, in the format "id|label".'),
      '#default_value' => $config->get('resolution_statuses'),
    ];

    // Task relationship types.
    $form['relationship_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Task relationship types'),
      '#description' => $this->t('Use one per line, in the format "id|label".'),
      '#default_value' => $config->get('relationship_types'),
    ];

    // Opposites of relationships.
    $form['relationship_opposites'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Task relationship opposites'),
      '#description' => $this->t('The opposite of a task relationship (i.e. if ticket "A" "Blocks" ticket "B", then ticket "B" is "Blocked by" "A".'),
      '#default_value' => $config->get('relationship_opposites'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('burndown.config_settings')
      ->set('enable_email_notifications', $form_state->getValue('enable_email_notifications'))
      ->set('geometric_size_defaults', $form_state->getValue('geometric_size_defaults'))
      ->set('tshirt_size_defaults', $form_state->getValue('tshirt_size_defaults'))
      ->set('resolution_statuses', $form_state->getValue('resolution_statuses'))
      ->set('relationship_types', $form_state->getValue('relationship_types'))
      ->set('relationship_opposites', $form_state->getValue('relationship_opposites'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
