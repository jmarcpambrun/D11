<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'UserCredentials' action.
 *
 * @Action(
 *  id = "drd_action_user_credentials",
 *  label = @Translation("User Credentials"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class UserCredentials extends BaseEntityRemote implements BaseConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  protected function setDefaultArguments(): void {
    parent::setDefaultArguments();
    $this->arguments['uid'] = 1;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['drd_action_user_credentials_uid'] = [
      '#type' => 'textfield',
      '#title' => t('User ID'),
      '#default_value' => $this->arguments['uid'],
      '#required' => TRUE,
    ];
    $form['drd_action_user_credentials_username'] = [
      '#type' => 'textfield',
      '#title' => t('User name'),
      '#default_value' => '',
    ];
    $form['drd_action_user_credentials_password'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#default_value' => '',
    ];
    $form['drd_action_user_credentials_status'] = [
      '#type' => 'select',
      '#title' => t('Status'),
      '#default_value' => -1,
      '#options' => [
        -1 => t('unchanged'),
        0 => t('disable'),
        1 => t('enable'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Nothing to be validated.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->arguments['uid'] = $form_state->getValue('drd_action_user_credentials_uid');
    $username = trim($form_state->getValue('drd_action_user_credentials_username'));
    $password = trim($form_state->getValue('drd_action_user_credentials_password'));
    $status = $form_state->getValue('drd_action_user_credentials_status');
    if (!empty($username)) {
      $this->arguments['username'] = $username;
    }
    if (!empty($password)) {
      $this->arguments['password'] = $password;
    }
    if ($status >= 0) {
      $this->arguments['status'] = $status;
    }
  }

}
