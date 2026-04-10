<?php

namespace Drupal\tfa\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\tfa\TfaPluginManager;
use Drupal\user\RoleInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The admin configuration page.
 *
 * @phpcs:disable DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
 *    PHPStan protects against this sniff.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * The TFA plugin manager to fetch plugin information.
   *
   * @var \Drupal\tfa\TfaPluginManager
   */
  protected TfaPluginManager $pluginManager;

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected UserDataInterface $userData;

  /**
   * Encryption profile manager to fetch the existing encryption profiles.
   *
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface
   */
  protected EncryptionProfileManagerInterface $encryptionProfileManager;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The admin configuration form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory object.
   * @param \Drupal\tfa\TfaPluginManager $plugin_manager
   *   The TFA plugin manager.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryption_profile_manager
   *   Encrypt profile manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TfaPluginManager $plugin_manager, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EntityTypeManagerInterface $entity_type_manager, TypedConfigManagerInterface $typed_config_manager) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->pluginManager = $plugin_manager;
    $this->encryptionProfileManager = $encryption_profile_manager;
    // User Data service to store user-based data in key value pairs.
    $this->userData = $user_data;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.tfa'),
      $container->get('user.data'),
      $container->get('encrypt.encryption_profile.manager'),
      $container->get('entity_type.manager'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tfa_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['tfa.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('tfa.settings');
    $form = [];

    // Get Login Plugins.
    $login_plugins = $this->pluginManager->getLoginDefinitions(FALSE);

    // Get Send Plugins.
    $send_plugins = $this->pluginManager->getSendDefinitions(FALSE);

    // Get Validation Plugins.
    $validation_plugins = $this->pluginManager->getValidationDefinitions(FALSE);
    // Get validation plugin labels.
    $validation_plugins_labels = [];
    foreach ($validation_plugins as $key => $plugin) {
      $validation_plugins_labels[$plugin['id']] = $plugin['label']->render();
    }

    // Fetching all available encryption profiles.
    $encryption_profiles = $this->encryptionProfileManager->getAllEncryptionProfiles();

    if (empty($validation_plugins)) {
      $this->messenger()->addError($this->t('No plugins available for validation. See the TFA help documentation for setup.'));
      $form_state->cleanValues();
      return $form;
    }
    if (empty($encryption_profiles)) {
      $this->messenger()->addError($this->t('No Encryption profiles available. <a href=":add_profile_url">Add an encryption profile</a>.', [':add_profile_url' => Url::fromRoute('entity.encryption_profile.add_form')->toString()]));
      $form_state->cleanValues();
      // Return form instead of parent::BuildForm to avoid the save button.
      return $form;
    }

    // Enable TFA checkbox.
    $form['tfa_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable TFA'),
      '#default_value' => $config->get('enabled'),
      '#description' => $this->t('Enable TFA for account authentication.'),
    ];

    $enabled_state = [
      'visible' => [':input[name="tfa_enabled"]' => ['checked' => TRUE]],
    ];

    /** @var \Drupal\user\RoleStorageInterface $role_storage */
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    /** @var \Drupal\user\RoleInterface $authenticated_role */
    $authenticated_role = $role_storage->load(RoleInterface::AUTHENTICATED_ID);
    $authenticated_role_setup_permission = $authenticated_role->hasPermission('setup own tfa');
    /** @var \Drupal\user\RoleInterface|null $roles */
    $roles = $role_storage->loadMultiple();

    $form['tfa_required_roles_container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles required to set up TFA'),
      '#description' => $this->t('Require users with these roles to set up TFA. Note: If a role does not have the "setup own tfa" permission users will be unable to configure a TFA token without administrator assistance. The account will be locked out once the skip limit is reached unless a token is configured through other methods.'),
      '#states' => $enabled_state,
    ];
    $form['tfa_required_roles_container']['tfa_required_roles'] = [
      '#type' => 'tableselect',
      '#title' => $this->t('Roles required to set up TFA'),
      '#header' => [
        $this->t('Role'),
        $this->t('Permission to set up TFA'),
      ],
      '#options' => [],
      '#empty' => $this->t('No Roles found.'),
      '#default_value' => $config->get('required_roles') ?: [],
    ];

    foreach ($roles as $role) {
      if ($role->id() == RoleInterface::ANONYMOUS_ID) {
        continue;
      }

      if ($authenticated_role_setup_permission && !$role->hasPermission('setup own tfa')) {
        $form['tfa_required_roles_container']['tfa_required_roles']['#options'][$role->id()] = [
          $role->label(),
          $this->t('Role both has permission to "Set up TFA for account" and inherits permission from the "authenticated" role.'),
        ];
      }
      elseif ($authenticated_role_setup_permission) {
        $form['tfa_required_roles_container']['tfa_required_roles']['#options'][$role->id()] = [
          $role->label(),
          $this->t('Role inherits permission from the "authenticated" role.'),
        ];
      }
      elseif ($role->hasPermission('setup own tfa')) {
        $form['tfa_required_roles_container']['tfa_required_roles']['#options'][$role->id()] = [
          $role->label(),
          $this->t('Role has permission "Set up TFA for account"'),
        ];
      }
      else {
        $form['tfa_required_roles_container']['tfa_required_roles']['#options'][$role->id()] = [
          $role->label(),
          $this->t(
            'Role does not have access to configure own tokens, verify <a href=":permissions_link">permissions</a>',
            [
              ':permissions_link' => Url::fromRoute('user.admin_permissions', [], ['fragment' => 'module-tfa'])->toString(),
            ]
          ),
        ];
      }
    }

    $form['tfa_allowed_validation_plugins'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed Validation plugins'),
      '#options' => $validation_plugins_labels,
      '#default_value' => $config->get('allowed_validation_plugins') ?: ['tfa_totp'],
      '#description' => $this->t('Plugins that can be setup by users for various TFA processes.'),
      // Show only when TFA is enabled.
      '#states' => $enabled_state,
      '#required' => TRUE,
    ];
    $form['tfa_default_validation_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Validation plugin'),
      '#options' => $validation_plugins_labels,
      '#default_value' => $config->get('default_validation_plugin') ?: 'tfa_totp',
      '#description' => $this->t('Plugin that will be used as the default TFA process.'),
      // Show only when TFA is enabled.
      '#states' => $enabled_state,
      '#required' => TRUE,
    ];

    // Validation plugin related settings.
    // $validation_plugins_labels has the plugin ids as the key.
    $form['validation_plugin_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Validation Settings'),
      '#tree' => TRUE,
      '#states' => $enabled_state,
    ];

    foreach ($validation_plugins_labels as $key => $val) {
      $instance = $this->pluginManager->createInstance($key, [
        'uid' => $this->currentUser()->id(),
      ]);

      if (method_exists($instance, 'buildConfigurationForm')) {
        $validation_enabled_state = [
          'visible' => [
            [
              ':input[name="tfa_enabled"]' => ['checked' => TRUE],
              ':input[name="tfa_allowed_validation_plugins[' . $key . ']"]' => ['checked' => TRUE],
            ],
            [
              'select[name="tfa_default_validation_plugin"]' => ['value' => $key],
            ],
          ],
        ];
        $form['validation_plugin_settings'][$key . '_container'] = [
          '#type' => 'container',
          '#states' => $validation_enabled_state,
        ];
        $form['validation_plugin_settings'][$key . '_container']['title'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $val,
        ];
        $form['validation_plugin_settings'][$key . '_container']['form'] = $instance->buildConfigurationForm();
        $form['validation_plugin_settings'][$key . '_container']['form']['#parents'] = [
          'validation_plugin_settings',
          $key,
        ];
      }
    }

    // The encryption profiles select box.
    $encryption_profile_labels = [];
    foreach ($encryption_profiles as $encryption_profile) {
      $encryption_profile_labels[$encryption_profile->id()] = $encryption_profile->label();
    }
    $form['encryption_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption Profile'),
      '#options' => $encryption_profile_labels,
      '#description' => $this->t('Encryption profiles to encrypt the secret'),
      '#default_value' => $config->get('encryption'),
      '#states' => $enabled_state,
      '#required' => TRUE,
    ];

    $form['users_without_tfa'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Settings for users who have not set up TFA.'),
      '#tree' => TRUE,
      '#states' => $enabled_state,
    ];

    $skip_value = $config->get('validation_skip');
    $form['users_without_tfa']['validation_skip'] = [
      '#type' => 'number',
      '#title' => $this->t('Skip Validation'),
      '#default_value' => $skip_value ?? 3,
      '#description' => $this->t('No. of times a user without having setup tfa validation can login.'),
      '#min' => 0,
      '#max' => 99,
      '#size' => 2,
      '#required' => TRUE,
    ];

    // Redirect users on login to TFA Setup Page.
    $form['users_without_tfa']['redirect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect users on login to TFA Setup Page'),
      '#default_value' => $config->get('users_without_tfa_redirect') ?? FALSE,
      '#description' => $this->t('If the user has the "setup own tfa" permission and has not yet configured TFA they will be redirected to the TFA overview page after login.'),
    ];

    // Enable login plugins.
    $login_form_array = [];

    foreach ($login_plugins as $login_plugin) {
      $id = $login_plugin['id'];
      $title = $login_plugin['label']->render();
      $login_form_array[$id] = (string) $title;
    }

    $form['tfa_login'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Login plugins'),
      '#options' => $login_form_array,
      '#default_value' => ($config->get('login_plugins')) ? $config->get('login_plugins') : [],
      '#description' => $this->t('Plugins that can allow a user to skip the TFA process. If any plugin returns true the user will not be required to follow TFA. <strong>Use with caution.</strong>'),
      '#states' => $enabled_state,
    ];

    // Login plugin related settings.
    // $login_form_array has the plugin ids as the key.
    $form['login_plugin_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Login Settings'),
      '#tree' => TRUE,
      '#states' => $enabled_state,
    ];
    foreach ($login_form_array as $key => $val) {
      $instance = $this->pluginManager->createInstance($key, [
        'uid' => $this->currentUser()->id(),
      ]);

      if (method_exists($instance, 'buildConfigurationForm')) {
        $login_enabled_state = [
          'visible' => [
            [
              ':input[name="tfa_enabled"]' => ['checked' => TRUE],
              ':input[name="tfa_login[' . $key . ']"]' => ['checked' => TRUE],
            ],
          ],
        ];
        $form['login_plugin_settings'][$key . '_container'] = [
          '#type' => 'container',
          '#states' => $login_enabled_state,
        ];
        $form['login_plugin_settings'][$key . '_container']['title'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $val,
        ];
        $form['login_plugin_settings'][$key . '_container']['form'] = $instance->buildConfigurationForm();
        $form['login_plugin_settings'][$key . '_container']['form']['#parents'] = [
          'login_plugin_settings',
          $key,
        ];
      }
    }

    // Enable send plugins.
    if (count($send_plugins)) {
      $send_form_array = [];

      foreach ($send_plugins as $send_plugin) {
        $id = $send_plugin['id'];
        $title = $send_plugin['label']->render();
        $send_form_array[$id] = (string) $title;
      }

      $form['tfa_send'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Send plugins'),
        '#options' => $send_form_array,
        '#default_value' => ($config->get('send_plugins')) ? $config->get('send_plugins') : [],
        '#description' => $this->t('TFA Send Plugins, like TFA Twilio'),
      ];
    }

    $form['tfa_flood'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('TFA Flood Settings'),
      '#states' => $enabled_state,
    ];

    // Flood control identifier.
    $form['tfa_flood']['tfa_flood_uid_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Flood Control With UID Only'),
      '#default_value' => ($config->get('tfa_flood_uid_only')) ?: 0,
      '#description' => $this->t('Flood control on the basis of uid only.'),
    ];

    // Flood window. Defaults to 5min.
    $form['tfa_flood']['tfa_flood_window'] = [
      '#type' => 'number',
      '#title' => $this->t('TFA Flood Window'),
      '#default_value' => ($config->get('tfa_flood_window')) ?: 300,
      '#description' => $this->t('TFA Flood Window.'),
      '#min' => 1,
      '#size' => 5,
      '#required' => TRUE,
    ];

    // Flood threshold. Defaults to 6 failed attempts.
    $form['tfa_flood']['tfa_flood_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('TFA Flood Threshold'),
      '#default_value' => ($config->get('tfa_flood_threshold')) ?: 6,
      '#description' => $this->t('TFA Flood Threshold.'),
      '#min' => 1,
      '#size' => 2,
      '#required' => TRUE,
    ];

    // Email configurations.
    if ($config->get('mail') === NULL) {
      $message = $this->t('Email settings missing. If this is the first time you are seeing this error after upgrading the TFA module, then please make sure you have run the required @update_link function.', [
        '@update_link' => Link::createFromRoute('update', 'system.status')->toString(),
      ]);
      $this->messenger()->addError($message);
    }
    $form['mail'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Emails'),
      '#default_tab' => 'edit-tfa-enabled-configuration',
    ];
    $form['tfa_enabled_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('User enabled TFA validation method'),
      '#description' => $this->t('This email is sent to the user when they enable a TFA validation method on their account. Available tokens are: [site] and [user]. Common variables are: [site:name], [site:url], [user:display-name], [user:account-name], and [user:mail].'),
      '#group' => 'mail',
      'tfa_enabled_configuration_subject' => [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $config->get('mail.tfa_enabled_configuration.subject'),
        '#required' => TRUE,
      ],
      'tfa_enabled_configuration_body' => [
        '#type' => 'textarea',
        '#title' => $this->t('Body'),
        '#default_value' => $config->get('mail.tfa_enabled_configuration.body'),
        '#required' => TRUE,
        '#attributes' => [
          'rows' => 10,
        ],
      ],
    ];
    $form['tfa_disabled_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('User disabled TFA validation method'),
      '#description' => $this->t('This email is sent to the user when they disable a TFA validation method on their account. Available tokens are: [site] and [user]. Common variables are: [site:name], [site:url], [user:display-name], [user:account-name], and [user:mail].'),
      '#group' => 'mail',
      'tfa_disabled_configuration_subject' => [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $config->get('mail.tfa_disabled_configuration.subject'),
        '#required' => TRUE,
      ],
      'tfa_disabled_configuration_body' => [
        '#type' => 'textarea',
        '#title' => $this->t('Body'),
        '#default_value' => $config->get('mail.tfa_disabled_configuration.body'),
        '#required' => TRUE,
        '#attributes' => [
          'rows' => 10,
        ],
      ],
    ];
    $form['help_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Help text'),
      '#description' => $this->t('Text to display when a user is locked out and blocked from logging in.'),
      '#default_value' => $config->get('help_text'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $default_validation_plugin = $form_state->getValue('tfa_default_validation_plugin');
    $allowed_validation_plugins = $form_state->getValue('tfa_allowed_validation_plugins');
    // Default validation plugin must always be allowed.
    $allowed_validation_plugins[$default_validation_plugin] = $default_validation_plugin;

    // Delete tfa data if plugin is disabled.
    if ($this->config('tfa.settings')->get('enabled') && !$form_state->getValue('tfa_enabled')) {
      $this->userData->delete('tfa');
    }

    $send_plugins = $form_state->getValue('tfa_send') ?: [];
    $login_plugins = $form_state->getValue('tfa_login') ?: [];
    $this->config('tfa.settings')
      ->set('enabled', $form_state->getValue('tfa_enabled'))
      ->set('required_roles', $form_state->getValue('tfa_required_roles'))
      ->set('send_plugins', array_filter($send_plugins))
      ->set('login_plugins', array_filter($login_plugins))
      ->set('login_plugin_settings', $form_state->getValue('login_plugin_settings'))
      ->set('allowed_validation_plugins', array_filter($allowed_validation_plugins))
      ->set('default_validation_plugin', $default_validation_plugin)
      ->set('validation_plugin_settings', $form_state->getValue('validation_plugin_settings'))
      ->set('validation_skip', $form_state->getValue(['users_without_tfa', 'validation_skip']))
      ->set('users_without_tfa_redirect', $form_state->getValue(['users_without_tfa', 'redirect']))
      ->set('encryption', $form_state->getValue('encryption_profile'))
      ->set('tfa_flood_uid_only', $form_state->getValue('tfa_flood_uid_only'))
      ->set('tfa_flood_window', $form_state->getValue('tfa_flood_window'))
      ->set('tfa_flood_threshold', $form_state->getValue('tfa_flood_threshold'))
      ->set('mail.tfa_enabled_configuration.subject', $form_state->getValue('tfa_enabled_configuration_subject'))
      ->set('mail.tfa_enabled_configuration.body', $form_state->getValue('tfa_enabled_configuration_body'))
      ->set('mail.tfa_disabled_configuration.subject', $form_state->getValue('tfa_disabled_configuration_subject'))
      ->set('mail.tfa_disabled_configuration.body', $form_state->getValue('tfa_disabled_configuration_body'))
      ->set('help_text', $form_state->getValue('help_text'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
