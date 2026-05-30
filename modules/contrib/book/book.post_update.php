<?php

/**
 * @file
 * Post update functions for the book module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\block\Entity\Block;
use Drupal\user\RoleInterface;

/**
 * Pre-populate the use_top_level_title setting of the book_navigation blocks.
 */
function book_post_update_prepopulate_use_top_level_title_setting(&$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'block', function (Block $block) {
    if ($block->getPluginId() === 'book_navigation') {
      $block->getPlugin()->setConfigurationValue('use_top_level_title', FALSE);
      return TRUE;
    }
    return FALSE;
  });
}

/**
 * Update extra book field for entity view displays.
 */
function book_post_update_book_navigation_view_display(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (EntityViewDisplayInterface $entity_view_display): bool {
    $update = FALSE;
    $components = $entity_view_display->getComponents();
    if ($entity_view_display->getTargetEntityTypeId() === 'node') {
      if (isset($components['book_navigation'])) {
        if ($entity_view_display->getMode() !== 'full' || $entity_view_display->getMode() !== 'default') {
          $updated_component = $entity_view_display->getComponent('book_navigation');
          $updated_component['region'] = 'hidden';
          $entity_view_display->setComponent('book_navigation', $updated_component);
          $update = TRUE;
        }
      }
    }
    return $update;
  });
}

/**
 * Grant 'access book list' permission to roles with 'access content'.
 */
function book_post_update_book_list_permission(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'user_role', function (RoleInterface $role): bool {
    $update = FALSE;

    if ($role->hasPermission('access content') && !$role->hasPermission('access book list')) {
      $role->grantPermission('access book list');
      $update = TRUE;
    }

    return $update;
  });
}

/**
 * Converts book.settings.allowed_types + child_type to structured format.
 */
function book_post_update_1convert_allowed_types_to_structured(array &$sandbox = []): void {
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('book.settings');

  $old_allowed = $config->get('allowed_types');
  $child_type = $config->get('child_type');

  // Only run if we're still using the old flat format.
  if (is_array($old_allowed) && isset($child_type)) {
    $new_allowed = [];
    foreach ($old_allowed as $type_id) {
      if (!empty($type_id)) {
        $new_allowed[] = [
          'content_type' => $type_id,
          'child_type' => $child_type,
        ];
      }
    }

    $config
      ->set('allowed_types', $new_allowed)
      ->clear('child_type')
      ->save();
  }
}

/**
 * Pre-populate the show_top_item setting of the book_navigation blocks.
 */
function book_post_update_prepopulate_show_top_item_setting(&$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'block', function (Block $block) {
    if ($block->getPluginId() === 'book_navigation') {
      $block->getPlugin()->setConfigurationValue('show_top_item', FALSE);
      return TRUE;
    }
    return FALSE;
  });
}

/**
 * Add the default label truncation setting.
 */
function book_post_update_add_default_label_truncate_settings(): ?string {
  $config = \Drupal::configFactory()->getEditable('book.settings');
  if ($config->get('truncate_label') === NULL) {
    // Specify the default setting if not specified.
    $config
      ->set('truncate_label', TRUE)
      ->save(TRUE);
    return 'Updated the default truncate setting.';
  }
  return NULL;
}

/**
 * Remove 'add any content to books' permission.
 */
function book_post_update_remove_add_any_content_books_permission(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'user_role', function (RoleInterface $role): bool {
    $update = FALSE;
    $permission = 'add any content to books';

    if ($role->hasPermission('add any content to books')) {
      $role->revokePermission($permission);
      $update = TRUE;
    }

    return $update;
  });
}

/**
 * Set allowed child_type to parent if none previously configured.
 */
function book_post_update_6allowed_child_type_default(array &$sandbox = []): void {
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('book.settings');
  $updated = FALSE;

  $allowed_types = $config->get('allowed_types');
  foreach ($allowed_types as &$type) {
    if (empty($type['child_type'])) {
      $type['child_type'] = $type['content_type'];
      $updated = TRUE;
    }
  }

  if ($updated) {
    $config->set('allowed_types', $allowed_types)->save();
  }
}

/**
 * Set starting_level to the book_navigation blocks.
 */
function book_post_update_prepopulate_starting_level_setting(&$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'block', function (Block $block) {
    if ($block->getPluginId() === 'book_navigation') {
      $block->getPlugin()->setConfigurationValue('starting_level', 1);
      $block->getPlugin()->setConfigurationValue('expanded', FALSE);
      return TRUE;
    }
    return FALSE;
  });
}

/**
 * Set max_depth and book_select on book_navigation blocks.
 */
function book_post_update_prepopulate_max_depth_and_selected_book_settings(&$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'block', function (Block $block) {
    if ($block->getPluginId() === 'book_navigation') {
      $block->getPlugin()->setConfigurationValue('max_depth', 0);
      $block->getPlugin()->setConfigurationValue('book_select', 0);
      return TRUE;
    }
    return FALSE;
  });
}

/**
 * Rename the invalid select_book block setting to book_select.
 */
function book_post_update_fix_book_navigation_book_select_setting(&$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'block', function (Block $block) {
    if ($block->getPluginId() !== 'book_navigation') {
      return FALSE;
    }

    $settings = $block->get('settings') ?? [];
    if (!array_key_exists('select_book', $settings)) {
      return FALSE;
    }

    if (!array_key_exists('book_select', $settings)) {
      $settings['book_select'] = $settings['select_book'];
    }
    unset($settings['select_book']);
    $block->set('settings', $settings);

    return TRUE;
  });
}
