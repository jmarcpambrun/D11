<?php

namespace Drupal\ai_search\Utility;

/**
 * Helper to flatten Search API's nested search keys structure.
 */
class SearchKeysHelper {

  /**
   * Flattens a search keys array into a flat list of keyword strings.
   *
   * Search API represents keyword groups (e.g. negated or nested terms) as
   * nested arrays with '#'-prefixed metadata keys such as '#negation' and
   * '#conjunction', per \Drupal\search_api\ParseMode\ParseModeInterface. This
   * recurses into those nested arrays so only actual keyword strings are
   * yielded.
   *
   * @param string|array $keys
   *   The search terms, as a plain string or a (possibly nested) keys array.
   * @param bool $include_negated
   *   Whether to include terms from negated groups. Defaults to TRUE. Pass
   *   FALSE to omit them entirely, which callers building semantic/vector
   *   search input should do: embedding models cannot reliably represent
   *   "not X", so negated terms are left for the traditional keyword layer
   *   to exclude instead.
   *
   * @return string[]
   *   A flat array of keyword strings.
   */
  public static function flatten(string|array $keys, bool $include_negated = TRUE): array {
    if (!is_array($keys)) {
      return [$keys];
    }
    if (!$include_negated && !empty($keys['#negation'])) {
      return [];
    }
    $flattened = [];
    foreach ($keys as $key => $value) {
      if (is_string($key) && $key[0] === '#') {
        continue;
      }
      if (is_array($value)) {
        $flattened = array_merge($flattened, self::flatten($value, $include_negated));
      }
      else {
        $flattened[] = $value;
      }
    }
    return $flattened;
  }

}
