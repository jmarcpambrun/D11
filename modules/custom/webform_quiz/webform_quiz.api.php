<?php

/**
 * @file
 * Hooks related to Webform Quiz module.
 */

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_quiz\QuizResults;

/**
 * Perform ajax actions for when a user clicks a quiz response.
 *
 * @param \Drupal\Core\Ajax\AjaxResponse $ajax_response
 *   The AjaxResponse class.
 * @param array $element
 *   The element variable.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The FormStateInterface array.
 */
function hook_webform_quiz_correct_answer_shown(AjaxResponse $ajax_response, array $element, FormStateInterface $form_state) {

}

/**
 * Alter the results display of a webform quiz submission.
 *
 * @param array $build
 *   The build render array.
 * @param \Drupal\webform_quiz\QuizResults $results
 *   The results QuizResults object.
 */
function hook_webform_quiz_results_display_alter(array &$build, QuizResults $results) {
  $build['share'] = [
    '#type' => 'container',
  ];
  $build['share']['contents']['#markup'] = t('Share results');
}
