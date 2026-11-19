<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;

/**
 * A recipe, wrapped as an extension so it can be listed and scanned.
 *
 * Recipes are not extensions, but the project list, the scan results and the
 * reports all work with extension objects. The path name of a recipe is its
 * recipe.yml file, and its name is the name of its directory.
 */
final class RecipeExtension extends Extension {

  /**
   * The install status, which recipes don't have.
   *
   * Recipes are applied, not installed. Modules and themes get this property
   * from their extension lists, and code that lists projects reads it.
   *
   * @var int|null
   */
  public $status = NULL;

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return basename(dirname($this->pathname));
  }

}
