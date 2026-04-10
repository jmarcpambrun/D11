<?php

namespace Drupal\eca_webform\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Gets data from a webform submission.
 */
#[Action(
  id: 'eca_webform_submission_get_data',
  label: new TranslatableMarkup('Webform Submission: Get data'),
  type: 'webform_submission',
)]
#[EcaAction(
  version_introduced: '1.0.0',
)]
class GetSubmissionData extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = AccessResult::forbidden();
    $field_name = $this->tokenService->replace($this->configuration['field_name']);
    if ($object instanceof WebformSubmission && $object->getWebform()->getElement($field_name) !== NULL) {
      $access_result = AccessResult::allowed();
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?WebformSubmissionInterface $webform_submission = NULL): void {
    if ($webform_submission === NULL) {
      return;
    }
    $field_name = $this->tokenService->replace($this->configuration['field_name']);
    $value = $webform_submission->getElementData($field_name) ?? '';
    $this->tokenService->addTokenData($this->configuration['token_name'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The machine name of the field, that holds the value.'),
      '#default_value' => $this->configuration['field_name'],
      '#required' => TRUE,
      '#weight' => -20,
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The field value will be loaded into this specified token.'),
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
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
