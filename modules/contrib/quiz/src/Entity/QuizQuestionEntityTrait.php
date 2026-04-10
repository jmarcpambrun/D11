<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A trait all Quiz question strongly typed entity bundles must use.
 */
trait QuizQuestionEntityTrait {

  /*
   * QUESTION IMPLEMENTATION FUNCTIONS
   *
   * This part acts as a contract(/interface) between the question-types and the
   * rest of the system.
   *
   * Question types are made by extending these generic methods and abstract
   * methods.
   */

  /**
   * Allow question types to override the body field title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the body field.
   */
  public function getBodyFieldTitle(): TranslatableMarkup {
    return t('Question');
  }

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $form = [];
    $form['#element_validate'] = [[static::class, 'getAnsweringFormValidate']];
    return $form;
  }

  /**
   * Finds out if a question has been answered or not.
   *
   * This function also returns TRUE if a quiz that this question belongs to
   * have been answered. Even if the question itself haven't been answered.
   * This is because the question might have been rendered and a user is about
   * to answer it...
   *
   * @return bool
   *   TRUE if question has been answered or is about to be answered...
   */
  public function hasBeenAnswered(): bool {
    $result = \Drupal::entityQuery('quiz_result_answer')
      ->accessCheck(FALSE)
      ->condition('question_vid', $this->getRevisionId())
      ->range(0, 1)
      ->execute();
    return !empty($result);
  }

  /**
   * This may be overridden in subclasses.
   *
   * If it returns true, it means the max_score is updated for all occurrences
   * of this question in quizzes.
   *
   * @return bool
   *   If the question should be updated in all quizzes.
   */
  protected function autoUpdateMaxScore(): bool {
    return FALSE;
  }

  /**
   * Validate a user's answer.
   *
   * @param array $element
   *   The form element of this question.
   * @param mixed $form_state
   *   Form state.
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('quiz');
    $quiz = $storage->loadRevision($form_state->getCompleteForm()['#quiz']->getRevisionId());

    $qqid = $element['#array_parents'][1];

    // There was an answer submitted.
    /** @var QuizResultAnswer $qra */
    $qra = $element['#quiz_result_answer'];

    // Temporarily score the answer.
    $score = $qra->score($form_state->getValue('question')[$qqid]);

    // @todo kinda hacky here, we have to scale it temporarily so isCorrect()
    // works
    $qra->set('points_awarded', $qra->getWeightedRatio() * $score);

    if ($quiz->get('repeat_until_correct')->getString() && !$qra->isCorrect() && $qra->isEvaluated()) {
      $form_state->setErrorByName('', t('The answer was incorrect. Please try again.'));

      // Show feedback after incorrect answer.
      $view_builder = \Drupal::entityTypeManager()
        ->getViewBuilder('quiz_result_answer');
      $element['feedback'] = $view_builder->view($qra);
      $element['feedback']['#weight'] = 100;
      $element['feedback']['#parents'] = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isGraded(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasFeedback(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isQuestion(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(QuizResult $quiz_result): ?QuizResultAnswer {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_result_answer')
      ->loadByProperties([
        'result_id' => $quiz_result->id(),
        'question_id' => $this->id(),
        'question_vid' => $this->getRevisionId(),
      ]);
    return reset($entities);
  }

}
