<?php

namespace Drupal\book;

/**
 * Helper functions for the book module.
 */
trait BookHelperTrait {

  /**
   * Extract content type machine names from nested allowed_types config array.
   *
   * @param array|null $allowed_types_config
   *   The raw config value for allowed_types. If null is passed probably means
   *   no allowed_types.
   *
   * @return string[]
   *   Array of content type machine names.
   */
  public function getBookContentTypes(?array $allowed_types_config = []): array {
    if (empty($allowed_types_config)) {
      return [];
    }
    $allowed_types = array_map(fn($item) => $item['content_type'] ?? NULL, $allowed_types_config);
    return array_filter($allowed_types);
  }

  /**
   * Get the child type for a given parent content type bundle.
   *
   * @param array $allowed_types_config
   *   The raw config value for allowed_types.
   * @param string $bundle
   *   The machine name of the parent content type.
   *
   * @return string|null
   *   The child type machine name, or NULL if not found.
   */
  public function getBookChildType(array $allowed_types_config, string $bundle): ?string {
    foreach ($allowed_types_config as $item) {
      if (!empty($item['content_type']) && $item['content_type'] === $bundle) {
        return $item['child_type'] ?? NULL;
      }
    }
    return NULL;
  }

}
