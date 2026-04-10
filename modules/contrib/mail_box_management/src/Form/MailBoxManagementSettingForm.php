<?php

namespace Drupal\mail_box_management\Form;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mail_box_management\Plugin\ConfigurationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MailBoxManagementSettingForm.php contains class MailBoxManagementSettingForm.
 *
 * @file
 * MailBoxManagementSettingForm.
 */

/**
 * MailBoxManagementSettingForm handles settings form.
 *
 * @class
 * MailBoxManagementSettingForm
 */
class MailBoxManagementSettingForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function __construct(
    private readonly ConfigurationHelper $configuration_helper,
    MessengerInterface $messenger,
  ) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): ConfigurationForm|static {
    return new static(
      $container->get('mail_box_management.config'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mail_box_management_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['settings_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mail box settings'),
      '#collapsible' => TRUE,
    ];
    $form['settings_wrapper']['cache_mail_data'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cache Data of Mails'),
      '#default_value' => $this->configuration_helper->get('cache_mail_data')?->get('cache_mail_data') ?? FALSE,
      '#description' => $this->t('Enable this option to cache the metadata
       and basic details of mails, improving performance by reducing real-time
       data retrieval from the mail system.'),
    ];

    $form['settings_wrapper']['cache_mail_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cache Data of Mail Content'),
      '#default_value' => $this->configuration_helper->get('cache_mail_content')?->get('cache_mail_content') ?? FALSE,
      '#description' => $this->t('Enable this option to cache the full
      content of mails, reducing load times by avoiding repeated
      content processing or retrieval.'),
    ];

    $form['settings_wrapper']['cache_mail_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cache Cron'),
      '#default_value' => $this->configuration_helper->get('cache_mail_cron')?->get('cache_mail_cron') ?? FALSE,
      '#description' => $this->t("Enable this setting if you want to
      ensure that your mail cache is refreshed regularly,
      particularly when mail data changes frequently, such
       as new incoming messages or changes in mail content. If you're using
       caching for performance, it's essential to clear outdated cache
       entries to prevent displaying old or inaccurate data."),
    ];

    $form['settings_wrapper']['cache_mail_clear'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cache Data Clear'),
      '#default_value' => $this->configuration_helper->get('cache_mail_clear')?->get('cache_mail_clear') ?? FALSE,
      '#description' => $this->t("Enable this setting if you need to clear
       the cached mail data manually. This can be useful after major updates,
       data changes, or if you're troubleshooting issues related to stale or
       incorrect mail information. It allows you to clear the cache without
        waiting for the cron job to trigger."),
    ];
    $form['settings_wrapper']['cache_mail_count_mailbox'] = [
      '#type' => 'number',
      '#title' => $this->t('Mails Per Mailbox Count'),
      '#default_value' => $this->configuration_helper->get('cache_mail_count_mailbox')?->get('cache_mail_count_mailbox') ?? 10,
      '#description' => $this->t("Enable this setting if your application
       frequently retrieves the count of mails per mailbox and you want to
        improve performance by avoiding repeated database queries.
         It’s particularly useful for large mailboxes or high-traffic
          applications."),
    ];

    $themes = mail_box_management_service('theme_handler')->listInfo();
    $options[''] = t('- Select theme -');
    foreach ($themes as $theme) {
      if ($theme instanceof Extension) {
        $options[$theme->getName()] = $theme->info['name'];
      }
    }

    $form['settings_wrapper']['theme_settings'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme Settings'),
      '#default_value' => $this->configuration_helper->get('mailbox_theme')?->get('mailbox_theme') ?? FALSE,
      '#options' => $options,
      '#description' => $this->t('Select theme which will be used for rendering mailbox for non administrator users.'),
    ];

    $form['settings_wrapper']['actions']['#type'] = 'actions';
    $form['settings_wrapper']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Settings'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $data = $form_state->getValues();
    $this->configuration_helper->set('cache_mail_data', $data['cache_mail_data']);
    $this->configuration_helper->set('cache_mail_content', $data['cache_mail_content']);
    $this->configuration_helper->set('cache_mail_cron', $data['cache_mail_cron']);
    $this->configuration_helper->set('cache_mail_clear', $data['cache_mail_clear']);
    $this->configuration_helper->set('cache_mail_count_mailbox', $data['cache_mail_count_mailbox']);
    $this->configuration_helper->set('mailbox_theme', $data['theme_settings']);
    $this->messenger->addMessage($this->t('Settings saved.'));
  }

}
