<?php

/**
 * @file
 * Post update functions for tfa.
 */

/**
 * Implements hook_post_update_NAME().
 *
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
