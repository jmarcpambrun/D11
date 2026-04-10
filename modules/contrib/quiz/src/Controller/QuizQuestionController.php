<?php

namespace Drupal\quiz\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Form\QuizQuestionAnsweringForm;
use Drupal\quiz\Form\QuizQuestionFeedbackForm;
use Drupal\quiz\Services\QuizSessionInterface;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * QuizQuestion controller class.
 */
class QuizQuestionController extends EntityController {

  /**
   * Constructs a new EntityController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\quiz\Services\QuizSessionInterface $quizSession
   *   The quiz session service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   An immutable configuration object.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityRepositoryInterface $entity_repository,
    RendererInterface $renderer,
    TranslationInterface $string_translation,
    UrlGeneratorInterface $url_generator,
    RouteMatchInterface $route_match,
    RequestStack $request_stack,
    protected QuizSessionInterface $quizSession,
    protected FormBuilderInterface $formBuilder,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
  ) {
    parent::__construct(
      $entity_type_manager,
      $entity_type_bundle_info,
      $entity_repository,
      $renderer,
      $string_translation,
      $url_generator,
      $route_match,
      $request_stack,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('renderer'),
      $container->get('string_translation'),
      $container->get('url_generator'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
      $container->get('quiz.session'),
      $container->get('form_builder'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Show feedback for a question or page.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The current quiz.
   * @param mixed $question_number
   *   The current question number.
   *
   * @return array
   *   The render array for that question.
   */
  public function feedback(Quiz $quiz, mixed $question_number): array {
    $form = $this->formBuilder->getForm(QuizQuestionFeedbackForm::class, $quiz, $question_number);
    $page['body']['question'] = $form;
    return $page;
  }

  /**
   * Take a quiz questions.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   A quiz.
   * @param int $question_number
   *   A question number, starting at 1. Pages do not have question numbers.
   *   Quiz directions are considered part of the numbering.
   */
  public function take(Quiz $quiz, int $question_number) {
    if ($this->quizSession->isTakingQuiz($quiz)) {
      // Attempt to resume a quiz in progress.
      $quiz_result = $this->quizSession->getResult($quiz);
      $layout = $quiz_result->getLayout();
      /** @var \Drupal\quiz\Entity\QuizResultAnswer $question */
      $question_relationship = $layout[$question_number];
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('quiz_question');
      if (!empty($question_relationship->qqr_pid)) {
        // Find the parent.
        foreach ($layout as $pquestion) {
          if ($pquestion->qqr_id == $question_relationship->qqr_pid) {
            // Load the page that the requested question belongs to.
            /** @var \Drupal\Quiz\Entity\QuizQuestion $question */
            $question = $storage->loadRevision($pquestion->get('question_vid')->getString());
          }
        }
      }
      else {
        // Load the question.
        /** @var \Drupal\Quiz\Entity\QuizQuestion $question */
        $question = $storage->loadRevision($question_relationship->get('question_vid')->getString());
      }
    }

    if (!isset($question)) {
      // Question disappeared or invalid session. Start over.
      $this->quizSession->removeQuiz($quiz);
      return $this->redirect('entity.quiz.canonical', ['quiz' => $quiz->id()]);
    }

    // Added the progress info to the view.
    $quiz_result = $this->quizSession->getResult($quiz);
    $questions = [];
    $i = 0;
    $found_pages = 0;
    $current_page = 0;
    foreach ($quiz_result->getLayout() as $idx => $question_relationship) {
      if (empty($question_relationship->qqr_pid)) {
        // Question has no parent. Show it in the jumper.
        $questions[$idx] = ++$i;
        $found_pages++;
      }
      if ($question->id() == $question_relationship->get('question_id')->getString()) {
        // Found our question.
        $current_page = $found_pages;
      }
    }

    $content = [];

    $content['progress'] = [
      '#theme' => 'quiz_progress',
      '#total' => count($questions),
      '#current' => $current_page,
      '#weight' => -50,
    ];

    if ($quiz->get('allow_jumping')->value) {
      // Jumping is allowed.
      if (count($questions) < $this->configFactory->get('quiz.settings')->get('pager_start')) {
        // We are still under the threshold, so we can show the select box.
        $selectbox = $this->formBuilder
          ->getForm('\Drupal\quiz\Form\QuizJumperForm', $quiz_result, $current_page, count($questions));
        $content['jumper'] = [
          '#theme' => 'quiz_jumper',
          '#form' => $selectbox,
          '#total' => count($questions),
        ];
      }
      else {
        // Show a pager because a large select box is not fun.
        $siblings = $this->configFactory->get('quiz.settings')->get('pager_siblings');
        $items[] = [
          '#wrapper_attributes' => ['class' => ['pager__item', 'pager-first']],
          'data' => Link::createFromRoute($this->t('first'), 'quiz.question.take', [
            'quiz' => $quiz->id(),
            'question_number' => 1,
          ])->toRenderable(),
        ];
        foreach (_quiz_pagination_helper(count($questions), 1, $current_page, $siblings) as $i) {
          if ($i == $current_page) {
            $items[] = [
              '#wrapper_attributes' => [
                'class' => [
                  'pager__item',
                  'pager-current',
                ],
              ],
              'data' => ['#markup' => $current_page],
            ];
          }
          else {
            $items[] = [
              '#wrapper_attributes' => [
                'class' => [
                  'pager__item',
                  'pager-item',
                ],
              ],
              'data' => Link::createFromRoute($i, 'quiz.question.take', [
                'quiz' => $quiz->id(),
                'question_number' => $i,
              ])->toRenderable(),
            ];
          }
        }
        $items[] = [
          '#wrapper_attributes' => ['class' => ['pager__item', 'pager-last']],
          'data' => Link::createFromRoute($this->t('last'), 'quiz.question.take', [
            'quiz' => $quiz->id(),
            'question_number' => count($questions),
          ])->toRenderable(),
        ];
        $content['pager'] = [
          '#type' => 'html_tag',
          '#tag' => 'nav',
          '#attributes' => ['class' => ['pager'], 'role' => 'navigation'],
        ];
        $content['pager']['links'] = [
          '#theme' => 'item_list',
          '#items' => $items,
          '#attributes' => ['class' => ['pager__items']],
        ];
      }
    }

    if (\file_exists(DRUPAL_ROOT . '/libraries/jquery-countdown/dist/jquery.countdown.min.js')) {
      if ($this->configFactory->get('quiz.settings')->get('has_timer') && $quiz->time_limit->value) {
        $settings = [
          'since' => $quiz_result->time_start->value + $quiz->time_limit->value - $this->time->getRequestTime(),
          'format' => $this->t('Time left: :format', [':format' => $this->configFactory->get('quiz.settings')->get('timer_format')]),
          'id' => 'countdown-quiz-' . $quiz->id(),
        ];

        $content['body']['countdown'] = [
          '#markup' => '<div class="countdown-quiz-' . $quiz->id() . '"></div>',
        ];

        // Attach library containing js files.
        $content['#attached']['library'][] = 'quiz/countdown';
        $content['#attached']['drupalSettings']['jquery_countdown_quiz'][] = $settings;
      }
    }

    $form = $this->formBuilder->getForm(QuizQuestionAnsweringForm::class, $question, $this->quizSession->getResult($quiz));
    $content['body']['question'] = $form;

    return $content;
  }

  /**
   * Check take access to question number in a quiz.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The current quiz.
   * @param int $question_number
   *   The current question number.
   */
  public function checkAccess(Quiz $quiz, int $question_number): AccessResultInterface {
    return $this->checkEntityAccess('take', $quiz, $question_number);
  }

  /**
   * Check feedback access to question number in a quiz.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The current quiz.
   * @param int $question_number
   *   The current question number.
   */
  public function checkFeedbackAccess(Quiz $quiz, int $question_number): AccessResultInterface {
    return $this->checkEntityAccess('feedback', $quiz, $question_number);
  }

  /**
   * Generic check access to question number in a quiz.
   *
   * Translate the numeric question index to a question result answer, and run
   * the default entity access check on it.
   *
   * @param string $op
   *   An entity operation to check.
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz.
   * @param int $question_number
   *   The question number in the current result.
   */
  public function checkEntityAccess(string $op, Quiz $quiz, int $question_number): AccessResultInterface {
    $qra = $this->numberToQuestionResultAnswer($quiz, $question_number);
    return (!is_null($qra) && $qra->access($op)) ? AccessResultAllowed::allowed() : AccessResultForbidden::forbidden();
  }

  /**
   * Translate the numeric question index to a question result answer.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The current quiz.
   * @param int $question_number
   *   The current question number.
   *
   * @return \Drupal\quiz\Entity\QuizResultAnswer|null
   *   The question result answer, or NULL if the current result does not exist
   *   or does not contain this question.
   */
  public function numberToQuestionResultAnswer(Quiz $quiz, int $question_number): ?QuizResultAnswer {
    if ($quiz_result = QuizUtil::resultOrTemp($quiz)) {
      return $quiz_result->getLayout()[$question_number];
    }

    return NULL;
  }

  /**
   * Return the quiz title.
   */
  public static function getTitle(Quiz $quiz, $question_number) {
    return $quiz->label();
  }

}
