<?php

declare(strict_types=1);

namespace Drupal\ai_search\Trait;

/**
 * Provides parsing for chunked search result entity IDs.
 */
trait DrupalEntityIdParserTrait {

  /**
   * Parses a chunked result's drupal_entity_id into its parts.
   *
   * @param string $drupalEntityId
   *   The raw drupal_entity_id extra-data value.
   *
   * @return array{0: string, 1: string, 2: string}
   *   Tuple of [entity type, entity ID, langcode], or empty strings if invalid.
   */
  protected function parseDrupalEntityId(string $drupalEntityId): array {
    $parts = explode(':', $drupalEntityId);
    if (count($parts) < 3 || !str_contains($parts[1], '/')) {
      return ['', '', ''];
    }
    [$entityType, $entityId] = explode('/', $parts[1], 2);
    return [$entityType, $entityId, $parts[2]];
  }

}
