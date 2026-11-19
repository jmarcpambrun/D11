<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;

/**
 * An analyzer that finds deprecated things in a project.
 *
 * Services with the upgrade_status.analyzer tag implement this. The main
 * analyzer runs them after PHPStan, from the highest priority to the lowest,
 * and stores their messages with the results of the project.
 */
interface AnalyzerInterface {

  /**
   * Checks whether this analyzer applies to a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The project. Its type is module, theme, profile or recipe.
   *
   * @return bool
   *   TRUE if the analyzer looks at projects of this kind.
   */
  public function applies(Extension $extension): bool;

  /**
   * Analyzes a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The project to analyze.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array;

}
