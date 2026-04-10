<?php

namespace Drupal\eca_webform\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\webform\WebformInterface;

/**
 * Gets third party settings of a webform.
 */
#[Action(
  id: 'eca_webform_get_third_party_setting',
  label: new TranslatableMarkup('Webform: Get third-party setting'),
  type: 'webform',
)]
#[EcaAction(
  version_introduced: '1.0.0',
)]
class GetThirdPartySetting extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = AccessResult::forbidden();
    $provider = $this->tokenService->replace($this->configuration['provider']);
    $setting_name = $this->tokenService->replace($this->configuration['setting_name']);
    if ($object instanceof WebformInterface && $object->getThirdPartySetting($provider, $setting_name) !== NULL) {
      $access_result = AccessResult::allowed();
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?WebformInterface $webform = NULL): void {
    if ($webform === NULL) {
      return;
    }
    $provider = $this->tokenService->replace($this->configuration['provider']);
    $setting_name = $this->tokenService->replace($this->configuration['setting_name']);
    $value = $webform->getThirdPartySetting($provider, $setting_name, '');
    $this->tokenService->addTokenData($this->configuration['token_name'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'provider' => '',
      'setting_name' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['provider'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provider'),
      '#description' => $this->t('The machine name of the module that provides the setting.'),
      '#default_value' => $this->configuration['provider'],
      '#required' => TRUE,
      '#weight' => -25,
      '#eca_token_replacement' => TRUE,
    ];
    $form['setting_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Setting name'),
      '#description' => $this->t('The machine name of the setting, that holds the value.'),
      '#default_value' => $this->configuration['setting_name'],
      '#required' => TRUE,
      '#weight' => -20,
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The setting value will be loaded into this specified token.'),
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $this->configuration['setting_name'] = $form_state->getValue('setting_name');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
