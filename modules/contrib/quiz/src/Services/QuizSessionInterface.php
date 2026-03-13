<?php

namespace Drupal\quiz\Services;

use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;

/**
 * Stores quiz state in the anonymous user's session.
 */
interface QuizSessionInterface {

  const RESULT_ID = 'result_id';

  const CURRENT_QUESTION = 'current_question';

  const TEMP_ID = 'temp';

  /**
   * Determine if the current user has a result or temporary quiz in session.
   *
   * @param \Drupal\quiz\Entity\Quiz|null $quiz
   *   The quiz.
   */
  public function isTakingQuiz(?Quiz $quiz = NULL);

  /**
   * Put a quiz result into the current user's session.
   *
   * @param \Drupal\quiz\Entity\QuizResult $quiz_result
   *   The quiz result.
   */
  public function startQuiz(QuizResult $quiz_result);

  /**
   * Remove quiz from session.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz.
   */
  public function removeQuiz(Quiz $quiz);

  /**
   * Get the current user's result for a Quiz in the session.
   *
   * @param \Drupal\quiz\Entity\Quiz|null $quiz
   *   The quiz.
   */
  public function getResult(?Quiz $quiz = NULL);

  /**
   * Get the current user's temporary result ID (for feedback/review).
   */
  public function getTemporaryResult();

  /**
   * Set a quiz result for the current user.
   *
   * @param \Drupal\quiz\Entity\QuizResult $quiz_result
   *   The quiz result.
   */
  public function setResult(QuizResult $quiz_result);

  /**
   * Set the user's temporary result ID (for feedback/review).
   *
   * @param \Drupal\quiz\Entity\QuizResult $quiz_result
   *   The quiz result.
   */
  public function setTemporaryResult(QuizResult $quiz_result);

  /**
   * Get the user's current question index for a quiz in the session.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz.
   *
   * @return int
   *   Question index starting at 1.
   */
  public function getCurrentQuestion(Quiz $quiz): int;

  /**
   * Set the user's current question.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz.
   * @param int $current_question
   *   The current question, starting at 1.
   */
  public function setCurrentQuestion(Quiz $quiz, int $current_question);

  /**
   * Checks for the survival of essential session variables during a quiz.
   *
   * @param int $quiz_id
   *   The quiz id extracted from the route.
   */
  public function isSessionSound(int $quiz_id): bool;

}
