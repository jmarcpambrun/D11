<?php

namespace Drupal\mail_box_management\Form;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mail_box_management\Plugin\ConfigurationHelper;

/**
 * File ConfigurationForm contains class ConfigurationForm.
 *
 * @file
 * ConfigurationForm contains class ConfigurationForm.
 */

/**
 * Class ConfigurationForm extends FormBase.
 *
 * @class
 * ConfigurationForm extends FormBase.
 */
class ConfigurationForm extends FormBase {

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
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'mail_box_management_configuration_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $servers = $this->configuration_helper->get('mail_box_management.settings');
    $imap = $servers->get('imap_servers') ?? [];

    $imap = array_filter($imap, function ($server) {
      return $server['owner_id'] == $this->currentUser()->id();
    });

    if (!empty($imap)) {
      $imap = reset($imap);
    }
    $form['form_title'] = [
      '#markup' => '<h1>Mailbox Configuration Form</h1>',
    ];
    $form['top_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('IMAP Server Settings'),
    // Default to collapsed.
      '#open' => FALSE,
      '#description' => $this->t('Configure your IMAP server settings below.'),
    ];
    $form['top_wrapper']['connection_type_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Connection Type'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['top_wrapper']['connection_type_wrapper']['connection_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Connection Type'),
      '#options' => [
        'none' => $this->t('None'),
        'imap' => $this->t('Mailbox IMAP'),
      ],
      '#required' => TRUE,
      '#default_value' => $imap['connection_type'] ?? NULL,
    ];
    $form['top_wrapper']['connection_type_wrapper']['connection_type_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Connection Type Title'),
      '#required' => TRUE,
      '#default_value' => $imap['connection_type_title'] ?? NULL,
    ];

    $form['top_wrapper']['imap_server_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => t('IMAP Server Information'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $form['top_wrapper']['imap_server_wrapper']['host'] = [
      '#type' => 'textfield',
      '#title' => t('Server Host'),
      '#required' => TRUE,
      '#default_value' => $imap['host'] ?? NULL,
    ];
    $form['top_wrapper']['imap_server_wrapper']['port'] = [
      '#type' => 'textfield',
      '#title' => t('Server Port'),
      '#required' => TRUE,
      '#default_value' => $imap['port'] ?? NULL,
    ];
    $form['top_wrapper']['imap_server_wrapper']['secure'] = [
      '#type' => 'select',
      '#title' => t('Server Secure'),
      '#required' => TRUE,
      '#options' => [
        'ssl' => 'SSL',
        'tls' => 'TLS',
      ],
      '#default_value' => $imap['secure'] ?? NULL,
    ];
    $form['top_wrapper']['imap_server_wrapper']['username'] = [
      '#type' => 'textfield',
      '#title' => t('Server Username'),
      '#required' => TRUE,
      '#default_value' => $imap['username'] ?? NULL,
    ];
    $form['top_wrapper']['imap_server_wrapper']['password'] = [
      '#type' => 'password',
      '#title' => t('Server Password'),
      '#required' => TRUE,
      '#default_value' => $imap['password'] ?? NULL,
    ];
    $form['top_wrapper']['imap_server_wrapper']['action'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'id' => 'mail-box-management-configuration-submit',
      ],
    ];
    if (in_array('administrator', $this->currentUser()->getRoles()) || !empty($imap)) {
      $form['imap_servers'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Host'),
          $this->t('Password'),
          $this->t('Secure'),
          $this->t('Port'),
          $this->t('Username'),
          $this->t('Owner'),
          $this->t('Connection Type'),
          $this->t('Connection Title'),
          $this->t('Operations'),
        ],
        '#empty' => $this->t('No IMAP servers added yet.'),
      ];
      $imap_servers = $servers->get('imap_servers');
      $imap_servers = in_array('administrator', $this->currentUser()
        ->getRoles()) ? $imap_servers : [$imap];

      // Populate rows with existing data.
      foreach ($imap_servers as $index => $server) {
        $form['imap_servers'][$index]['host'] = [
          '#markup' => $server['host'],
        ];
        $form['imap_servers'][$index]['password'] = [
          '#markup' => str_repeat('*', strlen($server['password'])),
        ];
        $form['imap_servers'][$index]['secure'] = [
          '#markup' => $server['secure'],
        ];
        $form['imap_servers'][$index]['port'] = [
          '#markup' => $server['port'],
        ];
        $form['imap_servers'][$index]['username'] = [
          '#markup' => $server['username'],
        ];
        $user = mail_box_management_service('entity_type.manager')
          ->getStorage('user')->load($server['owner_id']);
        if ($user instanceof User) {
          $form['imap_servers'][$index]['owner_id'] = [
            '#type' => 'link',
            '#title' => $user->getAccountName(),
            '#url' => Url::fromRoute('entity.user.canonical', route_parameters: ['user' => $user->id()]),
          ];
        }
        else {
          $form['imap_servers'][$index]['owner_id'] = [
            '#markup' => t('User does not exist.'),
          ];
        }
        $form['imap_servers'][$index]['connection_type'] = [
          '#markup' => $server['connection_type'],
        ];
        $form['imap_servers'][$index]['connection_type_title'] = [
          '#markup' => $server['connection_type_title'],
        ];
        $form['imap_servers'][$index]['operations'] = [
          '#type' => 'link',
          '#title' => $this->t('Delete'),
          '#url' => Url::fromRoute('mail_box_management.delete_server', ['owner_id' => $server['owner_id']]),
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory()->getEditable('mail_box_management.settings');
    $imap_servers = [
      'connection_type' => $form_state->getValue('connection_type'),
      'connection_type_title' => $form_state->getValue('connection_type_title'),
      'host' => $form_state->getValue('host'),
      'port' => $form_state->getValue('port'),
      'username' => $form_state->getValue('username'),
      'password' => $form_state->getValue('password'),
      'secure' => $form_state->getValue('secure'),
      'owner_id' => $this->currentUser()->id(),
    ];
    $existing = $this->configuration_helper->get('mail_box_management.settings');
    $imps = $existing->get('imap_servers') ?? [];
    $imps[] = $imap_servers;
    $config->set('imap_servers', $imps)->save();
    $this->messenger->addMessage($this->t('Configuration saved.'));
  }

}
