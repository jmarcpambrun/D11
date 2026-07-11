<?php

declare(strict_types=1);

namespace Drupal\quiz_directions\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for quiz_directions.
 */
class QuizDirectionsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, $route_match): string {
    if ($route_name == 'help.page.quiz_directions') {
      return (string) $this->t('Provides directions which can be inserted alongside questions in a quiz.');
    }
    return '';
  }

}
