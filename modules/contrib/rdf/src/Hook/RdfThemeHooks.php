<?php

namespace Drupal\rdf\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;

/**
 * Theme registration and rdf_metadata preprocessing.
 */
class RdfThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'rdf_wrapper' => [
        'variables' => [
          'attributes' => [],
          'content' => NULL,
        ],
      ],
      'rdf_metadata' => [
        'variables' => [
          'metadata' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for rdf_metadata.
   */
  #[Hook('preprocess_rdf_metadata')]
  public function preprocessRdfMetadata(array &$variables): void {
    foreach ($variables['metadata'] as $key => $attributes) {
      $variables['metadata'][$key] = is_null($attributes)
        ? new Attribute()
        : new Attribute($attributes);
    }
  }

}
