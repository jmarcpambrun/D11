<?php

namespace Drupal\protect_form_flood_control;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Manager class that handles flood-protecting forms.
 */
class Manager implements ManagerInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Flood\FloodInterface definition.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Component\Datetime\TimeInterface definition.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Drupal\Core\Datetime\DateFormatterInterface definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\Path\PathMatcherInterface definition.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The module configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Manager constructor.
   *
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(FloodInterface $flood, ConfigFactoryInterface $config_factory, TimeInterface $time, DateFormatterInterface $date_formatter, RequestStack $request_stack, AccountProxyInterface $current_user, PathMatcherInterface $path_matcher, MessengerInterface $messenger, LoggerChannelInterface $logger) {
    $this->flood = $flood;
    $this->configFactory = $config_factory;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->pathMatcher = $path_matcher;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->config = $config_factory->get('protect_form_flood_control.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, string $form_id) {
    $this->displayFormId($form_state, $form_id);
    if (!isset($form['#cache']['tags'])) {
      $form['#cache']['tags'] = [];
    }
    $tags = $form['#cache']['tags'];
    $form['#cache']['tags'] = Cache::mergeTags($tags, $this->config->getCacheTags());
    if ($this->formIsProtected($form_state, $form_id)) {
      if ($this->byPassFloodControl($form)) {
        return;
      }
      // Only static method can be called from validate callback. So we use
      // a custom callback in .module file.
      $form['protect_form_flood_control'] = [
        '#type' => 'hidden',
        '#value' => 'protect_form_flood_control',
        '#element_validate' => ['_protect_form_flood_control_validate_element'],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function protectAllForms() {
    return (bool) $this->config->get('general.protect_all');
  }

  /**
   * {@inheritdoc}
   */
  public function shouldLogBlockedSubmissions() {
    return (bool) $this->config->get('general.log');
  }

  /**
   * {@inheritdoc}
   */
  public function logBlockedSubmission(string $form_id, int $window, int $threshold) {
    $this->logger->info('The submission of the form @form_id has been blocked for client IP @client_ip. Settings for the form ID are window: @window seconds and threshold: @threshold times.', [
      '@form_id' => $form_id,
      '@client_ip' => $this->requestStack->getCurrentRequest()->getClientIp(),
      '@window' => $window,
      '@threshold' => $threshold,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function formIsSystemForm(string $form_id) {
    // Check if the form is a system form. We don't want to protect them.
    // Theses forms may be programmatically submitted by drush
    // and other modules.
    // Thanks to Honeypot module.
    if (preg_match('/[^a-zA-Z]system_/', $form_id) === 1 || preg_match('/[^a-zA-Z]search_/', $form_id) === 1 || preg_match('/[^a-zA-Z]views_exposed_form_/', $form_id) === 1) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getWindow() {
    return $this->config->get('general.window');
  }

  /**
   * {@inheritdoc}
   */
  public function getThreshold() {
    return $this->config->get('general.threshold');
  }

  /**
   * {@inheritdoc}
   */
  public function showIds() {
    return (bool) $this->config->get('show_ids');
  }

  /**
   * {@inheritdoc}
   */
  public function getProtectedFormIds() {
    return $this->config->get('general.protected_ids') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUnprotectedFormIds() {
    return $this->config->get('general.unprotected_ids') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowlist() {
    return $this->config->get('general.allowlist') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getProtectedFormIdsPatterns() {
    $ids = $this->getProtectedFormIds();
    return implode("\r\n", $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnprotectedFormIdsPatterns() {
    $ids = $this->getUnprotectedFormIds();
    return implode("\r\n", $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowlistPatterns() {
    $ip_addresses = $this->getAllowlist();
    return implode("\r\n", $ip_addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function shouldDisplayFormId() {
    if (!$this->showIds()) {
      return FALSE;
    }
    return $this->currentUser->hasPermission('administer protect form flood control') || $this->currentUser->hasPermission('view protect form flood control form ids');
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId(FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    return !empty($build_info['base_form_id']) ? $build_info['base_form_id'] : '';
  }

  /**
   * {@inheritdoc}
   */
  public function displayFormId(FormStateInterface $form_state, string $form_id) {
    if (!$this->shouldDisplayFormId()) {
      return;
    }
    $message = $this->t('Form ID: @form_id', ['@form_id' => $form_id]);
    $base_form_id = $this->getBaseFormId($form_state);
    if (!empty($base_form_id)) {
      $message = $this->t(
        'Form ID: @form_id - Base form ID: @base_form_id',
        [
          '@form_id' => $form_id,
          '@base_form_id' => $base_form_id,
        ]
      );
    }
    $this->messenger->addStatus($message);
  }

  /**
   * {@inheritdoc}
   */
  public function byPassFloodControl(array &$form) {
    $form['#cache']['contexts'][] = 'user.permissions';
    if ($this->currentUser->hasPermission('bypass protect form flood control')) {
      return TRUE;
    }
    if ($this->clientIpIsInAllowlist($form)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function clientIpIsInAllowlist(array &$form) {
    $allowlist_patterns = $this->getAllowlistPatterns();
    if (empty($allowlist_patterns)) {
      return FALSE;
    }
    $form['#cache']['contexts'][] = 'protect_form_flood_control_allowlist';
    $client_ip = $this->requestStack->getCurrentRequest()->getClientIp();
    return $this->pathMatcher->matchPath($client_ip, $allowlist_patterns);
  }

  /**
   * {@inheritdoc}
   */
  public function formIsProtected(FormStateInterface $form_state, string $form_id) {
    // Never protect form configuration.
    if ($form_id === 'protect_form_flood_control_settings') {
      return FALSE;
    }
    if ($this->formIsSystemForm($form_id)) {
      return FALSE;
    }
    if ($this->protectAllForms()) {
      $unprotected_ids_patterns = $this->getUnprotectedFormIdsPatterns();
      if ($this->formMatchPatterns($form_state, $form_id, $unprotected_ids_patterns)) {
        return FALSE;
      }
      return TRUE;
    }
    $protected_ids_patterns = $this->getProtectedFormIdsPatterns();
    if ($this->formMatchPatterns($form_state, $form_id, $protected_ids_patterns)) {
      return TRUE;
    }
    // Check at last each individual form configuration.
    $individual_form_configurations = $this->getIndividualFormConfigurations();
    foreach ($individual_form_configurations as $configuration) {
      $ids = $configuration['ids'];
      $ids_patterns = $this->massageIdsAsPattern($ids);
      if ($this->formMatchPatterns($form_state, $form_id, $ids_patterns)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function formMatchPatterns(FormStateInterface $form_state, string $form_id, string $patterns) {
    if (empty($patterns)) {
      return FALSE;
    }
    if ($this->pathMatcher->matchPath($form_id, $patterns)) {
      return TRUE;
    }
    // Check the base form ID.
    $base_form_id = $this->getBaseFormId($form_state);
    if (!empty($base_form_id) && $this->pathMatcher->matchPath($base_form_id, $patterns)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndividualFormConfigurations() {
    return $this->config->get('forms') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function massageIdsAsPattern(array $ids) {
    return implode("\r\n", $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormConfiguration(FormStateInterface $form_state, string $form_id) {
    $window_default = $this->getWindow();
    $threshold_default = $this->getThreshold();
    $form_configuration = [
      'window' => $window_default,
      'threshold' => $threshold_default,
    ];
    $individual_form_configurations = $this->getIndividualFormConfigurations();
    foreach ($individual_form_configurations as $configuration) {
      $ids = $configuration['ids'];
      $window = $configuration['window'];
      $threshold = $configuration['threshold'];
      $ids_patterns = $this->massageIdsAsPattern($ids);
      if ($this->formMatchPatterns($form_state, $form_id, $ids_patterns)) {
        $form_configuration = [
          'window' => !empty($window) ? $window : $window_default,
          'threshold' => !empty($threshold) ? $threshold : $threshold_default,
        ];
        break;
      }
    }
    return $form_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlood() {
    return $this->flood;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateFormatter() {
    return $this->dateFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public function truncateFormId(string $form_id) {
    if (strlen($form_id) >= self::MAX_LENGTH) {
      $md5 = md5($form_id);
      $truncated = substr($form_id, 0, 30);
      return $truncated . '_' . $md5;
    }
    return $form_id;
  }

}
