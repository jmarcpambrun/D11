<?php

namespace Drupal\tfa\Plugin\Tfa;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Exception\EncryptException;
use Drupal\tfa\Attribute\Tfa;
use Drupal\tfa\Plugin\TfaSetupInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaBasePlugin;
use Drupal\user\UserDataInterface;
use Drupal\user\UserStorageInterface;
use OTPHP\TOTP;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * TOTP validation class for performing TOTP validation.
 *
 * @property int<6> $codeLength
 */
#[Tfa(
  "tfa_totp",
  new TranslatableMarkup("TFA Time-based one-time password (TOTP)"),
  new TranslatableMarkup("TFA TOTP Validation Plugin"),
  [
    "Google Authenticator (Android)" => "https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2",
    "Google Authenticator (iOS)" => "https://apps.apple.com/us/app/google-authenticator/id388497605",
    "Microsoft Authenticator (Android/iOS)" => "https://www.microsoft.com/en-us/security/mobile-authenticator-app",
    "Twilio Authy (Android/iOS)" => "https://authy.com",
    "FreeOTP (Android/iOS)" => "https://freeotp.github.io",
    "GAuth Authenticator (Desktop)" => "https://github.com/gbraadnl/gauth",
  ],
  [
    "saved" => new TranslatableMarkup("Application code verified."),
    "skipped" => new TranslatableMarkup("Application codes not enabled."),
  ],
)]
final class TfaTotp extends TfaBasePlugin implements TfaValidationInterface, TfaSetupInterface, ContainerFactoryPluginInterface {

  /**
   * The time-window in which the validation should be done.
   *
   * @var int
   */
  protected int $timeSkew;

  /**
   * Whether or not the prefix should use the site name.
   *
   * @var bool
   */
  protected bool $siteNamePrefix;

  /**
   * Name prefix.
   *
   * @var string
   */
  protected string $namePrefix;

  /**
   * Configurable name of the issuer.
   *
   * @var non-empty-string
   */
  protected string $issuer;

  /**
   * The Datetime service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * Un-encrypted seed.
   *
   * @var ?non-empty-string
   */
  protected ?string $seed = NULL;

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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service, ConfigFactoryInterface $config_factory, TimeInterface $time, UserStorageInterface $user_storage, LockBackendInterface $lock) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Allow codes within tolerance range of 2 * 30 second units.
    $plugin_settings = $config_factory->get('tfa.settings')->get('validation_plugin_settings');
    $settings = $plugin_settings[$plugin_id] ?? [];
    $settings = array_replace([
      'time_skew' => 2,
      'site_name_prefix' => TRUE,
      'name_prefix' => 'TFA',
      'issuer' => 'Drupal',
    ], $settings);

    $this->userData = $user_data;
    $this->timeSkew = $settings['time_skew'];
    $this->siteNamePrefix = $settings['site_name_prefix'];
    $this->namePrefix = $settings['name_prefix'];
    $this->issuer = !empty($settings['issuer']) && is_string($settings['issuer']) ? $settings['issuer'] : 'Drupal';
    $this->time = $time;
    $this->userStorage = $user_storage;
    $this->lock = $lock;

    $this->encryptionProfile = $encryption_profile_manager->getEncryptionProfile($config_factory->get('tfa.settings')->get('encryption'));
    $this->encryptService = $encrypt_service;
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
      $container->get('datetime.time'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('lock')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function ready(): bool {
    return ($this->getSeed() !== FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state): array {
    $message = $this->t('Verification code is application generated and @length digits long.', ['@length' => $this->codeLength]);
    if ($this->getUserData('tfa', 'tfa_recovery_code', $this->uid)) {
      $message .= '<br/>' . $this->t("Can't access your account? Use one of your recovery codes.");
    }
    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application verification code'),
      '#description' => $message,
      '#required'  => TRUE,
      '#attributes' => [
        'autocomplete' => 'off',
        'autofocus' => 'autofocus',
      ],
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
   * The configuration form for this validation plugin.
   *
   * @return array
   *   Form array specific for this validation plugin.
   */
  public function buildConfigurationForm(): array {
    $settings_form['time_skew'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of Accepted Codes'),
      '#default_value' => $this->timeSkew,
      '#description' => $this->t('Number of past codes to consider valid. Codes are generated every 30 seconds, so setting this value to 10 would allow each code to work for five minutes.'),
      '#size' => 2,
      '#required' => TRUE,
    ];

    $settings_form['site_name_prefix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use site name as OTP QR code name prefix.'),
      '#default_value' => $this->siteNamePrefix,
      '#description' => $this->t('If checked, the site name will be used instead of a static string. This can be useful for multi-site installations.'),
    ];

    $settings_form['name_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OTP QR Code Prefix'),
      '#default_value' => $this->namePrefix ?? 'tfa',
      '#description' => $this->t('Prefix for OTP QR code names. Suffix is account username.'),
      '#size' => 15,
      '#states' => [
        'visible' => [':input[name="validation_plugin_settings[' . $this->pluginId . '][site_name_prefix]"]' => ['checked' => FALSE]],
      ],
    ];

    $settings_form['issuer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Issuer'),
      '#default_value' => $this->issuer,
      '#description' => $this->t('The provider or service this account is associated with.'),
      '#size' => 15,
      '#required' => TRUE,
    ];

    return $settings_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state): bool {
    $values = $form_state->getValues();
    $totp_validation_lock_id = 'tfa_validation_totp_' . $this->uid;
    while (!$this->lock->acquire($totp_validation_lock_id)) {
      $this->lock->wait($totp_validation_lock_id);
    }
    if (!$this->validate($values['code'])) {
      $this->lock->release($totp_validation_lock_id);
      $this->errorMessages['code'] = $this->t('Invalid application code. Please try again.');
      return FALSE;
    }
    else {
      $this->lock->release($totp_validation_lock_id);
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateRequest(#[\SensitiveParameter] string $code): bool {
    $totp_validation_lock_id = 'tfa_validation_totp_' . $this->uid;
    while (!$this->lock->acquire($totp_validation_lock_id)) {
      $this->lock->wait($totp_validation_lock_id);
    }
    if ($this->validate($code)) {
      $this->lock->release($totp_validation_lock_id);
      return TRUE;
    }
    $this->lock->release($totp_validation_lock_id);
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function validate($code): bool {
    // Strip whitespace.
    $code = preg_replace('/\s+/', '', $code);

    if (empty($code)) {
      return FALSE;
    }

    // Get OTP seed.
    $seed = $this->getSeed();
    if ($seed == FALSE) {
      return FALSE;
    }
    $validator = TOTP::createFromSecret($seed);
    $validator->setDigits($this->codeLength);

    // Get the last validated time window.
    $last_accepted_window = $this->getUserData('tfa', 'tfa_totp_time_window', $this->uid) ?: 1;

    $process_start_time = $this->time->getRequestTime();
    for ($i = -$this->timeSkew; $i <= $this->timeSkew; $i++) {
      $check_timestamp = max($process_start_time + ($validator->getPeriod() * $i), 0);
      $check_time_window = (int) floor($check_timestamp / $validator->getPeriod());
      if (!$validator->verify($code, $check_timestamp)) {
        // Code doesn't validate try the next window.
        continue;
      }

      if ($check_time_window > $last_accepted_window) {
        // Code is newer than last accepted code.
        $this->setUserData('tfa', ['tfa_totp_time_window' => $check_time_window], $this->uid);
        return TRUE;
      }
      return FALSE;
    }
    return FALSE;
  }

  /**
   * Get seed for this account.
   *
   * @return non-empty-string|false
   *   Decrypted account OTP seed or FALSE if none exists.
   */
  protected function getSeed(): string|FALSE {
    // A memory based seed is used for the setup form.
    if ($this->seed !== NULL) {
      return $this->seed;
    }

    // Lookup seed for account and decrypt.
    $result = $this->getUserData('tfa', $this->pluginId . '_seed', $this->uid);

    if (!empty($result)) {
      $encrypted = base64_decode($result['seed']);
      $seed = $this->encryptService->decrypt($encrypted, $this->encryptionProfile);
      if (!empty($seed)) {
        return $seed;
      }
    }
    return FALSE;
  }

  /**
   * Save seed for account.
   *
   * @param string $seed
   *   Un-encrypted seed.
   *
   * @throws \Drupal\encrypt\Exception\EncryptException
   *   Can throw an EncryptException.
   */
  public function storeSeed(string $seed): void {
    // Encrypt seed for storage.
    $encrypted = $this->encryptService->encrypt($seed, $this->encryptionProfile);

    // Until EncryptServiceInterface::encrypt enforces a non-empty string,
    // validate return value is a non-empty string. \base64_encode() below must
    // also only receive a string.
    if (!is_string($encrypted) || strlen($encrypted) === 0) {
      throw new EncryptException('Empty encryption value received from encryption service.');
    }

    $record = [
      $this->pluginId . '_seed' => [
        'seed' => base64_encode($encrypted),
        'created' => $this->time->getRequestTime(),
      ],
    ];

    $this->setUserData('tfa', $record, $this->uid);
  }

  /**
   * Delete the seed of the current validated user.
   */
  protected function deleteSeed(): void {
    $this->deleteUserData('tfa', $this->pluginId . '_seed', $this->uid);
  }

  /**
   * Get the value of the time-window in which the validation should be done.
   *
   * @return int
   *   The current value of the time skew.
   */
  public function getTimeSkew(): int {
    return $this->timeSkew;
  }

  /* ================================== SETUP ================================== */

  /**
   * {@inheritdoc}
   */
  public function getSetupForm(array $form, FormStateInterface $form_state): array {
    $this->setSeed($this->createSeed());

    $help_links = $this->getHelpLinks();

    $items = [];
    foreach ($help_links as $item => $link) {
      $items[] = Link::fromTextAndUrl($item, Url::fromUri($link, ['attributes' => ['target' => '_blank']]));
    }

    $form['apps'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Install authentication code application on your mobile or desktop device:'),
    ];
    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('The two-factor authentication application will be used during this setup and for generating codes during regular authentication. If the application supports it, scan the QR code below to get the setup code otherwise you can manually enter the text code.'),
    ];
    $form['seed'] = [
      '#type' => 'textfield',
      '#value' => $this->getSeed(),
      '#disabled' => TRUE,
      '#description' => $this->t('Enter this code into your two-factor authentication app or scan the QR code below.'),
    ];

    // QR image of seed.
    $form['qr_image'] = [
      '#prefix' => '<div class="tfa-qr-code"',
      '#theme' => 'image',
      '#uri' => $this->getQrCodeUri(),
      '#alt' => $this->t('QR code for TFA setup'),
      '#suffix' => '</div>',
    ];

    // QR code css giving it a fixed width.
    $form['page']['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => ".tfa-qr-code { width:200px }",
      ],
      // cSpell:disable-next-line qrcode
      'qrcode-css',
    ];

    // Include code entry form.
    $form = $this->getForm($form, $form_state);
    $form['actions']['login']['#value'] = $this->t('Verify and save');
    // Alter code description.
    $form['code']['#description'] = $this->t('A verification code will be generated after you scan the above QR code or manually enter the setup code. The verification code is six digits long.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state): bool {
    if (!$this->validate($form_state->getValue('code'))) {
      $this->errorMessages['code'] = $this->t('Invalid application code. Please try again.');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitSetupForm(array $form, FormStateInterface $form_state): bool {
    // Write seed for user.
    if ($this->seed == NULL) {
      return FALSE;
    }
    try {
      $this->storeSeed($this->seed);
      return TRUE;
    }
    catch (EncryptException $e) {
    }
    return FALSE;
  }

  /**
   * Get a base64 QR code image uri of seed.
   *
   * @return string
   *   QR-code uri.
   */
  protected function getQrCodeUri(): string {
    $seed = $this->getSeed();
    if (!$seed) {
      $seed = $this->createSeed();
      $this->setSeed($seed);
    }
    $token = TOTP::createFromSecret($seed);
    $token->setDigits($this->codeLength);
    $token->setLabel($this->getTokenLabel());
    $token->setIssuer($this->issuer);
    $qr_options = new QROptions();
    $qr_options->imageTransparent = FALSE;
    return(new QRCode($qr_options))->render($token->getProvisioningUri());
  }

  /**
   * Create OTP seed for account.
   *
   * @return non-empty-string
   *   Un-encrypted seed.
   */
  protected function createSeed() {
    return TOTP::generate()->getSecret();
  }

  /**
   * Setter for OTP secret key.
   *
   * @param non-empty-string $seed
   *   The OTP secret key.
   */
  public function setSeed(string $seed): void {
    $this->seed = $seed;
  }

  /**
   * Get label for QR image.
   *
   * @return non-empty-string
   *   String to be used as label. Contains non-sanitized account name.
   */
  protected function getTokenLabel(): string {
    /** @var \Drupal\user\Entity\User $account */
    $account = $this->userStorage->load($this->configuration['uid']);
    $prefix = $this->siteNamePrefix ? preg_replace('@[^a-z0-9-:]+@', '-', strtolower(\Drupal::config('system.site')->get('name'))) : $this->namePrefix;
    $prefix = !empty($prefix) ? $prefix . '-' : '';
    $full_label = $prefix . $account->getAccountName();
    return !empty($full_label) ? $full_label : 'TOTP Token';
  }

  /**
   * {@inheritdoc}
   */
  public function getOverview(array $params): array {
    $plugin_text = $this->t('Validation Plugin: @plugin',
      [
        '@plugin' => str_replace(' Setup', '', $this->getLabel()),
      ]
    );
    $output = [
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('TFA application'),
      ],
      'validation_plugin' => [
        '#type' => 'markup',
        '#markup' => '<p>' . $plugin_text . '</p>',
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Generate verification codes from a mobile or desktop application.'),
      ],
      'link' => [
        '#theme' => 'links',
        '#links' => [
          'admin' => [
            'title' => !$params['enabled'] ? $this->t('Set up application') : $this->t('Reset application'),
            'url' => Url::fromRoute('tfa.validation.setup', [
              'user' => $params['account']->id(),
              'method' => $params['plugin_id'],
            ]),
          ],
        ],
      ],
    ];
    return $output;
  }

}
