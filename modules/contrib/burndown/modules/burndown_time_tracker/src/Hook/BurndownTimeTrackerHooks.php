<?php

declare(strict_types=1);

namespace Drupal\burndown_time_tracker\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hook implementations for the Burndown Time Tracker module.
 */
final class BurndownTimeTrackerHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.burndown_time_tracker') {
      return NULL;
    }

    $text = file_get_contents(__DIR__ . '/../../README.md');
    return '<pre>' . Html::escape($text) . '</pre>';
  }

}
