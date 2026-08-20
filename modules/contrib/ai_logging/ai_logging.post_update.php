<?php

/**
 * @file
 * Post update functions for AI Logging.
 */

/**
 * Converts prompt_logging_output to boolean and removes unused bundles key.
 */
function ai_logging_post_update_fix_logging_config(&$sandbox) {
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('ai_logging.settings');

  // Remove the unused 'prompt_logging_bundles' key.
  $config->clear('prompt_logging_bundles');

  // Convert 'prompt_logging_output' to a strict boolean.
  $output_setting = $config->get('prompt_logging_output');
  $config->set('prompt_logging_output', (bool) $output_setting);
  $config->save();

  return t('Fixed AI Logging configuration types and removed unused keys.');
}
