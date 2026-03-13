<?php

namespace Drupal\quiz\Controller;

use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Form\QuizQuestionsForm;
use Drupal\quiz\Services\QuizSessionInterface;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for quiz actions.
 */
class QuizController extends EntityController {

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
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity Field manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entityFormBuilder
   *   The entity form builder service.
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
    protected MessengerInterface $messenger,
    protected AccountProxyInterface $currentUser,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected FormBuilderInterface $formBuilder,
    protected EntityFormBuilderInterface $entityFormBuilder,
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
      $container->get('messenger'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('form_builder'),
      $container->get('entity.form_builder'),
    );
  }

  /**
   * Take the quiz.
   *
   * @return mixed
   *   Response to the take request.
   */
  public function take(Quiz $quiz) {
    $page = [];
    /** @var \Drupal\Core\Access\AccessResultReasonInterface $result */
    $result = $quiz->access('take', NULL, TRUE);

    $message = '';
    if (is_subclass_of($result, AccessResultReasonInterface::class)) {
      $message = $result->getReason();
    }
    $success = !$result->isForbidden();

    if (!$success) {
      // Not allowed.
      $page['body']['#markup'] = $message;
      return $page;
    }
    elseif ($message) {
      // Allowed, but we have a message.
      $this->messenger->addMessage($message);
    }

    if ($quiz_result = $this->resume($quiz)) {
      // Resuming attempt.
      if (!empty($quiz_result->resume)) {
        // Show a message if this was reloaded from the database and not just
        // from the session.
        $this->messenger->addStatus($this->t('Resuming a previous @quiz in-progress.', ['@quiz' => QuizUtil::getQuizName()]));
      }
      return $this->redirect('quiz.question.take', [
        'quiz' => $quiz->id(),
        'question_number' => $this->quizSession->getCurrentQuestion($quiz),
      ]);
    }
    else {
      // Create new result.
      if ($success) {
        // Test a build of questions.
        $questions = $quiz->buildLayout();
        if (empty($questions)) {
          $this->messenger->addError($this->t('Not enough questions were found. Please add more questions before trying to take this @quiz.', ['@quiz' => QuizUtil::getQuizName()]));
          return $this->redirect('entity.quiz.canonical', ['quiz' => $quiz->id()]);
        }

        // Create a new Quiz result.
        $quiz_result = QuizResult::create([
          'qid' => $quiz->id(),
          'vid' => $quiz->getRevisionId(),
          'uid' => $this->currentUser->id(),
          'type' => $quiz->get('result_type')->getString(),
        ]);

        $build_on_last = $quiz->get('build_on_last')->getString() != 'fresh' && $quiz_result->findOldResult();
        $instances = $this->entityFieldManager
          ->getFieldDefinitions('quiz_result', $quiz->get('result_type')->getString());
        foreach ($instances as $field_name => $field) {
          if ($build_on_last || (is_a($field, FieldConfig::class) && $field->getThirdPartySetting('quiz', 'show_field'))) {
            // We found a field to be filled out.
            $redirect_url = Url::fromRoute('entity.quiz.take', [
              'quiz' => $quiz_result->getQuiz()->id(),
            ]);
            $form = $this->entityFormBuilder
              ->getForm($quiz_result, 'default', ['redirect' => $redirect_url]);
            return $form;
          }
        }
      }
      else {
        $page['body']['#markup'] = $result['message'];
        return $page;
      }
    }

    // New attempt.
    $quiz_result->save();
    $this->quizSession->startQuiz($quiz_result);
    return $this->redirect('quiz.question.take', [
      'quiz' => $quiz->id(),
      'question_number' => 1,
    ]);
  }

  /**
   * Creates a form for quiz questions.
   *
   * Handles the manage questions tab.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz we are managing questions for.
   *
   * @return array
   *   Array containing the form.
   */
  public function manageQuestions(Quiz $quiz): array {
    if ($quiz->get('randomization')->getString() < 3) {
      return $this->formBuilder->getForm(QuizQuestionsForm::class, $quiz);
    }
    else {
      $form = $this->entityTypeManager
        ->getFormObject('quiz', 'default')
        ->setEntity($quiz);
      $form = $this->formBuilder->getForm($form);
    }

    foreach (Element::children($form) as $key) {
      if (in_array($key, array_keys($quiz->getFieldDefinitions())) || $form[$key]['#type'] == 'details') {
        if (!in_array($key, ['quiz_terms', 'random', 'quiz'])) {
          $form[$key]['#access'] = FALSE;
        }
      }
    }
    return $form;
  }

  /**
   * Resume a quiz.
   *
   * Search the database for an in progress attempt, and put it back into the
   * session if allowed.
   *
   * @return \Drupal\quiz\Entity\QuizResult|bool
   *   Either the Quiz result or FALSE if not found.
   */
  public function resume($quiz) {
    // Make sure we use the same revision of the quiz throughout the quiz taking
    // session.
    $quiz_result = $this->quizSession->getResult($quiz);
    if ($quiz_result) {
      return $quiz_result;
    }
    else {
      // User doesn't have attempt in session.
      // If we allow resuming we can load it from the database.
      if ($quiz->get('allow_resume')->getString() && $this->currentUser->isAuthenticated()) {
        if ($quiz_result = $quiz->getResumeableResult($this->currentUser)) {
          // Put the result in the user's session.
          $this->quizSession->startQuiz($quiz_result);

          // Now advance the user to after the last answered question.
          $prev = NULL;
          foreach ($quiz_result->getLayout() as $qra) {
            if ($prev) {
              if ($qra->get('answer_timestamp')->isEmpty() && !$prev->get('answer_timestamp')->isEmpty()) {
                // This question has not been answered, but the previous
                // question has.
                $this->quizSession->setCurrentQuestion($quiz, $qra->get('number')->getString());
              }
            }

            $prev = clone $qra;
          }

          // Mark this quiz as being resumed from the database.
          $quiz_result->resume = TRUE;
          return $quiz_result;
        }
      }
    }

    return FALSE;
  }

}
