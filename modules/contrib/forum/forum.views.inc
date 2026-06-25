<?php

/**
 * @file
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\forum\Hook\ForumViewsHooks;

/**
 * @file
 * Provide views data for forum.module.
 */

/**
 * Implements hook_views_data().
 */
#[LegacyHook]
function forum_views_data() {
  return \Drupal::service(ForumViewsHooks::class)->viewsData();
}
