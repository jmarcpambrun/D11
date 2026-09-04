<?php

/**
 * @file
 * Contains post_update hooks for ai_agents.
 */

/**
 * Re-run update hook 10307 as it was skipped when updating from 1.1.x to 1.2.x.
 */
function ai_agents_post_update_10001() {
  $entity_type_manager = \Drupal::entityTypeManager();
  $storage = $entity_type_manager->getStorage('ai_agent');

  // Load all agents.
  $agents = $storage->loadMultiple();

  foreach ($agents as $agent) {
    $tool_usage_limits = $agent->get('tool_usage_limits') ?? [];
    $changed = FALSE;
    foreach ($tool_usage_limits as $tool_id => $properties) {
      // Clean up property restrictions.
      if (!is_array($properties) || empty($properties)) {
        continue;
      }
      foreach ($properties as $property_name => $values) {
        // Check if values exists.
        if (!empty($values['values']) && is_array($values['values'])) {
          foreach ($values['values'] as $key => $value) {
            // If the value ends with a \r, remove it.
            if (substr($value, -1) === "\r") {
              $tool_usage_limits[$tool_id][$property_name]['values'][$key] = rtrim($value, "\r");
              $changed = TRUE;
            }
            // Remove empty values.
            elseif ($value === '') {
              unset($tool_usage_limits[$tool_id][$property_name]['values'][$key]);
              $changed = TRUE;
            }
          }
        }
      }
    }
    // Set the cleaned tool usage limits back to the agent.
    if ($changed) {
      $agent->set('tool_usage_limits', $tool_usage_limits);
      $agent->save();
    }
  }
}

/**
 * Update to set the value for the new hostname_filter_disabled property.
 */
function ai_agents_post_update_10002() {
  $entity_type_manager = \Drupal::entityTypeManager();
  $storage = $entity_type_manager->getStorage('ai_agent');

  // Load all agents.
  $agents = $storage->loadMultiple();

  foreach ($agents as $agent) {
    // Set the default value for hostname_filter_disabled to FALSE if not set.
    if ($agent->get('hostname_filter_disabled') === NULL) {
      $agent->set('hostname_filter_disabled', FALSE);
      $agent->save();
    }
  }
}

/**
 * Sets defaults for the new short term memory properties on existing agents.
 */
function ai_agents_post_update_10003() {
  $config_factory = \Drupal::configFactory();

  foreach ($config_factory->listAll('ai_agents.ai_agent.') as $config_name) {
    $config = $config_factory->getEditable($config_name);
    $changed = FALSE;

    if ($config->get('short_term_memory_plugin') === NULL) {
      $config->set('short_term_memory_plugin', '');
      $changed = TRUE;
    }
    if ($config->get('short_term_memory_config') === NULL) {
      $config->set('short_term_memory_config', []);
      $changed = TRUE;
    }

    if ($changed) {
      $config->save();
    }
  }
}
