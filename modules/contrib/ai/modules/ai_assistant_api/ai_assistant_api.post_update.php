<?php

/**
 * @file
 * This file contains the post update function detailed below.
 */

use Drupal\ai_assistant_api\AiAssistantInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Move settings into new fields to handle future restructure on config.
 */
function ai_assistant_api_post_update_settings(): void {
  /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $assistant */
  foreach (\Drupal::entityTypeManager()->getStorage('ai_assistant')->loadMultiple() as $assistant) {

    // Consolidate existing instructions into a single text.
    $instructions = $assistant->get('system_role') . PHP_EOL . $assistant->get('assistant_message') . PHP_EOL . $assistant->get('preprompt_instructions');

    // Empty out the previous fields ready for deletion.
    $assistant->set('system_role', '');
    $assistant->set('assistant_message', '');
    $assistant->set('preprompt_instructions', '');

    // Set the new instructions.
    $assistant->set('instructions', $instructions);

    $path = \Drupal::service('module_handler')->getModule('ai_assistant_api')->getPath() . '/resources/';
    // Move the pre-action-=prompt to its new field, fill with txt file.
    $assistant->set('system_prompt', file_get_contents($path . 'system_prompt.txt'));

    // Clear out the old field.
    $assistant->set('pre_action_prompt', file_get_contents($path . 'pre_action_prompt.txt'));

    // Save the updated config.
    $assistant->save();
  }
}

/**
 * Converts allow_history to a ChatMemory plugin selection.
 */
function ai_assistant_api_post_update_convert_allow_history(&$sandbox): void {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (AiAssistantInterface $assistant) {
    $map = [
      'session_one_thread' => 'private_tempstore',
      'session' => 'private_tempstore_pool',
    ];
    $settings = [];
    $new_id = $map[$assistant->get('allow_history')] ?? '';
    $assistant->set('allow_history', $new_id);
    if (!empty($new_id)) {
      $settings += [
        'expiry' => 604800,
        'max_messages' => $assistant->get('history_context_length'),
      ];
    }
    $assistant->set('chat_memory_settings', $settings);
    return TRUE;
  };

  $config_entity_updater->update($sandbox, 'ai_assistant', $callback);
}
