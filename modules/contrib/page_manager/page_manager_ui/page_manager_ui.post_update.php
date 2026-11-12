<?php

/**
 * @file
 * Post update functions for Page Manager UI.
 */

/**
 * Force cache clear to ensure aggregated JavaScript files are regenerated.
 *
 * @see https://www.drupal.org/project/page_manager/issues/2921404
 */
function page_manager_ui_post_update_variant_selector_bigpipe_clear_cache() {
  // Empty post-update hook.
}

/**
 * Rebuild routes so the page enable and disable routes require a CSRF token.
 */
function page_manager_ui_post_update_page_operation_csrf() {
  // Empty post-update hook.
}
