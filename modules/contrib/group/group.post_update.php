<?php

/**
 * @file
 * Post update functions for Group.
 */

/**
 * Implements hook_removed_post_updates().
 */
function group_removed_post_updates(): array {
  return [
    'group_post_update_group_type_group_content_type_dependencies' => '2.0.0',
    'group_post_update_group_content_type_dependencies' => '2.0.0',
    'group_post_update_grant_access_overview_permission' => '2.0.0',
    'group_post_update_view_cache_contexts' => '2.0.0',
    'group_post_update_make_group_revisionable' => '2.0.0',
  ];
}
