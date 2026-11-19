<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;

/**
 * Describes the modules and themes that are deprecated or obsolete.
 *
 * Analyzers use this to report what depends on, installs or requires such an
 * extension. The lifecycle key of the extensions on the site is used, so core
 * and contributed extensions are handled the same way.
 */
final class DeprecatedExtensionDescriber {

  /**
   * Constructs a new deprecated extension describer.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   The theme extension list service.
   */
  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ThemeExtensionList $themeExtensionList,
  ) {}

  /**
   * Describes a module or theme if it is deprecated or obsolete.
   *
   * @param string $name
   *   The name of the module or theme.
   * @param string|null $project_dir
   *   (optional) The directory of the project that uses the extension. An
   *   extension in this directory is not described, because a project can
   *   use its own deprecated extensions.
   *
   * @return string|null
   *   The end of a message about the extension, like "the contact module,
   *   which is deprecated in Drupal core. ...", or NULL if it is not
   *   deprecated or obsolete, it is in the project, or it is not on the site.
   */
  public function describe(string $name, ?string $project_dir = NULL): ?string {
    foreach (['module' => $this->moduleExtensionList, 'theme' => $this->themeExtensionList] as $type => $list) {
      if (!$list->exists($name)) {
        continue;
      }
      $extension = $list->get($name);
      $lifecycle = $extension->info['lifecycle'] ?? '';
      if (!in_array($lifecycle, ['deprecated', 'obsolete'], TRUE)) {
        return NULL;
      }
      if ($project_dir !== NULL && str_starts_with(DRUPAL_ROOT . '/' . $extension->getPath() . '/', $project_dir . '/')) {
        return NULL;
      }
      $link = $extension->info['lifecycle_link'] ?? 'https://www.drupal.org/node/3215042';
      if (str_starts_with($extension->getPath(), 'core/')) {
        return "the $name $type, which is $lifecycle in Drupal core. Use the contributed project that replaces it, if there is one, and require it in composer.json. See $link.";
      }
      return "the $name $type, which is $lifecycle. See $link.";
    }
    return NULL;
  }

}
