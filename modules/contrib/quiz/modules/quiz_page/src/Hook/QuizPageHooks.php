<?php

declare(strict_types=1);

namespace Drupal\quiz_page\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for quiz_page.
 */
class QuizPageHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, $route_match): string {
    if ($route_name == 'help.page.quiz_page') {
      return (string) $this->t('Provides pages which can contain questions in a quiz.');
    }
    return '';
  }

}
