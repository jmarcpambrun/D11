<?php

namespace Drupal\quiz;

use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * Provides an interface for quiz questions.
 */
interface QuizQuestionInterface {

  /**
   * Get the form through which the user will answer the question.
   *
   * Question types should populate the form with selected values from the
   * current result if possible.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param \Drupal\quiz\Entity\QuizResultAnswer $answer
   *   The quiz result answer.
   *
   * @return array
   *   Form array.
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $answer): array;

  /**
   * Get the maximum possible score for this question.
   *
   * @return int
   *   Max score.
   */
  public function getMaximumScore(): int;

  /**
   * Is this question graded?
   *
   * Questions like Quiz Directions, Quiz Page, and Scale are not.
   *
   * By default, questions are expected to be gradable
   *
   * @return bool
   *   If the question has been graded.
   */
  public function isGraded(): bool;

  /**
   * Does this question type give feedback?
   *
   * Questions like Quiz Directions and Quiz Pages do not.
   *
   * By default, questions give feedback
   *
   * @return bool
   *   If the question has feedback.
   */
  public function hasFeedback(): bool;

  /**
   * Is this "question" an actual question?
   *
   * For example, a Quiz Page is not a question, neither is a "quiz directions".
   *
   * Returning FALSE here means that the question will not be numbered, and
   * possibly other things.
   *
   * @return bool
   *   If the question is actually a question.
   */
  public function isQuestion(): bool;

  /**
   * Get the response to this question in a quiz result.
   *
   * @param \Drupal\quiz\Entity\QuizResult $quiz_result
   *   Quiz result to get for response.
   *
   * @return \Drupal\quiz\Entity\QuizResultAnswer|null
   *   Quiz result answer or NULL if no result.
   */
  public function getResponse(QuizResult $quiz_result): ?QuizResultAnswer;

}
