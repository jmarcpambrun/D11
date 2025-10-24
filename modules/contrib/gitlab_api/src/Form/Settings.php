<?php

namespace Drupal\gitlab_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure GitLab API settings for this site.
 */
class Settings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'gitlab_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['gitlab_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#default_value' => $this->config('gitlab_api.settings')->get('url'),
      '#description' => $this->t('The url of the gitlab instance (eg : <em>https://gitlab.com</em> or <em>https://gitlab.mysite.com</em>)'),

    ];
    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authentication token'),
      '#default_value' => $this->config('gitlab_api.settings')->get('token'),
      '#description' => $this->t('Get this by creating a new personal access token on your gitlab profile (uri : <em>/profile/personal_access_tokens</em>)'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('gitlab_api.settings');
    $config
      ->set('url', $form_state->getValue('url'))
      ->set('token', $form_state->getValue('token'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
