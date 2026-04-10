<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Php' action.
 *
 * @Action(
 *  id = "drd_action_php",
 *  label = @Translation("Execute PHP"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Php extends BaseEntityRemote implements BaseConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  protected function setDefaultArguments(): void {
    parent::setDefaultArguments();
    $this->arguments['php'] = '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getFollowUpAction(): string {
    return 'drd_action_info';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['drd_action_php'] = [
      '#type' => 'textarea',
      '#title' => t('PHP Code'),
      '#default_value' => '',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->arguments['php'] = $form_state->getValue('drd_action_php');
  }

}
