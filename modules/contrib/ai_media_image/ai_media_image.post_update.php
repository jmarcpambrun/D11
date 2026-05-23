<?php

/**
 * @file
 * Post-update functions for AI Media Image module.
 */

/**
 * Update configuration to manage provider_configuration_open config.
 */
function ai_media_image_post_update_add_settings(): void {
  $config = \Drupal::service('config.factory')->getEditable('ai_media_image.settings');
  $provider_configuration_open = $config->get('provider_configuration_open');
  if ($provider_configuration_open === NULL) {
    $config->set('provider_configuration_open', TRUE)->save();
  }
}
