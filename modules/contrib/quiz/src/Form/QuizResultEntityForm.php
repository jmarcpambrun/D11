<?php

namespace Drupal\quiz\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Services\QuizSessionInterface;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Quiz result authoring form.
 */
class QuizResultEntityForm extends ContentEntityForm {

  use MessengerTrait;

  /**
   * Constructs a QuizResultEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\quiz\Services\QuizSessionInterface $quizSession
   *   The quiz session service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    protected QuizSessionInterface $quizSession,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('quiz.session')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Add the questions in this result to the edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\quiz\Entity\QuizResult $quiz_result */
    $quiz_result = $this->entity;
    if ($quiz_result->isNew()) {
      $quiz = $quiz_result->getQuiz();

      if ($quiz_result->findOldResult()) {
        $form['build_on_last'] = [
          '#title' => $this->t('Keep answers from last attempt?'),
          '#type' => 'radios',
          '#options' => [
            'fresh' => $this->t('No answers'),
            'correct' => $this->t('Only correct answers'),
            'all' => $this->t('All answers'),
          ],
          '#default_value' => $quiz->get('build_on_last')->getString(),
          '#description' => $this->t('You can choose to keep previous answers or start a new attempt.'),
          '#access' => $quiz->get('build_on_last')->getString() != 'fresh',
        ];
      }
      $form = parent::buildForm($form, $form_state);
      $form['actions']['submit']['#value'] = $this->t('Start @quiz', ['@quiz' => QuizUtil::getQuizName()]);
    }
    else {
      $form['question']['#tree'] = TRUE;
      $render_controller = $this->entityTypeManager->getViewBuilder('quiz_result_answer');
      foreach ($quiz_result->getLayout() as $layoutIdx => $qra) {
        if ($qra->getQuizQuestion()->isGraded()) {
          // Set up a fieldset to show the feedback.
          $form['question'][$layoutIdx] = [
            '#title' => $this->t('Question @num', ['@num' => $qra->get('display_number')->value]),
            '#type' => 'fieldset',
            'feedback' => $render_controller->view($qra),
          ];

          // Append any form elements for scoring.
          $form['question'][$layoutIdx] += $qra->getReportForm();
        }
      }

      $form = parent::buildForm($form, $form_state);
      $form['actions']['submit']['#value'] = $this->t('Save score');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Additionally, update the score and feedback of the questions in this
   * result.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\quiz\Entity\QuizResult $quiz_result */
    $quiz_result = $this->entity;
    if ($quiz_result->isNew()) {
      $quiz_result->build_on_last = $form_state->getValue('build_on_last');
    }
    else {
      $layout = $this->entity->getLayout();

      // Update questions.
      foreach ($form_state->getValue('question') as $layoutIdx => $question) {
        $qra = $layout[$layoutIdx];
        $qra->set('points_awarded', $question['score']);
        $qra->set('answer_feedback', $question['answer_feedback']);
        // The administrator is grading the questions.
        $qra->setEvaluated();
        $qra->save();
      }

      // Finalize result.
      $quiz_result->finalize();

      // Notify the user if results got deleted as a result of a scoring an
      // answer.
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('quiz');
      $quiz = $storage->loadRevision($quiz_result->get('vid')->getString());
      $results_got_deleted = $quiz_result->maintainResults();
      $add = '';
      if ($quiz->get('keep_results')->getString() == Quiz::KEEP_BEST && $results_got_deleted) {
        $add = $this->t('Note that this @quiz is set to only keep each users best answer.', ['@quiz' => QuizUtil::getQuizName()]);
      }
      $this->messenger()->addMessage($this->t('The scoring data you provided has been saved.') . $add);
    }

    // Update the result.
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Start the quiz result if necessary.
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $new = $this->entity->isNew();

    // Save the quiz result.
    parent::save($form, $form_state);

    if ($new) {
      // The user submitted a quiz result form to start a new attempt. Set the
      // quiz result in the session.
      $this->quizSession->startQuiz($this->entity);
    }
  }

}
