<?php

/**
 * @file
 * Hook_post_update_NAME functions for tfa module.
 */

/**
 * Redirect users to TFA setup page default to disabled.
 */
function tfa_post_update_add_redirect_to_users_without_tfa(array &$sandbox): void {
  $config = \Drupal::configFactory()->getEditable('tfa.settings');
  $config->set('users_without_tfa_redirect', FALSE);
  $config->save();
}

/**
 * Update TFA user data to remove SMS field.
 */
function tfa_post_update_user_data_0001_remove_sms(array &$sandbox): void {
  if (!isset($sandbox['total'])) {
    $uids = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->execute();
    $sandbox['total'] = count($uids);
    $sandbox['current'] = 0;

    if (empty($sandbox['total'])) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $users_per_batch = 25;
  $uids = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->range($sandbox['current'], $users_per_batch)
    ->execute();
  if (empty($uids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\user\UserDataInterface $user_data_service */
  $user_data_service = \Drupal::service('user.data');

  foreach ($uids as $uid) {
    $sandbox['current']++;
    $user_data = $user_data_service->get('tfa', (int) $uid, 'tfa_user_settings');
    if ($user_data == NULL) {
      // User has no TFA data.
      continue;
    }
    if (!is_array($user_data)) {
      \Drupal::messenger()
        ->addError(t("UID ':uid' has corrupt user data, not upgraded.", [':uid', $uid]));
      continue;
    }

    if (array_key_exists('data', $user_data) && array_key_exists('sms', $user_data['data'])) {
      unset($user_data['data']['sms']);
      $user_data_service->set('tfa', (int) $uid, 'tfa_user_settings', $user_data);
    }
  }

  $sandbox['progress_message'] = "Processed record {$sandbox['current']} of {$sandbox['total']}";

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}

/**
 * Update block config to no longer use the tfa_user_login block.
 */
function tfa_post_update_replace_tfa_user_login_block(array &$sandbox): void {
  $result = \Drupal::entityQuery('block')->condition('plugin', 'tfa_user_login_block')->accessCheck(FALSE)->execute();
  $storage = \Drupal::entityTypeManager()->getStorage('block');

  foreach ($result as $key) {
    $block = $storage->load($key);
    if ($block == NULL) {
      continue;
    }
    $block->set('plugin', 'user_login_block');
    $settings = $block->get('settings');
    if (!is_array($settings)) {
      continue;
    }
    $settings['provider'] = 'user';
    $block->set('settings', $settings);
    $block->save();
  }
}

/**
 * Convert TOTP plugin to exclusively use time slice for replay protection.
 */
function tfa_post_update_convert_totp_from_accepted_codes(array &$sandbox): void {
  if (!isset($sandbox['total'])) {
    $uids = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->execute();
    $sandbox['total'] = count($uids);
    $sandbox['current'] = 0;

    if (empty($sandbox['total'])) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $users_per_batch = 25;
  $uids = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->range($sandbox['current'], $users_per_batch)
    ->execute();
  if (empty($uids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  $request_time = \Drupal::service('datetime.time')->getRequestTime();

  /** @var \Drupal\user\UserDataInterface $user_data_service */
  $user_data_service = \Drupal::service('user.data');

  $plugin_config = \Drupal::config('tfa.settings')->get('validation_plugin_settings');
  $time_skew = 2;
  if (is_array($plugin_config) && isset($plugin_config['tfa_totp']['time_skew'])) {
    $time_skew = (int) $plugin_config['tfa_totp']['time_skew'];
  }

  foreach ($uids as $uid) {
    $sandbox['current']++;
    $user_data = $user_data_service->get('tfa', (int) $uid, NULL);
    if (!is_array($user_data) || empty($user_data)) {
      // User has no TFA data.
      continue;
    }

    if (array_key_exists('tfa_totp_time_window', $user_data)) {
      // User already has a time slice stored, we don't need to set one.
      continue;
    }

    if (!array_key_exists('tfa_totp_seed', $user_data)) {
      // User does not have a TOTP token.
      continue;
    }

    $most_recent_accepted_timestamp = NULL;
    foreach ($user_data as $key => $value) {

      if (str_starts_with($key, 'tfa_accepted_code_')) {
        if (!is_string($value) && !is_int($value)) {
          // Only process strings and ints.
          continue;
        }

        if ((int) $value > $most_recent_accepted_timestamp) {
          $most_recent_accepted_timestamp = (int) $value;
        }
      }
    }

    // If there is no last time window use the current request time.
    $most_recent_accepted_timestamp = $most_recent_accepted_timestamp ?: $request_time;

    // In order to prevent reply attacks, assume the code was accepted at the
    // max time future time skew.
    $time_slice = (int) (floor($most_recent_accepted_timestamp / 30) + $time_skew);
    $user_data_service->set('tfa', (int) $uid, 'tfa_totp_time_window', $time_slice);
  }

  $sandbox['progress_message'] = "Processed record {$sandbox['current']} of {$sandbox['total']}";

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}
