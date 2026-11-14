<?php

namespace Drupal\ai_integration_eca\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements library hooks for the AI/ECA integration module.
 */
class LibraryHooks {

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'modeler' && isset($libraries['react-ui'])) {
      $libraries['react-ui']['dependencies'][] = 'ai_integration_eca/modeler_plugin';
    }
  }

}
