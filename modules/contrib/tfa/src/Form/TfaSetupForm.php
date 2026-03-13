<?php

namespace Drupal\tfa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tfa\TfaPluginManager;
use Drupal\tfa\TfaSetup;
use Drupal\tfa\TfaUserDataTrait;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * TFA setup form router.
 */
final class TfaSetupForm extends FormBase {
  use TfaUserDataTrait;
  use StringTranslationTrait;

  /**
   * The TFA plugin manager.
   *
   * @var \Drupal\tfa\TfaPluginManager
   */
  protected TfaPluginManager $tfaPluginManager;

  /**
   * The password hashing service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected PasswordInterface $passwordChecker;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * The TFA Logger Channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $tfaLogger;

  /**
   * TFA Setup form constructor.
   *
   * @param \Drupal\tfa\TfaPluginManager $tfa_plugin_manager
   *   The TFA plugin manager.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data object to store user information.
   * @param \Drupal\Core\Password\PasswordInterface $password_checker
   *   The password service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Psr\Log\LoggerInterface $tfa_logger
   *   The TFA logger channel.
   */
  public function __construct(TfaPluginManager $tfa_plugin_manager, UserDataInterface $user_data, PasswordInterface $password_checker, MailManagerInterface $mail_manager, UserStorageInterface $user_storage, LoggerInterface $tfa_logger) {
    $this->tfaPluginManager = $tfa_plugin_manager;
    $this->userData = $user_data;
    $this->passwordChecker = $password_checker;
    $this->mailManager = $mail_manager;
    $this->userStorage = $user_storage;
    $this->tfaLogger = $tfa_logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.tfa'),
      $container->get('user.data'),
      $container->get('password'),
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('logger.channel.tfa'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tfa_setup';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL, string $method = 'tfa_totp', int $reset = 0): array {
    /** @var \Drupal\user\Entity\User $account */
    $account = $this->userStorage->load($this->currentUser()->id());

    $form['account'] = [
      '#type' => 'value',
      '#value' => $user,
    ];
    $tfa_data = $this->tfaGetTfaData($user->id());
    $enabled = isset($tfa_data['status'], $tfa_data['data']) && !empty($tfa_data['data']['plugins']) && $tfa_data['status'];

    // Always require a password on the first time through.
    if (empty($form_state->getStorage())) {
      // Allow administrators to change TFA settings for another account.
      if ($account->id() != $user->id() && $account->hasPermission('administer tfa for other users')) {
        $current_pass_description = $this->t('Enter your current password to
        alter TFA settings for account %name.', ['%name' => $user->getAccountName()]);
      }
      else {
        $current_pass_description = $this->t('Enter your current password to continue.');
      }

      $form['current_pass'] = [
        '#type' => 'password',
        '#title' => $this->t('Current password'),
        '#size' => 25,
        '#required' => TRUE,
        '#description' => $current_pass_description,
        '#attributes' => ['autocomplete' => 'off'],
      ];

      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Confirm'),
      ];

      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#limit_validation_errors' => [],
        '#submit' => ['::cancelForm'],
      ];
    }
    else {
      if (!$enabled && empty($form_state->get('steps'))) {
        $form_state->set('full_setup', TRUE);
        $steps = $this->tfaFullSetupSteps();
        $form_state->set('steps_left', $steps);
        $form_state->set('steps_skipped', []);
      }

      if (!empty($form_state->get('step_method'))) {
        $method = $form_state->get('step_method');
        if (!is_string($method)) {
          $form_state->setErrorByName('', $this->t('An unexpected error occurred.'));
          $this->tfaLogger->debug(
            'step_method not string in @class_method.',
            [
              '@class_method' => __METHOD__,
            ]
          );
          return [];
        }
      }

      // Record methods progressed.
      $steps = $form_state->get('steps');
      $steps = is_array($steps) ? $steps : [];
      $steps[] = $method;
      $form_state->set('steps', $steps);
      $plugin = $this->tfaPluginManager->getDefinition($method, FALSE);
      $setup_plugin = $this->tfaPluginManager->createInstance($plugin['id'], ['uid' => $user->id()]);
      $tfa_setup = new TfaSetup($setup_plugin);
      $form = $tfa_setup->getForm($form, $form_state, $reset);
      $form_state->set($method, $tfa_setup);

      $form['actions']['#type'] = 'actions';
      if (!empty($form_state->get('full_setup')) && count($form_state->get('steps')) > 1) {
        $count = count($form_state->get('steps_left'));
        $form['actions']['skip'] = [
          '#type' => 'submit',
          '#value' => $count > 0 ? $this->t('Skip') : $this->t('Skip and finish'),
          '#limit_validation_errors' => [],
          '#submit' => ['::cancelForm'],
        ];
      }
      // Provide cancel button on first step or single steps.
      else {
        $form['actions']['cancel'] = [
          '#type' => 'submit',
          '#value' => $this->t('Cancel'),
          '#limit_validation_errors' => [],
          '#submit' => ['::cancelForm'],
        ];
      }
      // Record the method in progress regardless of whether in full setup.
      $form_state->set('step_method', $method);
      // Record the plugin label for use in errors.
      $form_state->set('plugin_label', $plugin['label']);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->userStorage->load($this->currentUser()->id());
    $values = $form_state->getValues();
    $account = $form['account']['#value'];
    if (isset($values['current_pass'])) {
      // Allow administrators to change TFA settings for another account using
      // their own password.
      if ($account->id() != $user->id()) {
        if ($user->hasPermission('administer tfa for other users')) {
          $account = $user;
        }
        // If current user lacks admin permissions, kick them out.
        else {
          throw new NotFoundHttpException();
        }
      }
      $current_pass = $this->passwordChecker->check(trim($form_state->getValue('current_pass')), $account->getPassword());
      if (!$current_pass) {
        $form_state->setErrorByName('current_pass', $this->t("Incorrect password."));
      }
      return;
    }
    elseif (!empty($form_state->get('step_method'))) {
      $method = $form_state->get('step_method');
      if (!is_string($method)) {
        $form_state->setErrorByName('', $this->t('An unexpected error occurred.'));
        $this->tfaLogger->debug(
          'step_method not string in @class_method.',
          [
            '@class_method' => __METHOD__,
          ]
        );
        return;
      }
      $tfa_setup = $form_state->get($method);
      if (!is_object($tfa_setup) || !is_a($tfa_setup, TfaSetup::class)) {
        $form_state->setErrorByName('', $this->t('An unexpected error occurred.'));
        $this->messenger()->addError($this->t('There was an error during TFA setup. Your settings have not been saved.'));
        $this->tfaLogger->debug(
          '@method is not a TfaSetup object in @class_method.',
          [
            '@method' => $method,
            '@class_method' => __METHOD__,
          ]
        );
        return;
      }
      // Validate plugin form.
      if (!$tfa_setup->validateForm($form, $form_state)) {
        $messages = $tfa_setup->getErrorMessages();
        if (!empty($messages)) {
          foreach ($messages as $element => $message) {
            $form_state->setErrorByName($element, $message);
          }
        }
      }
    }
  }

  /**
   * Form cancel handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state): void {
    $account = $form['account']['#value'];
    $label = $form_state->get('plugin_label') ?? '';
    $this->messenger()->addWarning($this->t('Setup of @plugin_label canceled.', ['@plugin_label' => $label]));
    $form_state->setRedirect('tfa.overview', ['user' => $account->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = $form['account']['#value'];
    $values = $form_state->getValues();

    // Password validation.
    if (isset($values['current_pass'])) {
      $form_state->set('pass_confirmed', TRUE);
      $form_state->setRebuild();
      return;
    }
    elseif (!empty($form_state->get('step_method'))) {
      $method = $form_state->get('step_method');
      if (!is_string($method)) {
        $this->messenger()->addError($this->t('There was an error during TFA setup. Your settings have not been saved.'));
        $this->tfaLogger->debug(
          'step_method not string in @class_method.',
          [
            '@class_method' => __METHOD__,
          ]
        );
        return;
      }
      $skipped_method = FALSE;

      // Support skipping optional steps when in full setup.
      if (isset($values['skip']) && $values['op'] === $values['skip']) {
        $skipped_method = $method;
        $skipped_steps = $form_state->get('steps_skipped');
        $skipped_steps = is_array($skipped_steps) ? $skipped_steps : [];
        $skipped_steps[] = $method;
        $form_state->set('steps_skipped', $skipped_steps);
        $form_state->set($method, NULL);
      }

      if (!empty($form_state->get($method))) {
        if (!is_object($form_state->get($method)) || !is_a($form_state->get($method), TfaSetup::class)) {
          $this->messenger()->addError($this->t('There was an error during TFA setup. Your settings have not been saved.'));
          $this->tfaLogger->debug(
            '@method is not a TfaSetup object in @class_method.',
            [
              '@method' => $method,
              '@class_method' => __METHOD__,
            ]
          );
          return;
        }
        // Trigger multi-step if in full setup.
        if (!empty($form_state->get('full_setup'))) {
          $setup_class = $form_state->get($method);
          $this->tfaNextSetupStep($form_state, $method, $setup_class, $skipped_method !== FALSE);
        }

        // Plugin form submit.
        $setup_class = $form_state->get($method);
        if (!$setup_class->submitForm($form, $form_state)) {
          $this->messenger()->addError($this->t('There was an error during TFA setup. Your settings have not been saved.'));
          $form_state->setRedirect('tfa.overview', ['user' => $account->id()]);
          return;
        }
      }

      // Return if multi-step.
      if ($form_state->getRebuildInfo()) {
        return;
      }
      // Else, setup complete and return to overview page.
      $this->messenger()->addStatus($this->t('TFA setup complete.'));
      $form_state->setRedirect('tfa.overview', ['user' => $account->id()]);

      // Log and notify if this was full setup.
      if (!empty($form_state->get('step_method'))) {
        $data = ['plugins' => $form_state->get('step_method')];
        $this->tfaSaveTfaData($account->id(), $data);
        $this->logger('tfa')->info('TFA enabled for user @name UID @uid', [
          '@name' => $account->getAccountName(),
          '@uid' => $account->id(),
        ]);

        if ($account->getEmail()) {
          $params = ['account' => $account];
          $this->mailManager->mail('tfa', 'tfa_enabled_configuration', $account->getEmail(), $account->getPreferredLangcode(), $params);
        }
      }
    }
  }

  /**
   * Steps eligible for TFA setup.
   */
  protected function tfaFullSetupSteps(): array {
    $config = $this->config('tfa.settings');
    $steps = [
      $config->get('default_validation_plugin'),
    ];

    $login_plugins = $config->get('login_plugins');

    foreach ($login_plugins as $login_plugin) {
      $steps[] = $login_plugin;
    }

    // @todo Add send plugins.
    return $steps;
  }

  /**
   * Set form rebuild, next step, and message if any plugin steps left.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $this_step
   *   The current setup step.
   * @param \Drupal\tfa\TfaSetup $step_class
   *   The setup instance of the current step.
   * @param bool $skipped_step
   *   Whether the step was skipped.
   */
  protected function tfaNextSetupStep(FormStateInterface &$form_state, string $this_step, TfaSetup $step_class, bool $skipped_step = FALSE): void {
    // Remove this step from steps left.
    $steps_left = $form_state->get('steps_left');
    $steps_left = is_array($steps_left) ? $steps_left : [];
    $steps_left = array_diff($steps_left, [$this_step]);
    $form_state->set('steps_left', $steps_left);
    if (!empty($steps_left)) {
      // Contextual reporting.
      if ($output = $step_class->getSetupMessages()) {
        $output = $skipped_step ? $output['skipped'] : $output['saved'];
      }
      $count = count($steps_left);
      $output .= ' ' . $this->formatPlural($count, 'One setup step remaining.', '@count TFA setup steps remain.', ['@count' => $count]);
      if ($output) {
        $this->messenger()->addStatus($output);
      }

      // Set next step and mark form for rebuild.
      $next_step = array_shift($steps_left);
      $form_state->set('step_method', $next_step);
      $form_state->setRebuild();
    }
  }

}
