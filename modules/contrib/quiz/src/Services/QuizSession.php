<?php

namespace Drupal\quiz\Services;

use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Default implementation of the quiz session.
 */
class QuizSession implements QuizSessionInterface {

  /**
   * Constructs a new QuizSession object.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   */
  public function __construct(protected SessionInterface $session) {
  }

  /**
   * {@inheritdoc}
   */
  public function isTakingQuiz(?Quiz $quiz = NULL): bool {
    return (bool) $this->getResult($quiz);
  }

  /**
   * {@inheritdoc}
   */
  public function startQuiz(QuizResult $quiz_result): void {
    $current_quizzes = $this->getCurrentQuizzes();
    $current_quizzes[$quiz_result->getQuiz()->id()][self::RESULT_ID] = $quiz_result->id();
    $current_quizzes[$quiz_result->getQuiz()->id()][self::CURRENT_QUESTION] = 1;
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function removeQuiz(Quiz $quiz): void {
    $current_quizzes = $this->getCurrentQuizzes();
    unset($current_quizzes[$quiz->id()]);
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function getResult(?Quiz $quiz = NULL) {
    $current_quizzes = $this->getCurrentQuizzes();
    if ($quiz && isset($current_quizzes[$quiz->id()]) && !empty($current_quizzes[$quiz->id()][self::RESULT_ID])) {
      $result_id = $current_quizzes[$quiz->id()][self::RESULT_ID];
      return QuizResult::load($result_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemporaryResult() {
    $current_quizzes = $this->getCurrentQuizzes();
    if (!empty($current_quizzes[self::TEMP_ID])) {
      $result_id = $current_quizzes[self::TEMP_ID];
      return QuizResult::load($result_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setResult(QuizResult $quiz_result) {
    $current_quizzes = $this->getCurrentQuizzes();
    $current_quizzes[$quiz_result->getQuiz()->id()][self::RESULT_ID] = $quiz_result->id();
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function setTemporaryResult(QuizResult $quiz_result) {
    $current_quizzes = $this->getCurrentQuizzes();
    $current_quizzes[self::TEMP_ID] = $quiz_result->id();
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentQuestion(Quiz $quiz): int {
    $current_quizzes = $this->getCurrentQuizzes();
    if (isset($current_quizzes[$quiz->id()])) {
      return !empty($current_quizzes[$quiz->id()][self::CURRENT_QUESTION]) ? $current_quizzes[$quiz->id()][self::CURRENT_QUESTION] : 1;
    }
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentQuestion(Quiz $quiz, int $current_question) {
    $current_quizzes = $this->getCurrentQuizzes();
    if (isset($current_quizzes[$quiz->id()])) {
      $current_quizzes[$quiz->id()][self::CURRENT_QUESTION] = $current_question;
      $this->setCurrentQuizzes($current_quizzes);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isSessionSound(int $quiz_id): bool {
    $current_quizzes = $this->getCurrentQuizzes();
    return !empty($current_quizzes[$quiz_id][self::RESULT_ID]) && !empty($current_quizzes[$quiz_id][self::CURRENT_QUESTION]);
  }

  /**
   * Gets the current quizzes the user is taking.
   *
   * @return array
   *   The quizzes
   */
  protected function getCurrentQuizzes() {
    $key = $this->getSessionKey();
    return $this->session->get($key, []);
  }

  /**
   * Sets the current quizzes the user is taking.
   */
  protected function setCurrentQuizzes(array $current_quizzes): void {
    $key = $this->getSessionKey();
    if (count($current_quizzes) == 0) {
      $this->session->remove($key);
    }
    else {
      $this->session->set($key, $current_quizzes);
    }
  }

  /**
   * Gets the session key for the quiz session type.
   *
   * @return string
   *   The session key.
   */
  protected function getSessionKey(): string {
    return 'quiz';
  }

}
