<?php

namespace Drupal\maestro_template_builder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for this site.
 */
class MaestroTemplateBuilderSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_template_builder_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'maestro_template_builder.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('maestro_template_builder.settings');

    
    $default = $config->get('maestro_template_builder_pan_zoom_delay');
    $form['maestro_template_builder_pan_zoom_delay'] = [
      '#type' => 'number',
      '#min' => 500,
      '#title' => $this->t('The pan/zoom save delay.'),
      '#default_value' => $default ?? '1500',
      '#description' => $this->t('Minimum: 500. After zooming or panning the canvas, the template builder delays for the configured number of milliseconds before saving. Defaults to 1500 milliseconds.'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('maestro_template_builder.settings')
      ->set('maestro_template_builder_pan_zoom_delay', $form_state->getValue('maestro_template_builder_pan_zoom_delay'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
