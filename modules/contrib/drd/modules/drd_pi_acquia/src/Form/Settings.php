<?php

namespace Drupal\drd_pi_acquia\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drd_pi_acquia\Entity\Account;

/**
 * Provides configuration form.
 */
class Settings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      Account::getConfigName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drd_pi_acquia_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->messenger()->addMessage('No settings required at this point.');
    return parent::buildForm($form, $form_state);
  }

}
