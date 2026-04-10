<?php

namespace Drupal\quiz\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizFeedbackType;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function quiz_get_feedback_options;

/**
 * Quiz global settings form.
 */
class QuizAdminForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The Module handler service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected ModuleHandlerInterface $moduleHandler,
    TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['quiz.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'quiz_admin_settings';
  }

  /**
   * This builds the main settings form for the quiz module.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('quiz.settings');

    $form['quiz_global_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => $this->t("Control aspects of the Quiz module's display"),
    ];

    $form['quiz_global_settings']['revisioning'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Revisioning'),
      '#default_value' => $config->get('revisioning'),
      '#description' => $this->t('<strong>Warning: this will impact reporting.</strong><br/>Allow Quiz editors to create new revisions of Quizzes and Questions that have attempts.<br/>Leave this unchecked to prevent edits to Quizzes or Questions that have attempts (recommended).<br/>To make changes to a quiz in progress without revisioning, the user must have the "override quiz revisioning" permission.'),
    ];

    $form['quiz_global_settings']['durod'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete results when a user is deleted'),
      '#default_value' => $config->get('durod'),
      '#description' => $this->t('When a user is deleted delete any and all results for that user.'),
    ];

    $form['quiz_global_settings']['default_close'] = [
      '#type' => 'number',
      '#title' => $this->t('Default number of days before a @quiz is closed', ['@quiz' => QuizUtil::getQuizName()]),
      '#default_value' => $config->get('default_close'),
      '#size' => 4,
      '#min' => 0,
      '#maxlength' => 4,
      '#description' => $this->t('Supply a number of days to calculate the default close date for new quizzes.'),
    ];

    $form['quiz_global_settings']['use_passfail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow quiz creators to set a pass/fail option when creating a @quiz.', ['@quiz' => strtolower(QuizUtil::getQuizName())]),
      '#default_value' => $config->get('use_passfail'),
      '#description' => $this->t('Check this to display the pass/fail options in the @quiz form. If you want to prohibit other quiz creators from changing the default pass/fail percentage, uncheck this option.', ['@quiz' => QuizUtil::getQuizName()]),
    ];

    $form['quiz_global_settings']['remove_partial_quiz_record'] = [
      '#title' => $this->t('Remove incomplete quiz records (older than)'),
      '#description' => $this->t('Number of days to keep incomplete quiz attempts.'),
      '#default_value' => $config->get('remove_partial_quiz_record'),
      '#type' => 'textfield',
      '#units' => [
        '86400' => ['max' => 30, 'step size' => 1],
        '3600' => ['max' => 24, 'step size' => 1],
        '60' => ['max' => 60, 'step size' => 1],
      ],
    ];

    $form['quiz_global_settings']['remove_invalid_quiz_record'] = [
      '#title' => $this->t('Remove invalid quiz records (older than)'),
      '#description' => $this->t('Number of days to keep invalid quiz attempts.'),
      '#default_value' => $config->get('remove_invalid_quiz_record'),
      '#type' => 'textfield',
      '#units' => [
        '86400' => ['max' => 30, 'step size' => 1],
        '3600' => ['max' => 24, 'step size' => 1],
        '60' => ['max' => 60, 'step size' => 1],
      ],
    ];

    $form['quiz_global_settings']['autotitle_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Length of automatically set question titles'),
      '#size' => 3,
      '#maxlength' => 3,
      '#description' => $this->t("Integer between 0 and 128. If the question creator doesn't set a question title the system will make a title automatically. Here you can decide how long the autotitle can be."),
      '#default_value' => $config->get('autotitle_length'),
      '#min' => 0,
      '#max' => 128,
    ];

    $form['quiz_global_settings']['pager_start'] = [
      '#type' => 'number',
      '#title' => $this->t('Pager start'),
      '#size' => 3,
      '#maxlength' => 3,
      '#description' => $this->t('If a quiz has this many questions, a pager will be displayed instead of a select box.'),
      '#default_value' => $config->get('pager_start'),
    ];

    $form['quiz_global_settings']['pager_siblings'] = [
      '#type' => 'number',
      '#title' => $this->t('Pager siblings'),
      '#size' => 3,
      '#maxlength' => 3,
      '#description' => $this->t('Number of siblings to show.'),
      '#default_value' => $config->get('pager_siblings'),
    ];

    $form['quiz_global_settings']['time_limit_buffer'] = [
      '#type' => 'number',
      '#title' => $this->t('Time limit buffer'),
      '#size' => 3,
      '#maxlength' => 3,
      '#description' => $this->t('How many seconds after the time limit runs out to allow answers.'),
      '#default_value' => $config->get('time_limit_buffer'),
    ];

    // Review options.
    $review_options = quiz_get_feedback_options();

    $form['quiz_global_settings']['admin_review_options']['override_admin_feedback'] = [
      '#title' => $this->t('Override administrator review options'),
      '#description' => $this->t('When administrators take or grade a quiz, these options will be used instead.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('override_admin_feedback'),
    ];

    $form['quiz_global_settings']['admin_review_options']['#title'] = $this->t('Administrator review options');
    $form['quiz_global_settings']['admin_review_options']['#type'] = 'fieldset';
    $form['quiz_global_settings']['admin_review_options']['#description'] = $this->t('Control what feedback types quiz administrators will see when viewing results for other users.');
    foreach (QuizFeedbackType::loadMultiple() as $key => $when) {

      $items = (array) $config->get("admin_review_options_$key");
      $selected = [];
      foreach ($items as $item_name => $item) {
        if ($item === TRUE) {
          $selected[$item_name] = $item_name;
        }
      }

      $form['quiz_global_settings']['admin_review_options']["admin_review_options_$key"] = [
        '#title' => $when->label(),
        '#description' => $when->get('description'),
        '#type' => 'checkboxes',
        '#options' => $review_options,
        '#default_value' => $selected,
      ];
    }

    $form['quiz_addons'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Addons configuration'),
      '#description' => $this->t('Quiz has built in integration with some other modules. Disabled checkboxes indicates that modules are not enabled.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $jquery_countdown_installed = file_exists(DRUPAL_ROOT . '/libraries/jquery-countdown/dist/jquery.countdown.min.js');
    $jquery_countdown_requirements = '';

    $form['quiz_addons']['has_timer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display timer'),
      '#default_value' => !$jquery_countdown_installed ? FALSE : $config->get('has_timer'),
      '#description' => $this->t("jQuery countdown is <strong>optional</strong> for Quiz. It is used to display a timer when taking a quiz. Without this timer, the user will not know how much time they have left to complete the Quiz.") . ' ' . $jquery_countdown_requirements,
      '#disabled' => !$jquery_countdown_installed,
    ];

    $form['quiz_addons']['timer_format'] = [
      '#type' => 'textfield',
      '#title' => t('Timer format'),
      '#default_value' => $config->get('timer_format'),
      '#description' => t('Timer format'),
      '#disabled' => !$jquery_countdown_installed,
      '#states' => [
        'visible' => [
          ':input[name="has_timer"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['quiz_look_feel'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Look and feel'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => $this->t("Control aspects of the Quiz module's display"),
    ];

    $form['quiz_look_feel']['\Drupal\quiz\Util\QuizUtil::getQuizName()'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display name'),
      '#default_value' => QuizUtil::getQuizName(),
      '#description' => $this->t('Change the name of the quiz type. Do you call it <em>test</em> or <em>assessment</em> instead? Change the display name of the module to something else. By default, it is called <em>Quiz</em>.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->cleanValues();
    $this->config('quiz.settings')
      ->setData($form_state->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

}
