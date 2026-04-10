<?php

namespace Drupal\tfa\Plugin\Tfa;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\tfa\Attribute\Tfa;
use Drupal\tfa\Plugin\TfaSetupInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaBasePlugin;
use Drupal\tfa\TfaRandomTrait;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Recovery validation class for performing recovery codes validation.
 */
#[Tfa(
  "tfa_recovery_code",
  new TranslatableMarkup("TFA Recovery Code"),
  new TranslatableMarkup("TFA Recovery Code Validation Plugin"),
  [],
  [
    "saved" => new TranslatableMarkup("Recovery codes saved."),
    "skipped" => new TranslatableMarkup("Recovery codes not saved."),
  ]
 )]
final class TfaRecoveryCode extends TfaBasePlugin implements TfaValidationInterface, TfaSetupInterface, ContainerFactoryPluginInterface {
  use TfaRandomTrait;

  /**
   * The number of recovery codes to generate.
   *
   * @var int
   */
  protected int $codeLimit = 10;

  /**
   * Encryption profile.
   *
   * @var \Drupal\encrypt\EncryptionProfileInterface|null
   */
  protected ?EncryptionProfileInterface $encryptionProfile;

  /**
   * Encryption service.
   *
   * @var \Drupal\encrypt\EncryptServiceInterface
   */
  protected EncryptServiceInterface $encryptService;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Constructs a new Tfa plugin object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\user\UserDataInterface $user_data
   *   User data object to store user specific information.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryption_profile_manager
   *   Encryption profile manager.
   * @param \Drupal\encrypt\EncryptServiceInterface $encrypt_service
   *   Encryption service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user, LockBackendInterface $lock) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->userData = $user_data;
    $this->encryptionProfile = $encryption_profile_manager->getEncryptionProfile($config_factory->get('tfa.settings')->get('encryption'));
    $this->encryptService = $encrypt_service;
    $codes_amount = $config_factory->get('tfa.settings')->get('validation_plugin_settings.tfa_recovery_code.recovery_codes_amount');
    if (!empty($codes_amount)) {
      $this->codeLimit = $codes_amount;
    }
    $this->currentUser = $current_user;
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.data'),
      $container->get('encrypt.encryption_profile.manager'),
      $container->get('encryption'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('lock')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function ready(): bool {
    $codes = $this->getCodes();
    return !empty($codes);
  }

  /**
   * Check if account has access to the user plugin configuration.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route
   *   The route to be checked.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting access.
   *
   * @return bool
   *   Returns true if the access is allowed.
   */
  public function allowUserSetupAccess(RouteMatchInterface $route, AccountInterface $account): bool {
    // Only allow user setup access to the 'show codes' if user is self.
    $route_name = $route->getRouteName();
    $is_recovery_code_view = ($route_name === 'tfa.plugin.reset' && $route->getParameter('reset') !== "1");
    $admin_is_allowed = (
      $route_name !== 'tfa.validation.setup'
      && !$is_recovery_code_view
    );
    return (($this->uid === $account->id()) || $admin_is_allowed);
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state): array {
    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter one of your recovery codes'),
      '#required' => TRUE,
      '#description' => $this->t('Recovery codes were generated when you first set up TFA. Format: XXX XXX XXX'),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['login'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Verify'),
    ];
    return $form;
  }

  /**
   * Configuration form for the recovery code plugin.
   *
   * @return array
   *   Form array specific for this validation plugin.
   */
  public function buildConfigurationForm(): array {
    $settings_form['recovery_codes_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Recovery Codes Amount'),
      '#default_value' => $this->codeLimit,
      '#description' => $this->t('Number of Recovery Codes To Generate.'),
      '#min' => 1,
      '#size' => 2,
      '#required' => TRUE,
    ];

    return $settings_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state): bool {
    $values = $form_state->getValues();
    return $this->validate($values['code']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateRequest(#[\SensitiveParameter] string $code): bool {
    if ($this->validate($code)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Generate an array of secure recovery codes.
   *
   * @return array
   *   An array of randomly generated codes.
   *
   * @throws \Exception
   */
  public function generateCodes(): array {
    $codes = [];

    for ($i = 0; $i < $this->codeLimit; $i++) {
      $codes[] = $this->randomCharacters(9, '1234567890');
    }

    return $codes;
  }

  /**
   * Get unused recovery codes.
   *
   * @todo consider returning used codes so validate() can error with
   * appropriate message
   *
   * @return array
   *   Array of codes indexed by ID.
   *
   * @throws \Drupal\encrypt\Exception\EncryptionMethodCanNotDecryptException
   * @throws \Drupal\encrypt\Exception\EncryptException
   */
  public function getCodes(): array {
    $codes = $this->getUserData('tfa', $this->pluginId, $this->uid) ?: [];
    array_walk($codes, function (&$v, $k) {
      $v = $this->encryptService->decrypt($v, $this->encryptionProfile);
    });
    return $codes;
  }

  /**
   * Save recovery codes for current account.
   *
   * @param array $codes
   *   Recovery codes for current account.
   *
   * @throws \Drupal\encrypt\Exception\EncryptException
   */
  public function storeCodes(array $codes): void {
    $this->deleteCodes();

    // Encrypt code for storage.
    array_walk($codes, function (&$v, $k) {
      $v = $this->encryptService->encrypt($v, $this->encryptionProfile);
    });
    $data = [$this->pluginId => $codes];

    $this->setUserData('tfa', $data, $this->uid);
  }

  /**
   * Delete existing codes.
   */
  protected function deleteCodes(): void {
    // Delete any existing codes.
    $this->deleteUserData('tfa', $this->pluginId, $this->uid);
  }

  /**
   * {@inheritdoc}
   */
  protected function validate(string $code): bool {
    $recovery_validation_lock_id = 'tfa_validation_recovery_codes_' . $this->uid;
    while (!$this->lock->acquire($recovery_validation_lock_id)) {
      $this->lock->wait($recovery_validation_lock_id);
    }
    // Get codes and compare.
    $codes = $this->getCodes();
    if (empty($codes)) {
      $this->lock->release($recovery_validation_lock_id);
      $this->errorMessages['recovery_code'] = $this->t('You have no unused codes available.');
      return FALSE;
    }
    // Remove empty spaces.
    $code = str_replace(' ', '', $code);
    foreach ($codes as $id => $stored) {
      // Remove spaces from stored code.
      if (hash_equals(trim(str_replace(' ', '', $stored)), $code)) {
        unset($codes[$id]);
        $this->storeCodes($codes);
        $this->lock->release($recovery_validation_lock_id);
        return TRUE;
      }
    }
    $this->lock->release($recovery_validation_lock_id);
    $this->errorMessages['recovery_code'] = $this->t('Invalid recovery code.');
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function tokenLength(#[\SensitiveParameter]string $password): int {
    // Tokens are always 9 digits long.
    return 9;
  }

  /* ================================== SETUP ================================== */

  /**
   * {@inheritdoc}
   */
  public function getOverview(array $params): array {
    $ret = [
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Recovery Codes'),
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Generate one-time use codes for two-factor login. These are generally used to recover your account in case you lose access to another 2nd-factor device.'),
      ],
      'setup' => [
        '#theme' => 'links',
        '#links' => [
          'reset' => [
            'title' => !$params['enabled'] ? $this->t('Generate codes') : $this->t('Reset codes'),
            'url' => Url::fromRoute('tfa.plugin.reset', [
              'user' => $params['account']->id(),
              'method' => $params['plugin_id'],
              'reset' => 1,
            ]),
          ],
        ],
      ],
      'show_codes' => [
        '#theme' => 'links',
        '#access' => $params['enabled'],
        '#links' => [
          'show' => [
            'title' => $this->t('Show codes'),
            'url' => Url::fromRoute('tfa.validation.setup', [
              'user' => $params['account']->id(),
              'method' => $params['plugin_id'],
            ]),
          ],
        ],
      ],
    ];

    // Don't show codes to other users.
    if ((int) $this->currentUser->id() !== (int) $this->uid) {
      unset($ret['show_codes']);
    }

    return $ret;
  }

  /**
   * Get the setup form for the validation method.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int $reset
   *   Whether or not the user is resetting the application.
   *
   * @return array
   *   Form API array.
   */
  public function getSetupForm(array $form, FormStateInterface $form_state, int $reset = 0): array {
    $codes = $this->getCodes();

    // If $reset has a value, we're setting up new codes.
    if (!empty($reset)) {
      $codes = $this->generateCodes();

      // Make the human friendly.
      foreach ($codes as $key => $code) {
        $codes[$key] = implode(' ', str_split($code, 3));
      }
      $form['recovery_codes'] = [
        '#type' => 'value',
        '#value' => $codes,
      ];
    }

    $form['recovery_codes_output'] = [
      '#title' => $this->t('Recovery Codes'),
      '#theme' => 'item_list',
      '#items' => $codes,
    ];
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Print or copy these codes and store them somewhere safe before continuing.'),
    ];

    if (!empty($reset)) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['save'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Save codes to account'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state): bool {
    if (!empty($form_state->getValue('recovery_codes'))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitSetupForm(array $form, FormStateInterface $form_state): bool {
    $this->storeCodes($form_state->getValue('recovery_codes'));
    return TRUE;
  }

}
