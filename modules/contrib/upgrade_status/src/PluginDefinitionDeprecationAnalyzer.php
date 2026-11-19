<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;

/**
 * The plugin definition deprecation analyzer.
 *
 * Core only reports these deprecations when the plugin definitions are
 * discovered or used, and PHPStan can't find them:
 * - The 'access callback' key in Views handler definitions, like in
 *   hook_views_data().
 * - The theme_file key in annotations of Views plugins.
 * - CKEditor 5 drupalElementStyles icons without the Icon prefix in
 *   *.ckeditor5.yml files.
 * - Layout plugins without a label, in *.layouts.yml files, Layout attributes
 *   and Layout annotations.
 */
final class PluginDefinitionDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The message for the 'access callback' key in Views handler definitions.
   */
  private const ACCESS_CALLBACK = "The 'access callback' key is used in a Views handler definition. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Implement the access() method of the handler class instead. See https://www.drupal.org/node/3539918.";

  /**
   * The message for the theme_file key, with the plugin and its replacement.
   */
  private const THEME_FILE = 'The %s Views plugin has a theme_file key. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Remove the key, and move the preprocess code of the include file to the %s() method of the %s class. See https://www.drupal.org/node/3548325.';

  /**
   * The message for a CKEditor 5 icon, with the icon, plugin and replacement.
   */
  private const ICON = 'The %s icon is used in drupalElementStyles of the %s CKEditor 5 plugin. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use %s instead. See https://www.drupal.org/node/3528806.';

  /**
   * The message for a layout without a label, with the layout ID.
   */
  private const LAYOUT = 'The %s layout has no label. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Add a label to the layout definition. See https://www.drupal.org/node/3464076.';

  /**
   * The CKEditor 5 icons that were renamed, keyed by the old name.
   *
   * Other icons only get the Icon prefix.
   */
  private const RENAMED_ICONS = [
    'objectLeft' => 'IconObjectInlineLeft',
    'objectRight' => 'IconObjectInlineRight',
    'objectBlockLeft' => 'IconObjectLeft',
    'objectBlockRight' => 'IconObjectRight',
  ];

  /**
   * The extensions of PHP files.
   */
  private const PHP_EXTENSIONS = ['php', 'module', 'inc', 'install', 'theme', 'profile'];

  /**
   * Constructs a new plugin definition deprecation analyzer.
   *
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * Analyzes the plugin definitions of an extension.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array {
    $deprecations = [];
    foreach ($this->getFiles(DRUPAL_ROOT . '/' . $extension->getPath()) as $file) {
      $code = file_get_contents($file);
      if (str_ends_with($file, '.ckeditor5.yml')) {
        $messages = $this->findIcons($code);
      }
      elseif (str_ends_with($file, '.layouts.yml')) {
        $messages = $this->findYamlLayouts($code);
      }
      else {
        $messages = array_merge(
          $this->findAccessCallbacks($code),
          str_contains($file, '/src/Plugin/views/') ? $this->findThemeFiles($code) : [],
          str_contains($file, '/src/Plugin/Layout/') ? $this->findClassLayouts($code) : [],
        );
      }
      foreach ($messages as [$message, $line]) {
        $deprecations[] = new DeprecationMessage($message, str_replace(DRUPAL_ROOT . '/', '', $file), $line, 'PluginDefinitionDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Finds the 'access callback' key in Views data.
   *
   * Only files that implement hook_views_data(), hook_views_data_alter() or
   * EntityViewsData::getViewsData() are checked. Array items and assignments
   * are reported, but reads of the key are not.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return array<array{string, int}>
   *   The messages and their lines.
   */
  private function findAccessCallbacks(string $code): array {
    if (!str_contains($code, 'access callback') || !preg_match('/views_data|getViewsData/', $code)) {
      return [];
    }
    try {
      $tokens = array_values(array_filter(
        \PhpToken::tokenize($code),
        static fn (\PhpToken $token): bool => !$token->isIgnorable(),
      ));
    }
    catch (\ParseError $e) {
      return [];
    }
    $lines = [];
    foreach ($tokens as $index => $token) {
      if (!$token->is(T_CONSTANT_ENCAPSED_STRING) || !in_array($token->text, ["'access callback'", '"access callback"'], TRUE)) {
        continue;
      }
      // Report array items and assignments, but not reads of the key.
      $next = $tokens[$index + 1] ?? NULL;
      $is_assignment = ($tokens[$index - 1] ?? NULL)?->text === '['
        && $next?->text === ']'
        && ($tokens[$index + 2] ?? NULL)?->text === '=';
      if ($next?->is(T_DOUBLE_ARROW) || $is_assignment) {
        $lines[$token->line] = [self::ACCESS_CALLBACK, $token->line];
      }
    }
    return array_values($lines);
  }

  /**
   * Finds the theme_file key in annotations of Views plugins.
   *
   * Views only uses the key when the plugin has a theme and registers it.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return array<array{string, int}>
   *   The messages and their lines.
   */
  private function findThemeFiles(string $code): array {
    if (!str_contains($code, 'theme_file') || !preg_match_all('/^\s*\*\s*@Views[A-Z]\w*\s*\(/m', $code, $matches, PREG_OFFSET_CAPTURE)) {
      return [];
    }
    $provider = preg_match('/^namespace\s+Drupal\\\\(\w+)\\\\/m', $code, $namespace) ? $namespace[1] : 'example';
    $messages = [];
    foreach ($matches[0] as [$match, $offset]) {
      $arguments = $this->getArguments($code, $offset + strlen($match) - 1, ['"']);
      $theme_file = $arguments['theme_file'] ?? NULL;
      $theme = trim($arguments['theme'][0] ?? '', '"');
      if ($theme_file === NULL || in_array($theme_file[0], ['', '""'], TRUE) || $theme === '' || strcasecmp($arguments['register_theme'][0] ?? '', 'FALSE') === 0) {
        continue;
      }
      $messages[] = [
        sprintf(self::THEME_FILE, trim($arguments['id'][0] ?? $theme, '"'), 'preprocess' . $this->camelize($theme), 'Drupal\\' . $provider . '\\Hook\\' . $this->camelize($provider) . 'ThemeHooks'),
        substr_count($code, "\n", 0, $theme_file[1]) + 1,
      ];
    }
    return $messages;
  }

  /**
   * Finds CKEditor 5 drupalElementStyles icons without the Icon prefix.
   *
   * @param string $code
   *   The YAML code of a *.ckeditor5.yml file.
   *
   * @return array<array{string, int}>
   *   The messages and their lines.
   */
  private function findIcons(string $code): array {
    $lines = explode("\n", $code);
    $messages = [];
    $reported = [];
    foreach ($this->decode($code) as $plugin_id => $definition) {
      $groups = $definition['ckeditor5']['config']['drupalElementStyles'] ?? NULL;
      if (!is_array($groups)) {
        continue;
      }
      $start = $this->findLine($lines, '/^[\'"]?' . preg_quote((string) $plugin_id, '/') . '[\'"]?\s*:/', 0, []) ?? 0;
      foreach ($groups as $styles) {
        foreach (is_array($styles) ? $styles : [] as $style) {
          $icon = is_array($style) ? ($style['icon'] ?? NULL) : NULL;
          // Core maps the icon when this pattern doesn't match.
          if (!is_string($icon) || preg_match('/^(<svg)|(Icon)/', $icon)) {
            continue;
          }
          $line = $this->findLine($lines, '/^\s*(-\s*)?icon\s*:\s*[\'"]?' . preg_quote($icon, '/') . '[\'"]?\s*(#.*)?$/', $start, $reported) ?? $start;
          $reported[$line] = TRUE;
          $messages[] = [
            sprintf(self::ICON, $icon, $plugin_id, self::RENAMED_ICONS[$icon] ?? 'Icon' . ucfirst($icon)),
            $line + 1,
          ];
        }
      }
    }
    return $messages;
  }

  /**
   * Finds layouts without a label in a *.layouts.yml file.
   *
   * Layouts with a deriver are skipped, because the deriver can set the label.
   *
   * @param string $code
   *   The YAML code of a *.layouts.yml file.
   *
   * @return array<array{string, int}>
   *   The messages and their lines.
   */
  private function findYamlLayouts(string $code): array {
    $lines = explode("\n", $code);
    $messages = [];
    foreach ($this->decode($code) as $layout_id => $definition) {
      if (is_array($definition) && empty($definition['label']) && empty($definition['deriver'])) {
        $line = $this->findLine($lines, '/^[\'"]?' . preg_quote((string) $layout_id, '/') . '[\'"]?\s*:/', 0, []) ?? 0;
        $messages[] = [sprintf(self::LAYOUT, $layout_id), $line + 1];
      }
    }
    return $messages;
  }

  /**
   * Finds Layout attributes and annotations without a label.
   *
   * Like core, an attribute with a deriver is not reported, because the
   * deriver can set the label.
   *
   * @param string $code
   *   The PHP code of a layout plugin class.
   *
   * @return array<array{string, int}>
   *   The messages and their lines.
   */
  private function findClassLayouts(string $code): array {
    $patterns = [
      'attribute' => '/#\[\s*\\\\?(?:Drupal\\\\Core\\\\Layout\\\\Attribute\\\\)?Layout\s*\(/',
      'annotation' => '/^\s*\*\s*@Layout\s*\(/m',
    ];
    $messages = [];
    foreach ($patterns as $type => $pattern) {
      preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
      foreach ($matches[0] as [$match, $offset]) {
        $is_attribute = $type === 'attribute';
        $arguments = $this->getArguments($code, $offset + strlen($match) - 1, $is_attribute ? ['"', "'"] : ['"']);
        // The label is the second argument of the attribute.
        if (isset($arguments['label']) || isset($arguments['deriver']) || ($is_attribute && isset($arguments[1]))) {
          continue;
        }
        $id = trim($arguments['id'][0] ?? $arguments[0][0] ?? '', '"\'');
        // An annotation match can start with whitespace of the line before.
        $line = substr_count($code, "\n", 0, $offset + strlen($match) - strlen(ltrim($match))) + 1;
        $messages[] = [sprintf(self::LAYOUT, $id), $line];
      }
    }
    return $messages;
  }

  /**
   * Gets the top-level arguments of an attribute or annotation.
   *
   * @param string $code
   *   The code.
   * @param int $start
   *   The offset of the opening parenthesis.
   * @param string[] $quotes
   *   The characters that start and end a string.
   *
   * @return array<int|string, array{string, int}>
   *   The values and offsets of the arguments, keyed by name for named
   *   arguments and by position for the others.
   */
  private function getArguments(string $code, int $start, array $quotes): array {
    $segments = [];
    $depth = 0;
    $segment_start = $start + 1;
    $length = strlen($code);
    for ($i = $start; $i < $length; $i++) {
      $char = $code[$i];
      if (in_array($char, $quotes, TRUE)) {
        for ($i++; $i < $length && $code[$i] !== $char; $i++) {
          if ($code[$i] === '\\') {
            $i++;
          }
        }
      }
      elseif (in_array($char, ['(', '[', '{'], TRUE)) {
        $depth++;
      }
      elseif (in_array($char, [')', ']', '}'], TRUE)) {
        $depth--;
        if ($depth === 0) {
          $segments[] = [$segment_start, $i];
          break;
        }
      }
      elseif ($char === ',' && $depth === 1) {
        $segments[] = [$segment_start, $i];
        $segment_start = $i + 1;
      }
    }

    $arguments = [];
    foreach ($segments as [$from, $to]) {
      $text = substr($code, $from, $to - $from);
      $offset = $from + strspn($text, " \t\r\n*");
      $text = trim($text, " \t\r\n*");
      if ($text === '') {
        continue;
      }
      if (preg_match('/^(\w+)\s*(?:=(?!>)|:(?!:))\s*(.*)$/s', $text, $named)) {
        $arguments[$named[1]] = [trim($named[2], " \t\r\n*"), $offset];
      }
      else {
        $arguments[] = [$text, $offset];
      }
    }
    return $arguments;
  }

  /**
   * Finds the first line that matches a pattern.
   *
   * @param string[] $lines
   *   The lines of a file.
   * @param string $pattern
   *   The regular expression.
   * @param int $start
   *   The index of the line to start from.
   * @param array<int, bool> $skip
   *   The indexes of lines to skip, as keys.
   *
   * @return int|null
   *   The index of the line, or NULL if no line matches.
   */
  private function findLine(array $lines, string $pattern, int $start, array $skip): ?int {
    for ($i = $start; $i < count($lines); $i++) {
      if (!isset($skip[$i]) && preg_match($pattern, rtrim($lines[$i], "\r"))) {
        return $i;
      }
    }
    return NULL;
  }

  /**
   * Decodes a YAML file.
   *
   * @param string $code
   *   The YAML code.
   *
   * @return array
   *   The decoded data, or an empty array if it is not valid.
   */
  private function decode(string $code): array {
    try {
      $data = Yaml::decode($code);
    }
    catch (InvalidDataTypeException $e) {
      return [];
    }
    return is_array($data) ? $data : [];
  }

  /**
   * Converts a machine name to camel case, like Views does for theme hooks.
   *
   * @param string $name
   *   A machine name, like views_view_list.
   *
   * @return string
   *   The name in camel case, like ViewsViewList.
   */
  private function camelize(string $name): string {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
  }

  /**
   * Finds the PHP and plugin YAML files in a directory.
   *
   * Test fixtures, API documentation and code of other projects are skipped.
   *
   * @param string $path
   *   The directory to search.
   *
   * @return string[]
   *   The paths of the files.
   */
  private function getFiles(string $path): array {
    $files = [];
    $ignore_directories = array_merge(
      ['config', 'css', 'dist', 'js', 'libraries', 'node_modules', 'vendor'],
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    // Hidden files and directories, like .git, are not analyzed.
    foreach ($this->fileFinder->getPaths($path) as $file) {
      $entry = basename($file);
      if ($this->fileFinder->isDirectory($file)) {
        if (!in_array($entry, $ignore_directories, TRUE) && !preg_match('~/tests/fixtures$~', $file)) {
          $files = array_merge($files, $this->getFiles($file));
        }
      }
      elseif (str_ends_with($entry, '.ckeditor5.yml') || str_ends_with($entry, '.layouts.yml') || (in_array(pathinfo($entry, PATHINFO_EXTENSION), self::PHP_EXTENSIONS, TRUE) && !str_ends_with($entry, '.api.php'))) {
        $files[] = $file;
      }
    }
    return $files;
  }

}
