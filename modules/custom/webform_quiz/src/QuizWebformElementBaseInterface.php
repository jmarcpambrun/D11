<?php

namespace Drupal\webform_quiz;

interface QuizWebformElementBaseInterface {
	const WEBFORM_ELEMENT_MODE_QUIZ = 'quiz';
	const WEBFORM_ELEMENT_MODE_SURVEY = 'survey';

  /** 
   * if this elements demands to from user
   * to have 0 missed correct answers and 0 mistaken picked answers.
   * Used in QuizResults::compareAnswer().
   * 
   * @return boolean
   */
  public function isStrictCompare(array $element, ?array $caws = null);

  /**
   * if to render the correct answer field in the form.
   * For select, checkboxes, radios, etc ... it is not rendered,
   * as its managed from options form, but field correct_answer
   * still kept and populated in background for polymorphism
   *
   * @return boolean
   */
  public function correctAnswerFieldVisible();

  /**
   * If elemets settings allow selecting multiple answers.
   *
   * @param array $element element configuration for webform
   * @return bool
   */
  public static function supportsMultiple($element);

  /**
   * This function is used to remove all not important data from the value, 
   * which is user input (answer) or Admin UI provided options (answers).
   * 
   * For example it is used in WebformQuizTextField to remove spaces, dashes, 
   * make lowercase, etc ..., for futher comparison of user answer with preset answers.
   *
   * @param mixed $value
   * @return mixed
   */
  public static function prepareValueForCompare($value = NULL);

  /**
   * Use this function to get the correct answers with scores and descriptions.
   * Avoid using $element['#correct_answer'] directly, as it can be not set.
   * This function provides polymorphic behaviour.
   *
   * @param array $element element configuration for webform
   * @param bool|null $prepare_for_compare if answer_key should ber prepared for comparison
   *  using prepareValueForCompare, null will make prepare if $this is not instanceof Options
   * @return array [ answer_key => [text => ..., description => ..., answer_score => ...] ]
   */
  public function getCorrectAnswersWithScores($element, $prepare_for_compare = null);

  /**
   * Calculates the maximum score possible for a quiz element.
   *
   * @param array $element The quiz element.
   * @param array|null $caws The correct answers with scores. If not provided, it will be fetched using getCorrectAnswersWithScores().
   * @return int The maximum score possible.
   */
  public function getMaxScorePossible(array $element, ?array $caws = null);

  /**
   * Calculates the sum of scores from an array of answers.
   * Should be used for multiselect options
   *
   * @param array $caws
   *   An array of answers, where each answer is an associative array with a
   *   'answer_score' key representing the score.
   *   See getCorrectAnswersWithScores() for structure
   *
   * @return int
   *   The sum of scores from the given array of answers.
   */
  public function getScoresSum(array $caws);

  /**
   * Calculates the maximum score from an array of answers.
   * Should be used for single-select options
   *
   * @param array $caws
   *   An array of answers, where each answer is an associative array with a
   *   'answer_score' key representing the score.
   *   See getCorrectAnswersWithScores() for structure
   * 
   * @return int The maximum score.
   */
  public function getMaxScore(array $caws);
}