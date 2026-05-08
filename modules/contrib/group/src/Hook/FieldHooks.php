<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Field hook implementations for Group.
 */
final class FieldHooks {

  /**
   * Implements hook_field_widget_info_alter().
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    // Anything that supports entity reference fields should also work for ours.
    foreach ($info as $key => $widget_info) {
      if (in_array('entity_reference', $widget_info['field_types'], TRUE)) {
        $info[$key]['field_types'][] = 'group_relationship_target';
      }
    }
  }

  /**
   * Implements hook_field_formatter_info_alter().
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    // Anything that supports entity reference fields should also work for ours.
    foreach ($info as $key => $formatter_info) {
      if (in_array('entity_reference', $formatter_info['field_types'], TRUE)) {
        $info[$key]['field_types'][] = 'group_relationship_target';
      }
    }
  }

}
