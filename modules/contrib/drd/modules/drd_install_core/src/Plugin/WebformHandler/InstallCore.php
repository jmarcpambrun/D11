<?php

namespace Drupal\drd_install_core\Plugin\WebformHandler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Entity\Core;
use Drupal\drd\Entity\Domain;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform submission handler to install a new DRD core.
 *
 * @WebformHandler(
 *   id = "drd_core",
 *   label = @Translation("DRD Core Installer"),
 *   category = @Translation("External"),
 *   description = @Translation("Install a new DRD core after submission."),
 *   cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 *   tokens = TRUE,
 * )
 */
final class InstallCore extends WebformHandlerBase {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): InstallCore {
    return new InstallCore(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_user_id' => '',
      'field_host_id' => '',
      'field_core_id' => '',
      'field_domain_secrets' => '',
      'field_site_name' => '',
      'field_site_url' => '',
      'drupal_root' => '',
      'http_header' => '',
      'shared_secret' => '',
      'openssl_cipher' => '',
      'openssl_password' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $webform = $this->getWebform();
    $fields = [];
    foreach ($webform->getElementsInitializedAndFlattened() as $item) {
      $fields[$item['#webform_key']] = $item['#title'];
    }

    $form['field_user_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Field with user ID'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_user_id'],
    ];
    $form['field_host_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Field with host ID'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_host_id'],
    ];
    $form['field_core_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Field to store Core ID'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_core_id'],
    ];
    $form['field_domain_secrets'] = [
      '#type' => 'select',
      '#title' => $this->t('Field to store Domain secrets'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_domain_secrets'],
    ];
    $form['field_site_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Field with site name'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_site_name'],
    ];
    $form['field_site_url'] = [
      '#type' => 'select',
      '#title' => $this->t('Field with site URL'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_site_url'],
    ];
    $form['drupal_root'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal root directory'),
      '#default_value' => $this->configuration['drupal_root'],
    ];
    $form['http_header'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Headers for HTTP request'),
      '#default_value' => $this->configuration['http_header'],
    ];
    $form['shared_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shared secret'),
      '#default_value' => $this->configuration['shared_secret'],
    ];
    $form['openssl_cipher'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenSSL Cipher'),
      '#default_value' => $this->configuration['openssl_cipher'],
    ];
    $form['openssl_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenSSL Password'),
      '#default_value' => $this->configuration['openssl_password'],
    ];

    $this->elementTokenValidate($form);

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
    if (!$update && $webform_submission->getState() === WebformSubmissionInterface::STATE_COMPLETED) {
      // Save drd_core and drd_domain entities.
      $data = $webform_submission->getData();

      /** @var \Drupal\drd\Entity\CoreInterface $core */
      $core = Core::create([
        'user_id' => $data[$this->configuration['field_user_id']],
        'name' => $data[$this->configuration['field_site_name']],
        'status' => 1,
        'host' => $data[$this->configuration['field_host_id']],
      ]);
      try {
        if ($core->save()) {
          $data[$this->configuration['field_core_id']] = $core->id();
          $webform_submission->setData($data);
          $core->set('drupalroot', $this->replaceTokens(rtrim($this->configuration['drupal_root'], ' /'), $webform_submission));
          $core->save();

          $uri = $this->replaceTokens(trim($data[$this->configuration['field_site_url']], ' /'), $webform_submission);
          $values = [];
          $domain = Domain::instanceFromUrl($core, $uri, $values);
          $domain->setName($data[$this->configuration['field_site_name']]);
          $header = [];
          foreach (Yaml::decode($this->configuration['http_header']) as $key => $value) {
            $header[] = [
              'key' => $key,
              'value' => $value,
            ];
          }
          $domain->set('header', $header);
          $domain->set('installed', 1);
          $domain->set('auth', 'shared_secret');
          $domain->setAuthSetting([
            'shared_secret' => [
              'secret' => $this->configuration['shared_secret'],
            ],
          ]);
          $domain->set('crypt', 'OpenSsl');
          $domain->setCryptSetting([
            'OpenSsl' => [
              'cipher' => $this->configuration['openssl_cipher'],
              'password' => $this->configuration['openssl_password'],
            ],
          ]);
          $domain->save();
          $data[$this->configuration['field_domain_secrets']] = json_encode([
            'authorised' => [
              $domain->uuid() => [
                'uuid' => $domain->uuid(),
                'auth' => 'shared_secret',
                'authsetting' => [
                  'secret' => $this->configuration['shared_secret'],
                ],
                'crypt' => 'OpenSsl',
                'cryptsetting' => [
                  'cipher' => $this->configuration['openssl_cipher'],
                  'password' => $this->configuration['openssl_password'],
                ],
                'redirect' => '',
                'drdips' => [
                  'v4' => [],
                  'v6' => [],
                ],
                'timestamp' => $this->time->getRequestTime(),
                'ip' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
              ],
            ],
            'ott' => [],

          ], JSON_THROW_ON_ERROR);
          $webform_submission->setData($data);
        }

        $webform_submission->resave();
      }
      catch (EntityStorageException | \Exception) {
        // @todo Log this exception.
      }
    }
  }

}
