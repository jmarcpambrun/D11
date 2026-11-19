<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Site\Settings;

// cspell:ignore vararg
/**
 * Finds hooks in include files, and implementations of deprecated hooks.
 *
 * Drupal 11 loads $module.$group.inc files through hook_hook_info() groups, and
 * finds hooks in any other .inc file. Drupal 12 only looks for procedural hooks
 * in .module, .profile and .install files, so hooks in .inc files stop running
 * without an error.
 *
 * Hooks are documented in *.api.php files, and PHPStan does not check those for
 * deprecations. So this also reads the @deprecated text of each hook there.
 *
 * Drupal 12 also no longer finds template_preprocess_HOOK() functions by name,
 * and no longer loads the files named in hook_theme(). Templates then render
 * without their variables, and core only reports this while the code runs.
 *
 * hook_module_implements_alter() has no @deprecated text, because it stays in
 * use for older Drupal versions. Core only triggers its deprecation when the
 * container is built, so this also looks for that hook.
 */
final class HookDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The hook_hook_info() groups that core defines.
   *
   * These are defined in system_hook_info() and views_hook_info(). Views may
   * not be installed when scanning, so they are listed here.
   */
  private const CORE_GROUPS = ['tokens', 'views', 'views_execution'];

  /**
   * Hooks that core does not look for in the hook system.
   *
   * Core calls these directly, so they work in a file that the module loads.
   *
   * @see \Drupal\Core\Hook\HookCollectorPass::collectModuleHookImplementations()
   */
  private const NOT_DISCOVERED_HOOKS = [
    'install',
    'install_tasks',
    'install_tasks_alter',
    'schema',
    'uninstall',
    'update_last_removed',
    'update_dependencies',
  ];

  /**
   * Documented hook names without the hook_ prefix, keyed by hook name.
   *
   * @var array<string, true>|null
   */
  private ?array $hooks = NULL;

  /**
   * Regular expressions for documented hooks with a variable part.
   *
   * @var list<string>
   */
  private array $hookPatterns = [];

  /**
   * The @deprecated text of deprecated hooks, keyed by hook name.
   *
   * @var array<string, string>
   */
  private array $deprecatedHooks = [];

  /**
   * The @deprecated text of deprecated hooks with a variable part.
   *
   * @var array<string, string>
   */
  private array $deprecatedHookPatterns = [];

  /**
   * Constructs a new hook deprecation analyzer.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileFinder $fileFinder,
  ) {
  }

  /**
   * Analyzes an extension for hooks in include files and deprecated hooks.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array {
    $project_dir = DRUPAL_ROOT . '/' . $extension->getPath();
    $modules = array_keys($this->moduleExtensionList->getList());
    $modules[] = $extension->getName();
    $test_modules = $this->getTestModules($project_dir);
    $modules = array_merge($modules, array_keys($test_modules));
    usort($modules, static fn (string $a, string $b): int => strlen($b) - strlen($a));

    $deprecations = array_merge(
      $this->analyzeDeprecatedHooks($project_dir, $modules),
      $this->analyzeThemeHooks($project_dir, $modules),
      $this->analyzeExtensionFiles($project_dir),
    );
    // Themes load their include files themselves, and core finds the functions
    // after that. Only modules and profiles are affected.
    if ($extension->getType() !== 'theme') {
      $deprecations = array_merge($deprecations, $this->analyzeIncludeFiles($project_dir, $modules));
      $deprecations = array_merge($deprecations, $this->analyzeModuleImplementsAlter($project_dir, $modules));
    }
    foreach ($test_modules as $name => $test_module_dir) {
      $deprecations = array_merge(
        $deprecations,
        $this->analyzeDeprecatedHooks($test_module_dir, $modules),
        $this->analyzeThemeHooks($test_module_dir, $modules),
      );
      if (!$this->isTheme("$test_module_dir/$name.info.yml")) {
        $deprecations = array_merge(
          $deprecations,
          $this->analyzeIncludeFiles($test_module_dir, $modules),
          $this->analyzeModuleImplementsAlter($test_module_dir, $modules),
        );
      }
    }

    // A test module can have submodules, which are found as test modules too.
    $unique = [];
    foreach ($deprecations as $deprecation) {
      $unique[$deprecation->getFile() . ':' . $deprecation->getLine() . ':' . $deprecation->getMessage()] = $deprecation;
    }
    return array_values($unique);
  }

  /**
   * Finds hooks in include files.
   *
   * @param string $project_dir
   *   The project directory.
   * @param list<string> $modules
   *   The module names to look for at the start of function names, longest name
   *   first.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeIncludeFiles(string $project_dir, array $modules): array {
    $groups = array_merge(self::CORE_GROUPS, $this->getProjectHookGroups($project_dir));

    $deprecations = [];
    foreach ($this->getFiles($project_dir, '.inc') as $file) {
      // Drush command files are not part of the hook system.
      if (str_ends_with($file, '.drush.inc')) {
        continue;
      }
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $file);
      // Functions marked with #[LegacyHook] have an object-oriented twin, which
      // is the one that runs on Drupal 11.1 and later.
      $functions = array_filter($this->getFunctions(file_get_contents($file)), static fn (array $function): bool => !$function['legacy']);
      if (empty($functions)) {
        continue;
      }

      // Core only loads a $module.$group.inc file for the group's hooks, so
      // the whole file is affected, not just the hooks in it.
      $parts = explode('.', basename($file));
      if (count($parts) === 3 && in_array($parts[0], $modules, TRUE) && in_array($parts[1], $groups, TRUE)) {
        $deprecations[] = new DeprecationMessage("Core loads this file through the $parts[1] group of hook_hook_info(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Move the functions in this file to the .module file or to a class with Hook attributes. See https://www.drupal.org/node/3489765.", $error_path, reset($functions)['line'], 'HookDeprecationAnalyzer');
        continue;
      }

      foreach ($functions as $function) {
        $hook = $this->findHook($function['name'], $modules);
        if ($hook !== NULL) {
          $deprecations[] = new DeprecationMessage("{$function['name']}() in an include file may be an implementation of hook_$hook(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Drupal 12 only loads procedural hooks from .module, .profile and .install files, so this hook is ignored. Move the function to the .module file or to a class with Hook attributes. See https://www.drupal.org/node/3489765.", $error_path, $function['line'], 'HookDeprecationAnalyzer');
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds hook_module_implements_alter() without a legacy attribute.
   *
   * @param string $project_dir
   *   The project directory.
   * @param list<string> $modules
   *   The module names to look for at the start of function names, longest name
   *   first.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeModuleImplementsAlter(string $project_dir, array $modules): array {
    $deprecations = [];
    foreach (['.module', '.install', '.profile', '.inc'] as $ending) {
      foreach ($this->getFiles($project_dir, $ending) as $file) {
        if (str_ends_with($file, '.drush.inc')) {
          continue;
        }
        foreach ($this->getFunctions(file_get_contents($file)) as $function) {
          if (!$function['legacy'] && str_ends_with($function['name'], '_module_implements_alter') && in_array(substr($function['name'], 0, -strlen('_module_implements_alter')), $modules, TRUE)) {
            $deprecations[] = new DeprecationMessage("{$function['name']}() implements hook_module_implements_alter() without the LegacyModuleImplementsAlter attribute. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Drupal 12 ignores this function, so hooks can run in another order. Use the order argument of the Hook attribute, and add #[LegacyModuleImplementsAlter] if the function is still needed for older Drupal versions. See https://www.drupal.org/node/3496788.", str_replace(DRUPAL_ROOT . '/', '', $file), $function['line'], 'HookDeprecationAnalyzer');
          }
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds implementations of deprecated hooks.
   *
   * @param string $project_dir
   *   The project directory.
   * @param list<string> $modules
   *   The module names to look for at the start of function names, longest name
   *   first.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeDeprecatedHooks(string $project_dir, array $modules): array {
    $this->loadHooks();
    if (empty($this->deprecatedHooks) && empty($this->deprecatedHookPatterns)) {
      return [];
    }

    $deprecations = [];
    foreach (['.module', '.install', '.profile', '.theme', '.inc'] as $ending) {
      foreach ($this->getFiles($project_dir, $ending) as $file) {
        if (str_ends_with($file, '.drush.inc')) {
          continue;
        }
        foreach ($this->getFunctions(file_get_contents($file)) as $function) {
          $deprecated = $function['legacy'] ? NULL : $this->findDeprecatedImplementation($function['name'], $modules);
          if ($deprecated !== NULL) {
            [$hook, $description] = $deprecated;
            $deprecations[] = new DeprecationMessage("{$function['name']}() implements hook_$hook(). Deprecated $description", str_replace(DRUPAL_ROOT . '/', '', $file), $function['line'], 'HookDeprecationAnalyzer');
          }
        }
      }
    }

    // Hook classes can be anywhere in a module's namespace, so this also looks
    // in src directories.
    foreach ($this->getFiles($project_dir, '.php', ['tests', 'js', 'css']) as $file) {
      $code = file_get_contents($file);
      if (str_ends_with($file, '.api.php') || !str_contains($code, 'Hook')) {
        continue;
      }
      foreach ($this->getHookAttributes($code) as $attribute) {
        $description = $this->getHookDeprecation($attribute['hook']);
        if ($description !== NULL) {
          $deprecations[] = new DeprecationMessage("The Hook attribute implements hook_{$attribute['hook']}(). Deprecated $description", str_replace(DRUPAL_ROOT . '/', '', $file), $attribute['line'], 'HookDeprecationAnalyzer');
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds theme hooks that rely on what Drupal 12 removed.
   *
   * @param string $project_dir
   *   The project directory.
   * @param list<string> $modules
   *   The module names to look for at the start of function names, longest name
   *   first.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeThemeHooks(string $project_dir, array $modules): array {
    $deprecations = [];
    $registered = [];
    $preprocess_functions = [];
    $files = $this->getFiles($project_dir, '.php', ['tests', 'js', 'css']);
    foreach (['.module', '.install', '.profile', '.theme', '.inc'] as $ending) {
      $files = array_merge($files, $this->getFiles($project_dir, $ending));
    }
    foreach ($files as $file) {
      $code = file_get_contents($file);
      if (str_ends_with($file, '.api.php') || (!str_contains($code, 'theme') && !str_contains($code, 'initial preprocess') && !str_contains($code, 'template_preprocess_'))) {
        continue;
      }
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $file);
      $found = $this->findThemeHookData($code, $modules);
      $registered += $found['registered'];
      foreach ($found['file_keys'] as [$hook, $key, $line]) {
        $description = $key === 'file' ? 'a file' : 'includes';
        $deprecations[] = new DeprecationMessage("The $hook theme hook provides $description with the '$key' key. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use initial preprocess for template_preprocess instead, and load the file from the .module file if still needed. See https://www.drupal.org/node/3549500.", $error_path, $line, 'HookDeprecationAnalyzer');
      }
      foreach ($this->getFunctions($code) as $function) {
        if (str_starts_with($function['name'], 'template_preprocess_')) {
          $preprocess_functions[] = [$function['name'], $error_path, $function['line']];
        }
      }
    }
    foreach ($preprocess_functions as [$name, $error_path, $line]) {
      $hook = substr($name, strlen('template_preprocess_'));
      if (!isset($registered[$hook]) && !isset($registered[$name])) {
        $deprecations[] = new DeprecationMessage("$name() is not set as the initial preprocess of the $hook theme hook. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Add 'initial preprocess' => '$name' to the theme hook. See https://www.drupal.org/node/3504125.", $error_path, $line, 'HookDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Reads what a PHP file says about theme hooks.
   *
   * @param string $code
   *   The PHP code.
   * @param list<string> $modules
   *   The module names, to find functions that implement hook_theme().
   *
   * @return array{registered: array<string, true>, file_keys: list<array{string, string, int}>}
   *   The theme hooks with an initial preprocess, also function names used as a
   *   string, and the theme hook, key and line of each 'file' and 'includes'
   *   key in a hook_theme() implementation.
   */
  private function findThemeHookData(string $code, array $modules): array {
    $result = ['registered' => [], 'file_keys' => []];
    try {
      $tokens = array_values(array_filter(
        token_get_all($code),
        static fn ($token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE),
      ));
    }
    catch (\ParseError) {
      // Ignore syntax errors, PHPStan reports them.
      return $result;
    }

    // Each open bracket has an entry with the array key it is the value of.
    $stack = [];
    $key = NULL;
    $braces = 0;
    $next_is_theme_hook = FALSE;
    $entering_theme_hook = FALSE;
    $theme_hook_braces = NULL;
    foreach ($tokens as $index => $token) {
      $next = $tokens[$index + 1] ?? NULL;
      if (is_array($token)) {
        if ($token[0] === T_ATTRIBUTE) {
          $next_is_theme_hook = $next_is_theme_hook || $this->attributeIsHookTheme($tokens, $index);
        }
        elseif ($token[0] === T_FUNCTION) {
          $name = is_array($next) && $next[0] === T_STRING ? $next[1] : '';
          $entering_theme_hook = $next_is_theme_hook || (str_ends_with($name, '_theme') && in_array(substr($name, 0, -6), $modules, TRUE));
          $next_is_theme_hook = FALSE;
        }
        elseif ($token[0] === T_ARRAY && $next === '(') {
          $stack[] = $key;
          $key = NULL;
        }
        elseif (in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE)) {
          $braces++;
        }
        elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
          $value = trim($token[1], '\'"');
          if (str_starts_with($value, 'template_preprocess_')) {
            $result['registered'][$value] = TRUE;
          }
          $is_key = is_array($next) && $next[0] === T_DOUBLE_ARROW;
          $keys = array_values(array_filter($stack, 'is_string'));
          $hook = end($keys) ?: NULL;
          if ($is_key && $value === 'initial preprocess' && $hook !== NULL) {
            $result['registered'][$hook] = TRUE;
          }
          elseif ($is_key && $theme_hook_braces !== NULL && $hook !== NULL && in_array($value, ['file', 'includes'], TRUE) && !in_array('variables', $keys, TRUE)) {
            $result['file_keys'][] = [$hook, $value, $token[2]];
          }
          // A value can be for a key like 'x' => or for $hooks['x'] = [.
          if ($is_key || (end($stack) === NULL && $next === ']' && ($tokens[$index + 2] ?? NULL) === '=')) {
            $key = $value;
          }
        }
      }
      elseif ($token === '[' || ($token === '(' && !(is_array($tokens[$index - 1] ?? NULL) && $tokens[$index - 1][0] === T_ARRAY))) {
        $stack[] = $token === '[' ? $key : NULL;
        $key = NULL;
      }
      elseif ($token === ']' || $token === ')') {
        array_pop($stack);
      }
      elseif ($token === ',' || $token === ';') {
        $key = NULL;
        $entering_theme_hook = $token === ',' ? $entering_theme_hook : FALSE;
      }
      elseif ($token === '{') {
        $braces++;
        if ($entering_theme_hook) {
          $theme_hook_braces = $braces;
          $entering_theme_hook = FALSE;
        }
      }
      elseif ($token === '}') {
        if ($theme_hook_braces === $braces) {
          $theme_hook_braces = NULL;
        }
        $braces--;
      }
    }
    return $result;
  }

  /**
   * Tells whether an attribute group has #[Hook('theme')].
   *
   * @param array $tokens
   *   The tokens of the file, without whitespace and comments.
   * @param int $index
   *   The index of the T_ATTRIBUTE token.
   *
   * @return bool
   *   TRUE if the attribute group has #[Hook('theme')].
   */
  private function attributeIsHookTheme(array $tokens, int $index): bool {
    $name = $tokens[$index + 1] ?? NULL;
    if (!is_array($name) || !in_array($name[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE)) {
      return FALSE;
    }
    $parts = explode('\\', $name[1]);
    if (end($parts) !== 'Hook' || ($tokens[$index + 2] ?? NULL) !== '(') {
      return FALSE;
    }
    $argument = ($tokens[$index + 4] ?? NULL) === ':' ? ($tokens[$index + 5] ?? NULL) : ($tokens[$index + 3] ?? NULL);
    return is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING && trim($argument[1], '\'"') === 'theme';
  }

  /**
   * Finds the deprecated hook that a function implements.
   *
   * @param string $function
   *   The function name.
   * @param list<string> $modules
   *   The module names to look for at the start of the function name, longest
   *   name first.
   *
   * @return array{string, string}|null
   *   The hook name without the hook_ prefix and the @deprecated text, or NULL
   *   if this does not implement a deprecated hook.
   */
  private function findDeprecatedImplementation(string $function, array $modules): ?array {
    foreach ($modules as $module) {
      if (str_starts_with($function, $module . '_')) {
        $hook = substr($function, strlen($module) + 1);
        $description = $this->getHookDeprecation($hook);
        if ($description !== NULL) {
          return [$hook, $description];
        }
      }
    }
    return NULL;
  }

  /**
   * Returns the @deprecated text of a hook.
   *
   * @param string $hook
   *   The hook name without the hook_ prefix.
   *
   * @return string|null
   *   The @deprecated text, or NULL if the hook is not deprecated.
   */
  private function getHookDeprecation(string $hook): ?string {
    if (isset($this->deprecatedHooks[$hook])) {
      return $this->deprecatedHooks[$hook];
    }
    foreach ($this->deprecatedHookPatterns as $pattern => $description) {
      if (preg_match($pattern, $hook)) {
        return $description;
      }
    }
    return NULL;
  }

  /**
   * Finds the hook that a function implements.
   *
   * @param string $function
   *   The function name.
   * @param list<string> $modules
   *   The module names to look for at the start of the function name, longest
   *   name first.
   *
   * @return string|null
   *   The hook name without the hook_ prefix, or NULL if this is not a hook.
   */
  private function findHook(string $function, array $modules): ?string {
    foreach ($modules as $module) {
      if (str_starts_with($function, $module . '_')) {
        $hook = substr($function, strlen($module) + 1);
        if ($this->isHook($hook)) {
          return $hook;
        }
      }
    }
    return NULL;
  }

  /**
   * Tells whether a name is a documented hook.
   *
   * @param string $name
   *   The function name without the module name.
   *
   * @return bool
   *   TRUE if a *.api.php file documents this hook.
   */
  private function isHook(string $name): bool {
    $this->loadHooks();
    if (in_array($name, self::NOT_DISCOVERED_HOOKS, TRUE)) {
      return FALSE;
    }
    if (isset($this->hooks[$name])) {
      return TRUE;
    }
    foreach ($this->hookPatterns as $pattern) {
      if (preg_match($pattern, $name)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Reads the hook names from the *.api.php files of core and all modules.
   */
  private function loadHooks(): void {
    if ($this->hooks !== NULL) {
      return;
    }
    $this->hooks = [];
    $entity_types = '(' . implode('|', array_keys($this->entityTypeManager->getDefinitions())) . ')';
    $files = [DRUPAL_ROOT . '/core/core.api.php'];
    $files = array_merge($files, $this->getFiles(DRUPAL_ROOT . '/core/lib', '.api.php'));
    foreach ($this->moduleExtensionList->getList() as $module) {
      $files = array_merge($files, glob(DRUPAL_ROOT . '/' . $module->getPath() . '/*.api.php') ?: []);
    }
    foreach ($files as $file) {
      $code = is_file($file) ? file_get_contents($file) : '';
      if (!preg_match_all('/^function hook_(\w+)\s*\(/m', $code, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
      }
      foreach ($matches[1] as $index => [$hook]) {
        // Hooks like hook_form_FORM_ID_alter() have a variable part. Only allow
        // known entity types for hook_ENTITY_TYPE_load() and similar hooks, or
        // a helper like mymodule_row_update() would match.
        $pattern = str_replace('ENTITY_TYPE', $entity_types, $hook);
        $pattern = preg_replace('/[A-Z][A-Z0-9_]*[A-Z0-9]/', '\w+', $pattern);
        $description = $this->getDeprecationText(substr($code, 0, $matches[0][$index][1]));
        if ($pattern === $hook) {
          $this->hooks[$hook] = TRUE;
          if ($description !== NULL) {
            $this->deprecatedHooks[$hook] = $description;
          }
        }
        else {
          $regex = '/^' . $pattern . '$/';
          $this->hookPatterns[$regex] = $regex;
          if ($description !== NULL) {
            $this->deprecatedHookPatterns[$regex] = $description;
          }
        }
      }
    }
    $this->hookPatterns = array_values($this->hookPatterns);
  }

  /**
   * Reads the @deprecated text from the doc comment that ends some code.
   *
   * @param string $code
   *   The code before a function.
   *
   * @return string|null
   *   The text after @deprecated on one line, or NULL if there is none.
   */
  private function getDeprecationText(string $code): ?string {
    $code = rtrim($code);
    $start = strrpos($code, '/**');
    if (!str_ends_with($code, '*/') || $start === FALSE) {
      return NULL;
    }
    // The text ends at an empty line, the next tag or the end of the comment.
    if (!preg_match('/@deprecated\s+(.*?)(?=\n\s*\*\s*(?:\n|@)|\n?\s*\*\/)/s', substr($code, $start), $match)) {
      return NULL;
    }
    return preg_replace('/\s*\n\s*\*\s*/', ' ', trim($match[1]));
  }

  /**
   * Finds the hook_hook_info() groups that a project defines.
   *
   * @param string $project_dir
   *   The project directory.
   *
   * @return list<string>
   *   The group names.
   */
  private function getProjectHookGroups(string $project_dir): array {
    $groups = [];
    foreach ($this->getFiles($project_dir, '.module') as $file) {
      if (preg_match('/^function \w+_hook_info\(.*?^}/ms', file_get_contents($file), $function) && preg_match_all('/[\'"]group[\'"]\s*=>\s*[\'"](\w+)[\'"]/', $function[0], $matches)) {
        $groups = array_merge($groups, $matches[1]);
      }
    }
    return $groups;
  }

  /**
   * Finds test modules and themes in tests directories of a project.
   *
   * Sites do not list these unless they scan for tests, but core runs their
   * hooks the same way when a test installs them. Other files in tests
   * directories are not part of the hook system.
   *
   * @param string $project_dir
   *   The project directory.
   *
   * @return array<string, string>
   *   The directories of the extensions, keyed by extension name.
   */
  private function getTestModules(string $project_dir): array {
    $directories = [];
    foreach ($this->getFiles($project_dir, '.info.yml', ['js', 'css', 'src']) as $file) {
      if (str_contains(substr($file, strlen($project_dir)), '/tests/')) {
        $directories[basename($file, '.info.yml')] = dirname($file);
      }
    }
    return $directories;
  }

  /**
   * Analyzes the extension files that core deprecates.
   *
   * All extensions of the project are checked, including submodules, subthemes
   * and test extensions:
   * - Drupal 12 only finds the procedural hook implementations of a theme in
   *   its THEME.theme file, not in theme-settings.php.
   * - Drupal 12 removes support for THEME_ENGINE.engine files.
   * - Drupal 13 removes MODULE.module, PROFILE.profile and THEME.theme files.
   *   A file is not reported if a function in it has the
   *   ExtensionFileIsConverted attribute, which keeps the file for older
   *   Drupal versions. A module or profile file is not reported either if all
   *   its functions have the LegacyHook or LegacyRequirementsHook attribute.
   *   This follows \Drupal\Core\Hook\HookCollectorPass. These are only
   *   reported when the next major version is Drupal 13 or later, because
   *   messages for a later version are ignored.
   *
   * @param string $project_dir
   *   The project directory.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeExtensionFiles(string $project_dir): array {
    $deprecations = [];
    // Drupal 11.5 deprecates code in these files for Drupal 13. On Drupal 11
    // the message is for a later major version than the next one, so it is
    // in a category of its own and does not change the next step.
    $check_drupal_13 = ProjectCollector::getDrupalCoreMajorVersion() >= 11;
    foreach ($this->getFiles($project_dir, '.info.yml', ['js', 'css', 'src']) as $info_file) {
      // There may be info.yml files in shipped configuration, which have more
      // parts in their names.
      if (substr_count(basename($info_file), '.') !== 2) {
        continue;
      }
      $directory = dirname($info_file);
      $name = basename($info_file, '.info.yml');
      $type = $this->getExtensionType($info_file);
      if ($type === 'theme') {
        if (is_file("$directory/theme-settings.php")) {
          $deprecations[] = new DeprecationMessage("The theme-settings.php file of the $name theme is used. Deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. Drupal 12 doesn't load the hook implementations in this file, so the hooks don't run. Move the implementations to the $name.theme file, or to a class with the Hook attribute. See https://www.drupal.org/node/3587273.", str_replace(DRUPAL_ROOT . '/', '', "$directory/theme-settings.php"), 1, 'HookDeprecationAnalyzer');
        }
        $file = "$directory/$name.theme";
        if ($check_drupal_13 && is_file($file) && ($line = $this->findCodeToConvert(file_get_contents($file), FALSE)) !== NULL) {
          $deprecations[] = new DeprecationMessage("Code in the $name.theme file is used. Deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Move the code to classes, like hook implementations to classes with the Hook attribute. See https://www.drupal.org/node/3581222.", str_replace(DRUPAL_ROOT . '/', '', $file), $line, 'HookDeprecationAnalyzer');
        }
      }
      elseif ($check_drupal_13 && ($type === 'module' || $type === 'profile')) {
        $file = "$directory/$name.$type";
        if (is_file($file) && ($line = $this->findCodeToConvert(file_get_contents($file), TRUE)) !== NULL) {
          $deprecations[] = new DeprecationMessage("Code in the $name.$type file is used. Deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Move the code to classes, like hook implementations to classes with the Hook attribute. Functions that are still needed for older Drupal versions can stay with the LegacyHook attribute. See https://www.drupal.org/node/3619765.", str_replace(DRUPAL_ROOT . '/', '', $file), $line, 'HookDeprecationAnalyzer');
        }
      }
    }
    foreach ($this->getFiles($project_dir, '.engine', ['js', 'css', 'src']) as $file) {
      $engine = basename($file, '.engine');
      $deprecations[] = new DeprecationMessage("The $engine.engine theme engine file is used. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Drupal 12 doesn't load .engine files. Convert $engine.engine to a service. See https://www.drupal.org/node/3547356.", str_replace(DRUPAL_ROOT . '/', '', $file), 1, 'HookDeprecationAnalyzer');
    }
    return $deprecations;
  }

  /**
   * Finds the first function of an extension file that core deprecates.
   *
   * @param string $code
   *   The PHP code of the file.
   * @param bool $allow_legacy
   *   Whether functions with the LegacyHook or LegacyRequirementsHook
   *   attribute can stay. This is the case for module and profile files.
   *
   * @return int|null
   *   The line of the first function to convert, 1 if the file is reported
   *   without one, or NULL if the file is not reported.
   */
  private function findCodeToConvert(string $code, bool $allow_legacy): ?int {
    try {
      $tokens = token_get_all($code);
    }
    catch (\ParseError) {
      // Ignore syntax errors, PHPStan reports them.
      return NULL;
    }

    $first = NULL;
    $depth = 0;
    $attributes = [];
    foreach ($tokens as $index => $token) {
      if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE))) {
        $depth++;
      }
      elseif ($token === '}') {
        $depth--;
      }
      elseif (is_array($token) && $depth === 0) {
        if ($token[0] === T_ATTRIBUTE) {
          $attributes = array_merge($attributes, $this->getAttributeNames($tokens, $index));
        }
        elseif ($token[0] === T_FUNCTION && $this->isFunctionDeclaration($tokens, $index)) {
          if (in_array('ExtensionFileIsConverted', $attributes, TRUE)) {
            return NULL;
          }
          if ($first === NULL && !($allow_legacy && array_intersect(['LegacyHook', 'LegacyRequirementsHook'], $attributes))) {
            $first = $token[2];
          }
          $attributes = [];
        }
      }
    }
    // Core reports a theme file without functions, but not a module or
    // profile file.
    return $first ?? ($allow_legacy ? NULL : 1);
  }

  /**
   * Checks whether a function keyword starts a named function declaration.
   *
   * @param array $tokens
   *   The PHP tokens.
   * @param int $index
   *   The index of the T_FUNCTION token.
   *
   * @return bool
   *   FALSE for closures and for "use function" statements.
   */
  private function isFunctionDeclaration(array $tokens, int $index): bool {
    $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    for ($i = $index - 1; $i >= 0 && is_array($tokens[$i]) && in_array($tokens[$i][0], $skip, TRUE); $i--);
    if ($i >= 0 && is_array($tokens[$i]) && $tokens[$i][0] === T_USE) {
      return FALSE;
    }
    $skip[] = T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    for ($i = $index + 1; isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], $skip, TRUE); $i++);
    return isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING;
  }

  /**
   * Returns the short names of the attributes in an attribute group.
   *
   * @param array $tokens
   *   The PHP tokens.
   * @param int $index
   *   The index of the T_ATTRIBUTE token that starts the group.
   *
   * @return list<string>
   *   The attribute names without their namespace.
   */
  private function getAttributeNames(array $tokens, int $index): array {
    $names = [];
    $depth = 1;
    $parentheses = 0;
    for ($i = $index + 1; isset($tokens[$i]) && $depth > 0; $i++) {
      $token = $tokens[$i];
      if ($token === '[') {
        $depth++;
      }
      elseif ($token === ']') {
        $depth--;
      }
      elseif ($token === '(') {
        $parentheses++;
      }
      elseif ($token === ')') {
        $parentheses--;
      }
      elseif ($parentheses === 0 && $depth === 1 && is_array($token)
        && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE)) {
        $parts = explode('\\', $token[1]);
        $names[] = end($parts);
      }
    }
    return $names;
  }

  /**
   * Returns the type of the extension of an info file.
   *
   * @param string $file
   *   The path of the info file.
   *
   * @return string|null
   *   The extension type, like "module" or "theme", or NULL if unknown.
   */
  private function getExtensionType(string $file): ?string {
    try {
      $info = Yaml::decode(file_get_contents($file));
    }
    catch (InvalidDataTypeException) {
      return NULL;
    }
    return is_array($info) && is_string($info['type'] ?? NULL) ? $info['type'] : NULL;
  }

  /**
   * Tells whether an info file is for a theme.
   *
   * @param string $file
   *   The path of the info file.
   *
   * @return bool
   *   TRUE if the info file is for a theme.
   */
  private function isTheme(string $file): bool {
    try {
      $info = Yaml::decode(file_get_contents($file));
    }
    catch (InvalidDataTypeException) {
      return FALSE;
    }
    return is_array($info) && ($info['type'] ?? '') === 'theme';
  }

  /**
   * Finds the functions defined at the top level of a PHP file.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return list<array{name: string, line: int, legacy: bool}>
   *   The name and line of each function, and whether it has one of core's
   *   legacy hook attributes.
   */
  private function getFunctions(string $code): array {
    try {
      $tokens = token_get_all($code);
    }
    catch (\ParseError) {
      // Ignore syntax errors, PHPStan reports them.
      return [];
    }

    $functions = [];
    $depth = 0;
    $legacy = FALSE;
    foreach ($tokens as $index => $token) {
      if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE))) {
        $depth++;
      }
      elseif ($token === '}') {
        $depth--;
      }
      elseif (is_array($token) && $depth === 0) {
        if ($token[0] === T_ATTRIBUTE) {
          $legacy = $legacy || $this->attributeIsLegacyHook($tokens, $index);
        }
        elseif ($token[0] === T_FUNCTION) {
          $next = $tokens[$index + 2] ?? NULL;
          if (is_array($next) && $next[0] === T_STRING) {
            $functions[] = ['name' => $next[1], 'line' => $next[2], 'legacy' => $legacy];
          }
          $legacy = FALSE;
        }
      }
    }
    return $functions;
  }

  /**
   * Finds the hooks named in Hook attributes in a PHP file.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return list<array{hook: string, line: int}>
   *   The hook name and the line of each attribute.
   */
  private function getHookAttributes(string $code): array {
    try {
      $tokens = array_values(array_filter(token_get_all($code), static fn ($token): bool => !is_array($token) || $token[0] !== T_WHITESPACE));
    }
    catch (\ParseError) {
      // Ignore syntax errors, PHPStan reports them.
      return [];
    }

    $attributes = [];
    $depth = 0;
    foreach ($tokens as $index => $token) {
      if (is_array($token) && $token[0] === T_ATTRIBUTE) {
        $depth = 1;
      }
      elseif ($depth > 0 && $token === '[') {
        $depth++;
      }
      elseif ($depth > 0 && $token === ']') {
        $depth--;
      }
      elseif ($depth > 0 && is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE)) {
        $parts = explode('\\', $token[1]);
        if (end($parts) !== 'Hook' || ($tokens[$index + 1] ?? NULL) !== '(') {
          continue;
        }
        // The name can be a named argument: #[Hook(hook: 'cron')].
        $argument = ($tokens[$index + 3] ?? NULL) === ':' ? ($tokens[$index + 4] ?? NULL) : ($tokens[$index + 2] ?? NULL);
        if (is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING) {
          $attributes[] = ['hook' => trim($argument[1], '\'"'), 'line' => $token[2]];
        }
      }
    }
    return $attributes;
  }

  /**
   * Tells whether an attribute group contains one of core's legacy attributes.
   *
   * Core's hook discovery treats these the same way.
   *
   * @param array $tokens
   *   The tokens of the file.
   * @param int $index
   *   The index of the T_ATTRIBUTE token.
   *
   * @return bool
   *   TRUE if the attribute group contains a legacy attribute.
   *
   * @see \Drupal\Core\Hook\HookCollectorPass::collectModuleHookImplementations()
   */
  private function attributeIsLegacyHook(array $tokens, int $index): bool {
    $depth = 1;
    for ($i = $index + 1; isset($tokens[$i]) && $depth > 0; $i++) {
      $token = $tokens[$i];
      if ($token === '[') {
        $depth++;
      }
      elseif ($token === ']') {
        $depth--;
      }
      elseif (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE) && preg_match('/(^|\\\\)Legacy(Hook|ModuleImplementsAlter|RequirementsHook)$/', $token[1])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Finds files with a given ending under a path.
   *
   * Follows \Drupal\Core\Hook\HookCollectorPass::filterIterator(), which does
   * not look at tests, assets and classes.
   *
   * @param string $path
   *   Base path to find files in.
   * @param string $ending
   *   The ending of the file names, like ".inc".
   * @param list<string> $skip_directories
   *   The directory names to skip.
   *
   * @return list<string>
   *   A list of paths to the files found under the base path.
   */
  private function getFiles(string $path, string $ending, array $skip_directories = ['tests', 'js', 'css', 'src']): array {
    $files = $this->fileFinder->getFiles($path, $ending);
    $ignore_directories = array_merge(
      $skip_directories,
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE)) {
        $files = array_merge($files, $this->getFiles($dir, $ending, $skip_directories));
      }
    }
    return $files;
  }

}
