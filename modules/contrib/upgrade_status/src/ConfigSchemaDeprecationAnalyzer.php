<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\views\ViewsData;

/**
 * Config schema deprecation analyzer.
 */
final class ConfigSchemaDeprecationAnalyzer implements AnalyzerInterface {

  /**
   * Whether config schema types are known to have no deprecations.
   *
   * @var array<string, bool>
   */
  private array $typesWithoutDeprecations = [];

  /**
   * Constructs a new config schema deprecation analyzer.
   *
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   * @param \Drupal\views\ViewsData|null $viewsData
   *   The views data, if the views module is installed.
   */
  public function __construct(
    private readonly TypedConfigManagerInterface $typedConfigManager,
    private readonly FileFinder $fileFinder,
    private readonly ?ViewsData $viewsData = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Extension $extension): bool {
    return TRUE;
  }

  /**
   * Analyzes usages of deprecated config schema elements in an extension.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   *
   * @throws \Exception
   */
  public function analyze(Extension $extension): array {
    $deprecations = [];

    $project_dir = DRUPAL_ROOT . '/' . $extension->getPath();
    $config_files = $this->getViewsConfigFiles($project_dir);
    foreach ($config_files as $config_file) {
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $config_file);
      $file_contents = file_get_contents($config_file);
      if (($line = $this->findKeyLine('default_argument_skip_url:', $file_contents)) !== 1) {
        $deprecations[] = new DeprecationMessage("Support from all Views contextual filter settings for the default_argument_skip_url setting is removed from drupal:11.0.0. No replacement is provided. See https://www.drupal.org/node/3382316.", $error_path, $line, 'ConfigSchemaDeprecationAnalyzer');
      }
    }

    foreach ($this->getSchemaFiles($project_dir) as $schema_file) {
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $schema_file);
      $file_contents = file_get_contents($schema_file);
      try {
        $schema = Yaml::decode($file_contents);
      }
      catch (InvalidDataTypeException) {
        // Ignore files with syntax errors, core cannot read them either.
        continue;
      }
      if (!is_array($schema)) {
        continue;
      }
      $lines = explode("\n", $file_contents);
      $cursor = 0;
      foreach ($this->findConstraintsWithInvalidOptions($schema, $lines, $cursor) as [$constraint, $line]) {
        // MessageCategorizer::FIXES_FOR_OLDER_VERSIONS has a pattern for this
        // message.
        $deprecations[] = new DeprecationMessage("The options of the $constraint constraint in this config schema are not a named array. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Name each option, for example Choice: {choices: [a, b]} instead of Choice: [a, b]. See https://www.drupal.org/node/3554746.", $error_path, $line, 'ConfigSchemaDeprecationAnalyzer');
      }
    }

    foreach ($this->getShippedConfigFiles($project_dir) as $config_file) {
      $file_contents = file_get_contents($config_file);
      try {
        $data = Yaml::decode($file_contents);
      }
      catch (InvalidDataTypeException) {
        continue;
      }
      $lines = explode("\n", $file_contents);
      $config_name = basename($config_file, '.yml');
      $data = is_array($data) ? $data : [];
      $updated = $this->findConfigUpdatedOnSave($config_name, $data);
      foreach ($updated as $path => $message) {
        $deprecations[] = new DeprecationMessage($message, str_replace(DRUPAL_ROOT . '/', '', $config_file), $this->findNestedKeyLine(explode('.', $path), $lines), 'ConfigSchemaDeprecationAnalyzer');
      }
      // Keys that are found above are not reported again for their schema.
      foreach (array_diff_key($this->findDeprecatedKeys($config_name, $data), $updated) as $path => $deprecation) {
        $deprecations[] = $this->getDeprecatedKeyMessage($config_name, (string) $path, $deprecation, $config_file, $this->findPathLine((string) $path, $lines, 0));
      }
    }

    foreach ($this->getRecipeFiles($project_dir) as $recipe_file) {
      $file_contents = file_get_contents($recipe_file);
      try {
        $recipe = Yaml::decode($file_contents);
      }
      catch (InvalidDataTypeException) {
        continue;
      }
      $actions = is_array($recipe) ? $recipe['config']['actions'] ?? [] : [];
      $lines = explode("\n", $file_contents);
      foreach (is_array($actions) ? $actions : [] as $config_name => $config_actions) {
        $config_name = (string) $config_name;
        $name_line = $this->findPathLine($config_name, $lines, 0, TRUE);
        foreach (['simpleConfigUpdate', 'simple_config_update'] as $action) {
          if (!is_array($config_actions[$action] ?? NULL)) {
            continue;
          }
          // The keys of simpleConfigUpdate can be paths, like 'css.preprocess'.
          $data = [];
          foreach ($config_actions[$action] as $key => $value) {
            NestedArray::setValue($data, explode('.', (string) $key), $value);
          }
          $action_line = $this->findPathLine($action, $lines, $name_line - 1);
          foreach ($this->findDeprecatedKeys($config_name, $data) as $path => $deprecation) {
            $line = $path === '' ? $name_line : $this->findPathLine($path, $lines, $action_line - 1, TRUE);
            $deprecations[] = $this->getDeprecatedKeyMessage($config_name, $path, $deprecation, $recipe_file, $line);
          }
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds config that Drupal 11 updates on save and Drupal 12 does not.
   *
   * These are the updates of shipped config in ViewsConfigUpdater,
   * BlockConfigUpdater, Block::preSave() and the presave hooks of the search
   * and system modules that trigger a deprecation in Drupal 11.
   *
   * @param string $config_name
   *   The name of the config, like views.view.content.
   * @param array $data
   *   The config data.
   *
   * @return array<string, string>
   *   The deprecation messages, keyed by the path of the key to change, like
   *   display.default.display_options.style.
   */
  private function findConfigUpdatedOnSave(string $config_name, array $data): array {
    $found = [];
    if (str_starts_with($config_name, 'views.view.')) {
      foreach (is_array($data['display'] ?? NULL) ? $data['display'] : [] as $display_id => $display) {
        $options = is_array($display['display_options'] ?? NULL) ? $display['display_options'] : [];
        $path = "display.$display_id.display_options";
        // @see \Drupal\views\ViewsConfigUpdater::processTableCssClassUpdate()
        $style = $options['style'] ?? NULL;
        if (is_array($style) && ($style['type'] ?? NULL) === 'table' && is_array($style['options'] ?? NULL) && !isset($style['options']['class'])) {
          $found["$path.style.options"] = "The table style of the $display_id display in $config_name has no class option. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Add class: '' to the style options. See https://www.drupal.org/node/3499943.";
        }
        // @see \Drupal\views\ViewsConfigUpdater::processEntityArgumentUpdate()
        foreach (is_array($options['arguments'] ?? NULL) ? $options['arguments'] : [] as $argument_id => $argument) {
          if (!is_array($argument) || ($argument['plugin_id'] ?? NULL) !== 'numeric' || $this->viewsData === NULL || !is_string($argument['table'] ?? NULL)) {
            continue;
          }
          $definition = $this->viewsData->get($argument['table'])[$argument['field'] ?? '']['argument'] ?? [];
          if (($definition['id'] ?? NULL) === 'entity_target_id') {
            $target_type = $definition['target_entity_type_id'] ?? '';
            $found["$path.arguments.$argument_id.plugin_id"] = "The $argument_id contextual filter of the $display_id display in $config_name uses the numeric plugin for an entity reference. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use plugin_id: entity_target_id and target_entity_type_id: $target_type. See https://www.drupal.org/node/3441945.";
          }
        }
      }
    }
    elseif (str_starts_with($config_name, 'block.block.')) {
      $plugin = is_string($data['plugin'] ?? NULL) ? $data['plugin'] : '';
      $settings = is_array($data['settings'] ?? NULL) ? $data['settings'] : [];
      // @see \Drupal\block\Entity\Block::preSave()
      if (array_key_exists('weight', $data) && !is_int($data['weight'])) {
        $found['weight'] = "The weight of $config_name is not an integer. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use an integer. See https://www.drupal.org/node/3462474.";
      }
      // @see \Drupal\block\BlockConfigUpdater::needsInfoStatusSettingsRemoved()
      if (str_starts_with($plugin, 'block_content')) {
        foreach (['status', 'info'] as $key) {
          if (isset($settings[$key])) {
            $found["settings.$key"] = "The $key setting of the content block in $config_name is not used. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Remove the setting. See https://www.drupal.org/node/3499836.";
          }
        }
      }
      // @see \Drupal\search\Hook\SearchHooks::blockPresave()
      if ($plugin === 'search_form_block' && ($settings['page_id'] ?? NULL) === '') {
        $found['settings.page_id'] = "The page_id setting of the search block in $config_name is an empty string. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use null for the default search page. See https://www.drupal.org/node/3463132.";
      }
    }
    // @see \Drupal\system\Hook\SystemHooks::systemEntityFormModePresave()
    elseif (str_starts_with($config_name, 'core.entity_form_mode.') && is_string($data['description'] ?? NULL) && trim($data['description']) === '') {
      $found['description'] = "The description of $config_name is an empty string. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use null. See https://www.drupal.org/node/3452144.";
    }
    return $found;
  }

  /**
   * Finds the keys of config data with a deprecated schema.
   *
   * @param string $config_name
   *   The name of the config, like system.performance.
   * @param array $data
   *   The config data.
   *
   * @return array<string, string>
   *   The deprecation messages, keyed by the path of the deprecated key, like
   *   css.gzip. The key of a deprecated config object is an empty string.
   */
  private function findDeprecatedKeys(string $config_name, array $data): array {
    // Wildcards in the names of config actions match several config objects.
    if (str_contains($config_name, '*') || !$this->typedConfigManager->hasConfigSchema($config_name)) {
      return [];
    }
    try {
      // Most config has a schema without deprecations. That is found from
      // the schema alone, which is much faster than creating the typed config.
      if ($this->isSchemaWithoutDeprecations($this->typedConfigManager->getDefinition($config_name))) {
        return [];
      }
      $typed_config = $this->typedConfigManager->createFromNameAndData($config_name, $data);
    }
    catch (\Exception) {
      return [];
    }
    $found = [];
    if (!empty($typed_config->getDataDefinition()['deprecated'])) {
      $found[''] = $typed_config->getDataDefinition()['deprecated'];
    }
    return $found + $this->findDeprecatedElements($typed_config);
  }

  /**
   * Finds the elements of typed config with a deprecated schema.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed config element.
   * @param string $path
   *   The path of the element.
   *
   * @return array<string, string>
   *   The deprecation messages, keyed by the path of the deprecated element.
   */
  private function findDeprecatedElements(TypedDataInterface $element, string $path = ''): array {
    $found = [];
    if ($element instanceof TraversableTypedDataInterface) {
      foreach ($element as $key => $child) {
        $child_path = $path === '' ? (string) $key : "$path.$key";
        $deprecation = $child->getDataDefinition()['deprecated'] ?? NULL;
        if (is_string($deprecation) && $deprecation !== '') {
          $found[$child_path] = $deprecation;
        }
        // Looking at the elements of a child creates an object for each of
        // them. That is skipped when the schema of the child has no
        // deprecations.
        $definition = $child->getDataDefinition();
        if ($child instanceof TraversableTypedDataInterface && $definition instanceof DataDefinition && $this->isSchemaWithoutDeprecations($definition->toArray())) {
          continue;
        }
        $found += $this->findDeprecatedElements($child, $child_path);
      }
    }
    return $found;
  }

  /**
   * Checks that a config schema definition has no deprecations.
   *
   * This looks at the definition, the types it extends and the definitions of
   * the elements in it. The config data is not used, so a dynamic type, like
   * block.settings.[%parent.plugin], is not followed.
   *
   * @param array $definition
   *   The definition of a config schema type or of an element.
   * @param bool $is_type
   *   TRUE for the definition of a type as the typed config manager lists it.
   *   Such a definition has no type if it does not extend another one. An
   *   element without a type has the undefined type.
   *
   * @return bool
   *   TRUE if config with this schema cannot have a deprecated element. FALSE
   *   if it can, or if that depends on the config data.
   */
  private function isSchemaWithoutDeprecations(array $definition, bool $is_type = FALSE): bool {
    if (!empty($definition['deprecated'])) {
      return FALSE;
    }
    $type = $definition['type'] ?? ($is_type ? NULL : 'undefined');
    if ($type !== NULL && !$this->isTypeWithoutDeprecations($type)) {
      return FALSE;
    }
    $elements = [];
    if (isset($definition['mapping'])) {
      if (!is_array($definition['mapping'])) {
        return FALSE;
      }
      $elements = array_values($definition['mapping']);
    }
    if (isset($definition['sequence'])) {
      if (!is_array($definition['sequence'])) {
        return FALSE;
      }
      $elements[] = $definition['sequence'];
      // Before Drupal 11, the definition of the items can be the first item
      // of a list.
      if (isset($definition['sequence'][0])) {
        $elements[] = $definition['sequence'][0];
      }
    }
    foreach ($elements as $element) {
      if (!is_array($element) || !$this->isSchemaWithoutDeprecations($element)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Checks that a config schema type has no deprecations.
   *
   * @param mixed $type
   *   The name of the type.
   *
   * @return bool
   *   TRUE if config of this type cannot have a deprecated element. FALSE if
   *   it can, or if that depends on the config data.
   */
  private function isTypeWithoutDeprecations(mixed $type): bool {
    // A type with a part in brackets depends on the config data.
    if (!is_string($type) || str_contains($type, '[') || str_contains($type, ']')) {
      return FALSE;
    }
    if (!isset($this->typesWithoutDeprecations[$type])) {
      $definitions = $this->typedConfigManager->getDefinitions();
      // A type that is part of its own definition is not followed again. A
      // name without a definition of its own stands for another type, which
      // the typed config manager finds.
      $this->typesWithoutDeprecations[$type] = FALSE;
      if (isset($definitions[$type]) && is_array($definitions[$type])) {
        $this->typesWithoutDeprecations[$type] = $this->isSchemaWithoutDeprecations($definitions[$type], TRUE);
      }
    }
    return $this->typesWithoutDeprecations[$type];
  }

  /**
   * Creates the message for a deprecated config key.
   *
   * @param string $config_name
   *   The name of the config.
   * @param string $path
   *   The path of the key, or an empty string for the config object.
   * @param string $deprecation
   *   The deprecation message of the schema.
   * @param string $file
   *   The file that has the config.
   * @param int $line
   *   The line in the file.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage
   *   The message.
   */
  private function getDeprecatedKeyMessage(string $config_name, string $path, string $deprecation, string $file, int $line): DeprecationMessage {
    $deprecation = trim($deprecation);
    // Keep the version information in the format that categorizes messages.
    if (preg_match('/\bis deprecated ((?:in|as of) drupal:.*)$/is', $deprecation, $matches)) {
      $deprecation = 'Deprecated ' . $matches[1];
    }
    $subject = $path === '' ? "The $config_name config is used." : "The $path key of the $config_name config is used.";
    return new DeprecationMessage("$subject $deprecation", str_replace(DRUPAL_ROOT . '/', '', $file), $line, 'ConfigSchemaDeprecationAnalyzer');
  }

  /**
   * Finds the line of a key in a YAML file.
   *
   * @param string $path
   *   The path of the key, like css.gzip. Each part is looked for after the
   *   line of the part before it. Numeric parts are skipped, because they are
   *   items of a list.
   * @param list<string> $lines
   *   The lines of the file.
   * @param int $cursor
   *   The index of the line to start from.
   * @param bool $allow_dotted
   *   Whether the whole path can be a key, like in simpleConfigUpdate.
   *
   * @return int
   *   The line number if found, 1 otherwise.
   */
  private function findPathLine(string $path, array $lines, int $cursor, bool $allow_dotted = FALSE): int {
    if ($allow_dotted) {
      foreach (array_slice($lines, $cursor, NULL, TRUE) as $index => $line) {
        if (preg_match('/^\s*[\'"]?' . preg_quote($path, '/') . '[\'"]?\s*:/', $line)) {
          return $index + 1;
        }
      }
    }
    $found = NULL;
    foreach (explode('.', $path) as $part) {
      if (is_numeric($part)) {
        continue;
      }
      foreach (array_slice($lines, $cursor, NULL, TRUE) as $index => $line) {
        if (preg_match('/^\s*[\'"]?' . preg_quote($part, '/') . '[\'"]?\s*:/', $line)) {
          $found = $index;
          $cursor = $index + 1;
          continue 2;
        }
      }
      break;
    }
    return $found === NULL ? 1 : $found + 1;
  }

  /**
   * Finds the line of a nested key in a YAML file.
   *
   * Unlike findPathLine(), each key is only looked for among the direct
   * children of the key before it, based on the indentation.
   *
   * @param list<string> $keys
   *   The keys from the top level down, like [display, default].
   * @param list<string> $lines
   *   The lines of the file.
   *
   * @return int
   *   The line number of the deepest key found, 1 if none is found.
   */
  private function findNestedKeyLine(array $keys, array $lines): int {
    $found = NULL;
    $cursor = 0;
    $parent_indent = -1;
    foreach ($keys as $key) {
      $pattern = '/^\s*[\'"]?' . preg_quote($key, '/') . '[\'"]?\s*:(\s|$)/';
      $child_indent = NULL;
      $match = NULL;
      for ($index = $cursor; isset($lines[$index]); $index++) {
        $trimmed = ltrim($lines[$index]);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
          continue;
        }
        $indent = strlen($lines[$index]) - strlen($trimmed);
        if ($indent <= $parent_indent) {
          break;
        }
        $child_indent ??= $indent;
        if ($indent === $child_indent && preg_match($pattern, $lines[$index])) {
          $match = $index;
          break;
        }
      }
      if ($match === NULL) {
        break;
      }
      $found = $match;
      $cursor = $match + 1;
      $parent_indent = $child_indent;
    }
    return $found === NULL ? 1 : $found + 1;
  }

  /**
   * Finds the config files that a project ships.
   *
   * These are the files in config/install and config/optional directories,
   * and in the config directories of recipes. Test fixtures are skipped.
   *
   * @param string $path
   *   The directory to look in.
   *
   * @return list<string>
   *   A list of paths to the config files.
   */
  private function getShippedConfigFiles(string $path): array {
    $files = [];
    if (preg_match('~/config/(install|optional)$~', $path) || (basename($path) === 'config' && is_file(dirname($path) . '/recipe.yml'))) {
      $files = $this->fileFinder->getFiles($path, '.yml');
    }
    $ignore_directories = Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']);
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE) && !preg_match('~/tests/fixtures$~', $dir)) {
        $files = array_merge($files, $this->getShippedConfigFiles($dir));
      }
    }
    return $files;
  }

  /**
   * Finds the recipe.yml files of a project.
   *
   * @param string $path
   *   The directory to look in.
   *
   * @return list<string>
   *   A list of paths to the recipe files.
   */
  private function getRecipeFiles(string $path): array {
    $files = is_file($path . '/recipe.yml') ? [$path . '/recipe.yml'] : [];
    $ignore_directories = array_merge(
      ['config', 'content', 'vendor'],
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE) && !preg_match('~/tests/fixtures$~', $dir)) {
        $files = array_merge($files, $this->getRecipeFiles($dir));
      }
    }
    return $files;
  }

  /**
   * Finds constraints with options that core will not accept in Drupal 12.
   *
   * Drupal 12's ConstraintManager::create() only accepts a named array or NULL
   * as options. This walks the schema in the order of the file, so the line of
   * each constraint can be found by moving forward in the file.
   *
   * @param array $schema
   *   A part of the decoded schema.
   * @param list<string> $lines
   *   The lines of the schema file.
   * @param int $cursor
   *   The index of the line to continue looking from.
   * @param string|null $parent
   *   The key of this part of the schema, NULL for the top level.
   *
   * @return list<array{string, int}>
   *   The name and the line number of each constraint with invalid options.
   */
  private function findConstraintsWithInvalidOptions(array $schema, array $lines, int &$cursor, ?string $parent = NULL): array {
    $found = [];
    foreach ($schema as $key => $value) {
      if (!is_array($value)) {
        continue;
      }
      // Top level keys are type names. Keys in a mapping are config keys, so a
      // config key can also be called "constraints".
      if ($key !== 'constraints' || $parent === NULL || $parent === 'mapping') {
        $found = array_merge($found, $this->findConstraintsWithInvalidOptions($value, $lines, $cursor, (string) $key));
        continue;
      }

      $cursor = $this->findSchemaKeyLine('constraints', $lines, $cursor);
      foreach ($value as $constraint => $options) {
        if (!is_string($constraint)) {
          continue;
        }
        $cursor = $this->findSchemaKeyLine($constraint, $lines, $cursor);
        if (is_array($options)) {
          if ($options !== [] && array_is_list($options)) {
            $found[] = [$constraint, $cursor + 1];
          }
          $found = array_merge($found, $this->findConstraintsWithInvalidOptions($options, $lines, $cursor, $constraint));
        }
        elseif ($options !== NULL) {
          $found[] = [$constraint, $cursor + 1];
        }
      }
      $cursor++;
    }
    return $found;
  }

  /**
   * Finds the next line with a key in a schema file.
   *
   * @param string $key
   *   The key to find.
   * @param list<string> $lines
   *   The lines of the schema file.
   * @param int $cursor
   *   The index of the line to start looking from.
   *
   * @return int
   *   The index of the line with the key, or $cursor if it is not found.
   */
  private function findSchemaKeyLine(string $key, array $lines, int $cursor): int {
    $pattern = '/(^|[\s{,])[\'"]?' . preg_quote($key, '/') . '[\'"]?\s*:/';
    for ($index = $cursor; isset($lines[$index]); $index++) {
      if (!str_starts_with(ltrim($lines[$index]), '#') && preg_match($pattern, $lines[$index])) {
        return $index;
      }
    }
    return $cursor;
  }

  /**
   * Finds all config schema files under a path.
   *
   * @param string $path
   *   Base path to find all config schema files in.
   *
   * @return list<string>
   *   A list of paths to config schema files found under the base path.
   */
  private function getSchemaFiles(string $path): array {
    $files = [];
    if (str_ends_with($path, '/config/schema')) {
      $files = $this->fileFinder->getFiles($path, '.schema.yml');
    }
    $ignore_directories = Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']);
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE)) {
        $files = array_merge($files, $this->getSchemaFiles($dir));
      }
    }
    return $files;
  }

  /**
   * Finds all views config files for extensions under a path.
   *
   * @param string $path
   *   Base path to find all views config files in.
   *
   * @return array
   *   A list of paths to views config files found under the base path.
   */
  private function getViewsConfigFiles(string $path) {
    $files = [];
    foreach ($this->fileFinder->getFiles($path, '.yml') as $file) {
      if (preg_match('/^views\.view\..*\.yml$/', basename($file))) {
        $files[] = $file;
      }
    }
    // Directories like node_modules can have many files, and no
    // configuration of the project.
    $ignore_directories = Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']);
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE)) {
        $files = array_merge($files, $this->getViewsConfigFiles($dir));
      }
    }
    return $files;
  }

  /**
   * Finds the line that contains the substring.
   *
   * @param string $substring
   *   The string to find.
   * @param string $file_contents
   *   String contents of a file.
   *
   * @return int
   *   Line number if found, 1 otherwise.
   */
  private function findKeyLine($substring, $file_contents) {
    $lines = explode("\n", $file_contents);
    foreach ($lines as $num => $line) {
      if (strpos($line, $substring) !== FALSE) {
        return $num + 1;
      }
    }
    return 1;
  }

}
