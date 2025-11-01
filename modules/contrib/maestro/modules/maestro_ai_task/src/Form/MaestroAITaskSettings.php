<?php

namespace Drupal\maestro_ai_task\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for the Maestro AI task mechanism.
 */
class MaestroAITaskSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'maestro_ai_task.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // We use the Key module to store API Keys.  
    $config = $this->config('maestro_ai_task.settings');

    $default = $config->get('base_prompt');
    $form['base_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Base Prompt.'),
      '#default_value' => isset($default) ? $default : 'You are a knowledgeable and helpful office administrator who reviews documents.',
      '#description' => $this->t('The base AI Prompt. This prompt will be added, by default, to any new AI task added to your workflow.'),
      '#required' => TRUE,
    ];

    $default = $config->get('ai_testing') ?? 0;
    $form['ai_testing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test Mode'),
      '#default_value' => $default,
      '#description' => $this->t('When checked, the AI Testing Response field will be used as the return from AI calls.'),
      '#required' => FALSE,
    ];

    $default = $config->get('ai_testing_response') ?? Json::encode(
      [
        'result' => 'true',
      ]
    );
    $form['ai_testing_response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI Testing Response.'),
      '#default_value' => $default,
      '#description' => $this->t('When in testing mode, this is the response text used by the Maestro AI module.'),
      '#required' => FALSE,
    ];

    $default = $config->get('use_ai_module');
    $form['use_ai_module'] = [
      '#markup' => $this->t('Please use the AI module configuration to set up the LLM API of your choice'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('maestro_ai_task.settings')
      ->set('base_prompt', $form_state->getValue('base_prompt'))
      ->save();

    $this->config('maestro_ai_task.settings')
      ->set('ai_testing', $form_state->getValue('ai_testing'))
      ->save();

    $this->config('maestro_ai_task.settings')
      ->set('ai_testing_response', $form_state->getValue('ai_testing_response'))
      ->save();
  }

}
