<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;

/**
 * For analyzers of the code of modules, themes and profiles.
 */
trait ExtensionAnalyzerTrait {

  /**
   * {@inheritdoc}
   */
  public function applies(Extension $extension): bool {
    // Recipes are not extensions, and have no code of this kind.
    return $extension->getType() !== 'recipe';
  }

}
