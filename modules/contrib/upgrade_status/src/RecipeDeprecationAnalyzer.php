<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;

/**
 * The recipe deprecation analyzer.
 *
 * Core only reports these deprecations when a recipe is applied, so PHPStan
 * can't find them. Drupal 12 removes them, and the recipe can't be applied:
 * - Deprecated and obsolete modules and themes, like the Claro theme.
 * - Core recipes that core no longer uses, like core/recipes/article_tags.
 * - The old IDs of the simpleConfigUpdate and createIfNotExists config
 *   actions.
 * - The simpleConfigUpdate config action on config entities.
 */
final class RecipeDeprecationAnalyzer implements AnalyzerInterface {

  /**
   * Core recipes that are removed from Drupal 12.
   *
   * Core's own recipes stopped using them in Drupal 11.4.
   */
  private const REMOVED_CORE_RECIPES = [
    'article_comment',
    'article_content_type',
    'article_tags',
    'basic_shortcuts',
    'content_search',
    'page_content_type',
  ];

  /**
   * The old IDs of config actions and their replacements.
   */
  private const DEPRECATED_ACTIONS = [
    'simple_config_update' => 'simpleConfigUpdate',
    'entity_create:ensure_exists' => 'entity_create:createIfNotExists',
  ];

  /**
   * Constructs a new recipe deprecation analyzer.
   *
   * @param \Drupal\upgrade_status\DeprecatedExtensionDescriber $deprecatedExtensionDescriber
   *   The deprecated extension describer.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly DeprecatedExtensionDescriber $deprecatedExtensionDescriber,
    private readonly ConfigManagerInterface $configManager,
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Extension $extension): bool {
    return TRUE;
  }

  /**
   * Analyzes the recipes of a project.
   *
   * This is the recipe itself for a recipe project, and the recipes a module
   * or theme ships for other projects.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The project to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array {
    $deprecations = [];
    foreach ($this->getRecipeFiles(DRUPAL_ROOT . '/' . $extension->getPath()) as $file) {
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $file);
      $code = file_get_contents($file);
      try {
        $recipe = Yaml::decode($code);
      }
      catch (InvalidDataTypeException $e) {
        $deprecations[] = new DeprecationMessage('Parse error. ' . $e->getMessage(), $error_path, 1, 'RecipeDeprecationAnalyzer');
        continue;
      }
      if (!is_array($recipe)) {
        continue;
      }
      foreach ($this->analyzeRecipe($recipe, explode("\n", $code)) as [$message, $line]) {
        $deprecations[] = new DeprecationMessage($message, $error_path, $line, 'RecipeDeprecationAnalyzer');
      }
      foreach (glob(dirname($file) . '/config/*.yml') ?: [] as $config_file) {
        foreach ($this->analyzeConfigDependencies($config_file) as [$message, $line]) {
          $deprecations[] = new DeprecationMessage($message, str_replace(DRUPAL_ROOT . '/', '', $config_file), $line, 'RecipeDeprecationAnalyzer');
        }
      }
    }
    return $deprecations;
  }

  /**
   * Analyzes the recipe.yml file of a recipe.
   *
   * @param array $recipe
   *   The decoded recipe.yml file.
   * @param string[] $lines
   *   The lines of the recipe.yml file.
   *
   * @return array
   *   A list of messages and line numbers.
   */
  private function analyzeRecipe(array $recipe, array $lines): array {
    $results = [];

    $recipes_line = $this->findLine($lines, '/^recipes\s*:/');
    foreach ($this->getList($recipe['recipes'] ?? []) as $name) {
      if (preg_match('@^core/recipes/(' . implode('|', self::REMOVED_CORE_RECIPES) . ')/?$@', $name)) {
        // MessageCategorizer::FIXES_FOR_OLDER_VERSIONS has a pattern for this
        // message.
        $results[] = [
          "The $name core recipe is applied. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Core no longer uses it. Add the configuration you need to this recipe instead. See https://www.drupal.org/project/drupal/issues/3608069.",
          $this->findLine($lines, $this->getListItemPattern($name), $recipes_line),
        ];
      }
    }

    $install_line = $this->findLine($lines, '/^install\s*:/');
    foreach ($this->getList($recipe['install'] ?? []) as $name) {
      if ($description = $this->deprecatedExtensionDescriber->describe($name)) {
        $results[] = [
          "The recipe installs $description",
          $this->findLine($lines, $this->getListItemPattern($name), $install_line),
        ];
      }
    }

    $config_line = $this->findLine($lines, '/^config\s*:/');
    $import = $recipe['config']['import'] ?? [];
    if (is_array($import)) {
      $import_line = $this->findLine($lines, '/^\s+import\s*:/', $config_line);
      foreach (array_keys($import) as $name) {
        if ($description = $this->deprecatedExtensionDescriber->describe((string) $name)) {
          $results[] = [
            "The recipe imports configuration from $description",
            $this->findLine($lines, $this->getKeyPattern((string) $name), $import_line),
          ];
        }
      }
    }

    $actions = $recipe['config']['actions'] ?? [];
    if (is_array($actions)) {
      $actions_line = $this->findLine($lines, '/^\s+actions\s*:/', $config_line);
      foreach ($actions as $config_name => $config_actions) {
        if (!is_array($config_actions)) {
          continue;
        }
        $config_name = (string) $config_name;
        $name_line = $this->findLine($lines, $this->getKeyPattern($config_name), $actions_line);
        foreach (array_keys($config_actions) as $action) {
          $action = (string) $action;
          if (isset(self::DEPRECATED_ACTIONS[$action])) {
            $results[] = [
              "The $action config action is used. Deprecated in drupal:10.3.1 and is removed from drupal:12.0.0. Use " . self::DEPRECATED_ACTIONS[$action] . " instead. See https://www.drupal.org/node/3458273.",
              $this->findLine($lines, $this->getKeyPattern($action), $name_line),
            ];
          }
          if (in_array($action, ['simpleConfigUpdate', 'simple_config_update'], TRUE) && $this->configManager->getEntityTypeIdByName($config_name)) {
            $results[] = [
              "The $action config action is used on the $config_name config entity. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Drupal 12 throws an exception. Use the setProperties config action instead. See https://www.drupal.org/node/3515543.",
              $this->findLine($lines, $this->getKeyPattern($action), $name_line),
            ];
          }
        }
      }
    }

    return $results;
  }

  /**
   * Analyzes the dependencies of a config file shipped with a recipe.
   *
   * @param string $file
   *   The path of the config file.
   *
   * @return array
   *   A list of messages and line numbers.
   */
  private function analyzeConfigDependencies(string $file): array {
    $code = file_get_contents($file);
    try {
      $config = Yaml::decode($code);
    }
    catch (InvalidDataTypeException $e) {
      return [];
    }
    $dependencies = is_array($config) && is_array($config['dependencies'] ?? NULL) ? $config['dependencies'] : [];
    $names = [];
    foreach ([$dependencies, $dependencies['enforced'] ?? []] as $list) {
      foreach (['module', 'theme'] as $type) {
        $names = array_merge($names, $this->getList(is_array($list) ? $list[$type] ?? [] : []));
      }
    }

    $results = [];
    $lines = explode("\n", $code);
    $dependencies_line = $this->findLine($lines, '/^dependencies\s*:/');
    foreach (array_unique($names) as $name) {
      if ($description = $this->deprecatedExtensionDescriber->describe($name)) {
        $results[] = [
          "The configuration depends on $description",
          $this->findLine($lines, $this->getListItemPattern($name), $dependencies_line),
        ];
      }
    }
    return $results;
  }

  /**
   * Returns the strings of a list from a recipe.
   *
   * @param mixed $list
   *   The list as decoded.
   *
   * @return string[]
   *   The strings in the list.
   */
  private function getList(mixed $list): array {
    return is_array($list) ? array_filter($list, 'is_string') : [];
  }

  /**
   * Returns a pattern that finds a list item.
   *
   * @param string $value
   *   The value of the list item.
   *
   * @return string
   *   The pattern.
   */
  private function getListItemPattern(string $value): string {
    return '/^\s*-\s*[\'"]?' . preg_quote($value, '/') . '[\'"]?\s*(#.*)?$/';
  }

  /**
   * Returns a pattern that finds a mapping key.
   *
   * @param string $key
   *   The key.
   *
   * @return string
   *   The pattern.
   */
  private function getKeyPattern(string $key): string {
    return '/^\s*[\'"]?' . preg_quote($key, '/') . '[\'"]?\s*:/';
  }

  /**
   * Finds the first line that matches a pattern.
   *
   * @param string[] $lines
   *   The lines of the file.
   * @param string $pattern
   *   The pattern to find.
   * @param int $start
   *   The line number to start from.
   *
   * @return int
   *   The number of the line that matches, or the start line if none does.
   */
  private function findLine(array $lines, string $pattern, int $start = 1): int {
    for ($index = $start - 1; $index < count($lines); $index++) {
      if (preg_match($pattern, $lines[$index])) {
        return $index + 1;
      }
    }
    return $start;
  }

  /**
   * Finds the recipe.yml files in a directory.
   *
   * Test classes, test fixtures and code of other projects are skipped.
   *
   * @param string $path
   *   The directory to search.
   *
   * @return string[]
   *   The paths of the recipe.yml files.
   */
  private function getRecipeFiles(string $path): array {
    $files = file_exists($path . '/recipe.yml') ? [$path . '/recipe.yml'] : [];
    $ignore_directories = array_merge(
      ['config', 'content', 'dist', 'libraries', 'vendor'],
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    foreach ($this->fileFinder->getDirectories($path, TRUE) as $directory) {
      if (!in_array(basename($directory), $ignore_directories, TRUE) && !preg_match('~/tests/(src|fixtures)$~', $directory)) {
        $files = array_merge($files, $this->getRecipeFiles($directory));
      }
    }
    return $files;
  }

}
