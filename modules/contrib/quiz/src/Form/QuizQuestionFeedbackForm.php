<?php

namespace Drupal\quiz\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\quiz\Services\QuizSessionInterface;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Post-question feedback form.
 */
class QuizQuestionFeedbackForm extends FormBase {

  /**
   * QuizQuestionFeedbackForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\quiz\Services\QuizSessionInterface $quizSession
   *   The quiz session service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QuizSessionInterface $quizSession,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('quiz.session'),
    );
  }

  /**
   * Show feedback for a question response.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $quiz = $form_state->getBuildInfo()['args'][0];
    $question_number = $form_state->getBuildInfo()['args'][1];
    $quiz_result = QuizUtil::resultOrTemp($quiz);
    $form = [];

    $form['actions']['#type'] = 'actions';

    if (!$quiz_result->get('time_end')->isEmpty()) {
      // Quiz is done.
      $form['actions']['finish'] = [
        '#type' => 'submit',
        '#submit' => ['::submitEnd'],
        '#value' => $this->t('Finish'),
      ];
    }
    else {
      $form['actions']['next'] = Link::createFromRoute($this->t('Next question'), 'quiz.question.take', [
        'quiz' => $quiz->id(),
        'question_number' => $question_number + 1,
      ], ['attributes' => ['class' => ['button']]])->toRenderable();
    }

    $view_builder = $this->entityTypeManager->getViewBuilder('quiz_result_answer');

    // Add feedback.
    $out = [];

    foreach ($quiz_result->getLayout() as $question) {
      if ($question->get('number')->getString() == $question_number &&
        $question->qqr_pid) {
        // Question is in a page.
        foreach ($quiz_result->getLayout() as $qra) {
          if ($qra->qqr_pid == $question->qqr_pid) {
            $out[] = [
              '#title' => $this->t('Question @num', [
                '@num' => $qra->get('display_number')->getString(),
              ]),
              '#type' => 'fieldset',
              'feedback' => $view_builder->view($qra),
            ];
          }
        }
      }
    }

    // Single question.
    if (empty($out)) {
      $qra = $quiz_result->getLayout()[$question_number];

      $feedback = $view_builder->view($qra);

      $out[] = [
        '#title' => $this->t('Question @num', [
          '@num' => $quiz_result->getLayout()[$question_number]->get('display_number')->getString(),
        ]),
        '#type' => 'fieldset',
        'feedback' => $feedback,
      ];
    }

    $form['feedback'] = $out;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'quiz_take_question_feedback_form';
  }

  /**
   * Submit handler to go to the next question from the question feedback.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $quiz = $form_state->getBuildInfo()['args'][0];
    $quiz_session = $this->quizSession;
    $form_state->setRedirect('quiz.question.take', [
      'quiz' => $quiz->id(),
      'question_number' => $quiz_session->getCurrentQuestion($quiz),
    ]);
  }

  /**
   * Submit handler to go to the quiz results from the last question's feedback.
   */
  public function submitEnd($form, &$form_state): void {
    $quiz_session = $this->quizSession;
    $quiz_result = $quiz_session->getTemporaryResult();
    $quiz = $form_state->getBuildInfo()['args'][0];
    $form_state->setRedirect('entity.quiz_result.canonical', [
      'quiz' => $quiz->id(),
      'quiz_result' => $quiz_result->id(),
    ]);
  }

}
