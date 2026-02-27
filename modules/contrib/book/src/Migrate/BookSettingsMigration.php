<?php

namespace Drupal\book\Migrate;

/**
 * Helper function for d6/d7 book_settings migrations.
 */
class BookSettingsMigration {

  /**
   * Convert old allowed_types from list of strings to list of mappings.
   *
   * @param array|string $allowed_types
   *   Array of strings or a single string.
   *
   * @return array
   *   Array of arrays with keys 'content_type' and 'child_type'.
   */
  public static function convertAllowedTypes(array|string $allowed_types): array {
    $result = [];

    if (is_array($allowed_types)) {
      foreach ($allowed_types as $content_type) {
        $result[] = [
          'content_type' => $content_type,
          'child_type' => $content_type,
        ];
      }
    }

    if (is_string($allowed_types)) {
      $result = [
        'content_type' => $allowed_types,
        'child_type' => $allowed_types,
      ];
    }
    return $result;
  }

}
