<?php

declare(strict_types=1);

namespace Drupal\quiz\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Entity\QuizResultAnswerType;
use Drupal\quiz\Entity\QuizResultType;
use Drupal\quiz\Entity\QuizType;
use Drupal\quiz\Plugin\QuizQuestionPluginManager;
use Drupal\quiz\Services\QuizHelper;
use Drupal\quiz\Services\QuizSessionInterface;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * General hook implementations for quiz.
 */
class QuizHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected AccountProxyInterface $currentUser,
    protected MessengerInterface $messenger,
    protected RouteMatchInterface $routeMatch,
    protected QuizSessionInterface $quizSession,
    protected QuizQuestionPluginManager $pluginManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected QuizHelper $quizHelper,
    #[Autowire(service: 'config.factory')]
    protected $configFactory,
    #[Autowire(service: 'datetime.time')]
    protected $time,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): string {
    if ($route_name == 'help.page.quiz') {
      return (string) $this->t('<p>The quiz module allows users to administer a quiz, as a sequence of questions, and track the answers given. It allows for the creation of questions (and their answers), and organizes these questions into a quiz. Its target audience includes educational institutions, online training programs, employers, and people who just want to add a fun activity for their visitors to their Drupal site.</p><p>For more information about Quiz, and resources on how to use Quiz, see the <a href="https://drupal.org/project/quiz">Quiz project website</a></p>');
    }
    return '';
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $result_ids = [];

    $old_rm_time = $this->configFactory->get('quiz.settings')
      ->get('remove_partial_quiz_record');
    if ($old_rm_time) {
      $res = $this->database->select('quiz_result', 'qnr')
        ->fields('qnr', ['result_id'])
        ->condition('time_end', 0)
        ->where('(:request_time - time_start) > :remove_time', [
          ':request_time' => $this->time->getRequestTime(),
          ':remove_time' => $old_rm_time,
        ])
        ->execute();
      while ($result_id = $res->fetchField()) {
        $result_ids[$result_id] = $result_id;
      }
    }

    $inv_rm_time = $this->configFactory->get('quiz.settings')->get('remove_invalid_quiz_record');
    if ($inv_rm_time) {
      $query = $this->database->select('quiz_result', 'qnr');
      $query->fields('qnr', ['result_id']);
      $query->join('quiz', 'qnp', 'qnr.vid = qnp.vid');
      $db_or = $query->orConditionGroup();
      $db_or->isNull('qnp.takes');
      $db_or->condition('qnp.takes', 0);
      $query->condition($db_or);
      $query->condition('qnr.is_invalid', 1);
      $query->condition('qnr.time_end', $this->time->getRequestTime() - $inv_rm_time, '<=');
      $res = $query->execute();
      while ($result_id = $res->fetchField()) {
        $result_ids[$result_id] = $result_id;
      }
    }

    $quiz_results = QuizResult::loadMultiple($result_ids);
    $this->entityTypeManager->getStorage('quiz_result')->delete($quiz_results);
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra = [];

    foreach (QuizType::loadMultiple() as $bundle) {
      $extra['quiz'][$bundle->id()] = [
        'display' => [
          'take' => [
            'label' => $this->t('Take @quiz button', ['@quiz' => QuizUtil::getQuizName()]),
            'description' => $this->t('The take button.'),
            'weight' => 10,
          ],
          'stats' => [
            'label' => $this->t('@quiz summary', ['@quiz' => QuizUtil::getQuizName()]),
            'description' => $this->t('@quiz summary', ['@quiz' => QuizUtil::getQuizName()]),
            'weight' => 9,
          ],
        ],
      ];
    }

    $options = $this->quizHelper->getFeedbackOptions();
    foreach (QuizResultAnswerType::loadMultiple() as $bundle) {
      $extra['quiz_result_answer'][$bundle->id()]['display']['table'] = [
        'label' => $this->t('Feedback table'),
        'description' => $this->t('A table of feedback.'),
        'weight' => 0,
        'visible' => TRUE,
      ];
      foreach ($options as $option => $label) {
        $extra['quiz_result_answer'][$bundle->id()]['display'][$option] = [
          'label' => $label,
          'description' => $this->t('Feedback for @label.', ['@label' => $label]),
          'weight' => 0,
          'visible' => FALSE,
        ];
      }
    }

    foreach (QuizResultType::loadMultiple() as $bundle) {
      $extra['quiz_result'][$bundle->id()]['display'] = [
        'score' => [
          'label' => $this->t('Score'),
          'description' => $this->t('The score of the result.'),
          'weight' => 1,
        ],
        'questions' => [
          'label' => $this->t('Questions'),
          'description' => $this->t('The questions in this result.'),
          'weight' => 2,
        ],
        'summary' => [
          'label' => $this->t('Summary'),
          'description' => $this->t('The summary and pass/fail text.'),
          'weight' => 3,
        ],
      ];
    }

    return $extra;
  }

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel($edit, $account, $method): void {
    if ($method == 'user_cancel_reassign') {
      $this->database
        ->query("UPDATE {quiz_result} SET uid = 0 WHERE uid = :uid", [':uid' => $account->id()]);
    }
  }

  /**
   * Implements hook_user_delete().
   */
  #[Hook('user_delete')]
  public function userDelete($account): void {
    if ($this->configFactory->get('quiz.settings')->get('durod', 0)) {
      $this->deleteUsersResults((int) $account->id());
    }
  }

  /**
   * Implements hook_quiz_access().
   */
  #[Hook('quiz_access')]
  public function quizAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'take') {
      $user_is_admin = $entity->access('update');
      $hooks = [];

      if (!$entity->get('quiz_date')->isEmpty()) {
        $request_time = $this->time->getRequestTime();
        $quiz_date = $entity->get('quiz_date')->get(0)->getValue();
        $quiz_open = $request_time >= strtotime($quiz_date['value']);
        $quiz_closed = $request_time >= strtotime($quiz_date['end_value']);
        if (!$quiz_open || $quiz_closed) {
          if ($user_is_admin) {
            $hooks['admin_ignore_date'] = [
              'success' => TRUE,
              'message' => (string) $this->t('You are marked as an administrator or owner for this @quiz. While you can take this @quiz, the open/close times prohibit other users from taking this @quiz.', ['@quiz' => QuizUtil::getQuizName()]),
            ];
          }
          else {
            if ($quiz_closed) {
              return AccessResultForbidden::forbidden((string) $this->t('This @quiz is closed.', ['@quiz' => QuizUtil::getQuizName()]));
            }
            if (!$quiz_open) {
              return AccessResultForbidden::forbidden((string) $this->t('This @quiz is not yet open.', ['@quiz' => QuizUtil::getQuizName()]));
            }
          }
        }
      }

      if ($entity->get('takes')->getString() > 0) {
        $taken = $this->database
          ->query('SELECT COUNT(*) AS takes FROM {quiz_result} WHERE uid = :uid AND qid = :qid', [
            ':uid' => $account->id(),
            ':qid' => $entity->id(),
          ])
          ->fetchField();
        $allowed_times = $this->formatPlural($entity->get('takes')->getString(), '1 time', '@count times');
        $taken_times = $this->formatPlural($taken, '1 time', '@count times');

        if ($taken) {
          if (FALSE && $user_is_admin) {
            $hooks['owner_limit'] = [
              'success' => TRUE,
              'message' => (string) $this->t('You have taken this @quiz already. You are marked as an owner or administrator for this quiz, so you can take this quiz as many times as you would like.', ['@quiz' => QuizUtil::getQuizName()]),
            ];
          }
          elseif ($taken >= $entity->get('takes')->getString()) {
            if ($entity->allow_resume->value && $entity->getResumeableResult($account)) {
              // Quiz is resumable and there is an active attempt.
            }
            elseif (!$this->quizSession->isTakingQuiz($entity)) {
              $hooks['attempt_limit'] = [
                'success' => FALSE,
                'message' => (string) $this->t('You have already taken this @quiz @really. You may not take it again.', [
                  '@quiz' => QuizUtil::getQuizName(),
                  '@really' => $taken_times,
                ]),
              ];
            }
          }
          elseif ($entity->show_attempt_stats->value) {
            $hooks['attempt_limit'] = [
              'success' => TRUE,
              'message' => (string) $this->t("You can only take this @quiz @allowed. You have taken it @really.", [
                '@quiz' => QuizUtil::getQuizName(),
                '@allowed' => $allowed_times,
                '@really' => $taken_times,
              ]),
              'weight' => -10,
            ];
          }
        }
      }

      if ($entity->show_passed->value && $account->id() && $entity->isPassed($account)) {
        $hooks['already_passed'] = [
          'success' => TRUE,
          'message' => (string) $this->t('You have already passed this @quiz.', ['@quiz' => QuizUtil::getQuizName()]),
          'weight' => 10,
        ];
      }

      if (!empty($hooks)) {
        foreach ($hooks as $hook) {
          if (!$hook['success']) {
            return AccessResultForbidden::forbidden($hook['message']);
          }
        }
      }

      if (!empty($hooks)) {
        foreach ($hooks as $hook) {
          if ($hook['success']) {
            if ($this->routeMatch->getRouteName() == 'entity.quiz.canonical') {
              $this->messenger->addWarning($hook['message']);
            }
            return [AccessResultAllowed::allowed()];
          }
        }
      }

      if (!$this->currentUser->hasPermission('access quiz') || !$entity->access('view')) {
        return [AccessResultForbidden::forbidden((string) $this->t('You are not allowed to take this @quiz.', ['@quiz' => QuizUtil::getQuizName()]))];
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(&$form, FormStateInterface $form_state): void {
    $field = $form_state->getFormObject()->getEntity();
    if ($field->getTargetEntityTypeId() != 'quiz_result') {
      return;
    }

    $form['third_party_settings']['quiz']['show_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show this field on @quiz start.', ['@quiz' => QuizUtil::getQuizName()]),
      '#default_value' => $field->getThirdPartySetting('quiz', 'show_field', TRUE),
      '#description' => $this->t('If checked, this field will be presented when starting a quiz.'),
    ];
  }

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getTargetEntityTypeId() == 'quiz_result') {
      if (is_a($field_definition, FieldConfig::class)) {
        if (!$field_definition->getThirdPartySetting('quiz', 'show_field')) {
          return AccessResult::forbidden('quiz_show_field');
        }
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(&$page): void {
    $page['#attached']['library'][] = 'quiz/styles';
  }

  /**
   * Implements hook_query_quiz_random_alter().
   */
  #[Hook('query_quiz_random_alter')]
  public function queryQuizRandomAlter(AlterableInterface $query): void {
    $query->orderRandom();
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    $plugins = $this->pluginManager->getDefinitions();
    foreach ($plugins as $key => $plugin) {
      if (isset($bundles['quiz_question'][$key])) {
        $bundles['quiz_question'][$key]['class'] = $plugin['class'];
        $bundles['quiz_result_answer'][$key]['class'] = $plugin['handlers']['response'];
      }
    }
  }

  /**
   * Deletes all results associated with a given user.
   */
  protected function deleteUsersResults(int $uid): void {
    $res = $this->database->query("SELECT result_id FROM {quiz_result} WHERE uid = :uid", [':uid' => $uid]);
    $result_ids = [];
    while ($result_id = $res->fetchField()) {
      $result_ids[] = $result_id;
    }
    $controller = $this->entityTypeManager->getStorage('quiz_result');
    $entities = $controller->loadMultiple($result_ids);
    $controller->delete($entities);
  }

}
