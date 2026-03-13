<?php

/**
 * @file
 * Post update functions for Quiz.
 */

/**
 * Update quiz setting after schema updates.
 */
function quiz_post_update_config_schema_update(): void {
  $quiz_settings = \Drupal::service('config.factory')->getEditable('quiz.settings');
  $options_end = $quiz_settings->get('admin_review_options_end');
  $options_question = $quiz_settings->get('admin_review_options_question');

  if (!empty($options_end)) {
    if (empty($options_end['quiz_question_view_full'])) {
      $options_end['quiz_question_view_full'] = FALSE;
    }
    if (empty($options_end['quiz_question_view_question'])) {
      $options_end['quiz_question_view_question'] = FALSE;
    }
    $quiz_settings->set('admin_review_options_end', $options_end);
  }

  if (!empty($options_question)) {
    if (empty($options_question['quiz_question_view_full'])) {
      $options_question['quiz_question_view_full'] = FALSE;
    }
    if (empty($options_question['quiz_question_view_question'])) {
      $options_question['quiz_question_view_question'] = FALSE;
    }
    $quiz_settings->set('admin_review_options_question', $options_question);
  }
  $quiz_settings->save();

  // Just resave quiz_matching.setting config.
  \Drupal::service('config.factory')->getEditable('quiz_matching.settings')->save();
}

/**
 * Add timer format setting.
 */
function quiz_post_update_add_timer_format_setting(): void {
  $quiz_settings = \Drupal::service('config.factory')->getEditable('quiz.settings');
  $quiz_settings->set('timer_format', '%-H h %M min %S sec')->save();
}
