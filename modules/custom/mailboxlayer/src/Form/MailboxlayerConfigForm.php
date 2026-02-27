<?php

namespace Drupal\mailboxlayer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * MailboxlayerConfigForm to get the mailboxlayer configuration.
 */
class MailboxlayerConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mailboxlayer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailboxlayer_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mailboxlayer.settings');

    $form['mailbox_layer'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mailboxlayer Configuration'),
    ];
    $form['mailbox_layer']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mailboxlayer Access Token'),
      '#default_value' => $config->get('mailbox_layer.api_key'),
    ];
    $form['mailbox_layer']['email_expire'] = [
      '#type' => 'select',
      '#title' => $this->t('Email expiration (In Days)'),
      '#description' => $this->t('Select the days after which the email gets expires from the mailboxlayer log table'),
      '#options' => [
        '1' => '1',
        '5' => '5',
        '10' => '10',
        '20' => '20',
        '30' => '30',
      ],
      '#default_value' => $config->get('mailbox_layer.email_expire'),

    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mailboxlayer.settings');
    $config->clear('mailbox_layer');
    /* Storing Mailbox API Layer Key */
    $config->set('mailbox_layer.api_key', $form_state->getValue(['api_key']));
    $config->set('mailbox_layer.email_expire', $form_state->getValue(['email_expire']));
    $config->save();

    return parent::submitForm($form, $form_state);

  }

}
