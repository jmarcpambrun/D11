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

/**
 * Remove display name setting.
 */
function quiz_post_update_remove_display_name_setting(): void {
  $quiz_settings = \Drupal::service('config.factory')->getEditable('quiz.settings');
  $quiz_settings->clear('\Drupal\quiz\Util\QuizUtil::getQuizName()')->save();
}

/**
 * Update the Quiz results View to use the current VBO selection settings.
 */
function quiz_post_update_update_quiz_results_vbo_settings(): void {
  $view = \Drupal::entityTypeManager()
    ->getStorage('view')
    ->load('quiz_results');

  if (!$view) {
    return;
  }

  $displays = $view->get('display');
  $updated = FALSE;

  foreach ($displays as &$display) {
    if (empty($display['display_options']['fields'])) {
      continue;
    }

    $fields = &$display['display_options']['fields'];
    foreach ($fields as &$field) {
      if (($field['plugin_id'] ?? NULL) !== 'views_bulk_operations_bulk_form'
        || !array_key_exists('force_selection_info', $field)) {
        continue;
      }

      $legacy_value = $field['force_selection_info'];
      if ($legacy_value === TRUE || $legacy_value === 1 || $legacy_value === '1' || $legacy_value === 'show') {
        $replacement_value = 'always_show';
      }
      elseif ($legacy_value === 'hide') {
        $replacement_value = 'always_hide';
      }
      else {
        $replacement_value = 'default';
      }

      $field['show_multipage_selection_box'] ??= $replacement_value;
      $field['show_select_all'] ??= $replacement_value;
      unset($field['force_selection_info']);
      $updated = TRUE;
    }
    unset($field);
  }
  unset($display);

  if ($updated) {
    $view->set('display', $displays);
    $view->save();
  }
}
