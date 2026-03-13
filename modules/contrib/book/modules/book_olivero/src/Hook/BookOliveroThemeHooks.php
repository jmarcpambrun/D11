<?php

namespace Drupal\book_olivero\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for book_olivero.
 */
class BookOliveroThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'book_navigation' => [
        'template' => 'book-navigation',
      ],
      'book_tree' => [
        'template' => 'book-tree',
      ],
    ];
  }

}
