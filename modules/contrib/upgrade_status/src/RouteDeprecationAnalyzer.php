<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Site\Settings;

/**
 * The route deprecation analyzer.
 *
 * Routes and route aliases are deprecated with a deprecated key in their
 * routing.yml definition. Drupal only reports a deprecated alias when it is
 * used, and does not report a deprecated route at all. Route names are
 * strings, so PHPStan doesn't find them either.
 */
final class RouteDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The extensions of the files to check.
   */
  private const FILE_EXTENSIONS = ['php', 'module', 'inc', 'install', 'theme', 'profile', 'twig', 'yml'];

  /**
   * Deprecated routes, keyed by route name.
   *
   * Each value has the deprecation message and the directory of the extension
   * that defines the route.
   *
   * @var array<string, array{string, string}>|null
   */
  private ?array $deprecatedRoutes = NULL;

  /**
   * Constructs a new route deprecation analyzer.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profileExtensionList
   *   The profile extension list service.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ProfileExtensionList $profileExtensionList,
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * Analyzes uses of deprecated routes in a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array {
    $project_dir = DRUPAL_ROOT . '/' . $extension->getPath();
    // A project can use the routes that it deprecates.
    $routes = array_filter(
      $this->getDeprecatedRoutes(),
      static fn (array $route): bool => !str_starts_with($route[1] . '/', $project_dir . '/'),
    );
    if (!$routes) {
      return [];
    }

    $deprecations = [];
    foreach ($this->getFiles($project_dir) as $file) {
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $file);
      $is_yaml = str_ends_with($file, '.yml');
      $lines = explode("\n", file_get_contents($file));
      foreach ($routes as $name => [$message]) {
        $pattern = $this->getPattern($name, $is_yaml);
        foreach ($lines as $index => $line) {
          if (!($is_yaml && preg_match('/^\s*#/', $line)) && preg_match($pattern, $line)) {
            $deprecations[] = new DeprecationMessage("The $name route is used. $message", $error_path, $index + 1, 'RouteDeprecationAnalyzer');
          }
        }
      }
    }
    return $deprecations;
  }

  /**
   * Returns the pattern that finds a route name in a line.
   *
   * @param string $name
   *   The route name.
   * @param bool $is_yaml
   *   Whether the line is from a YAML file.
   *
   * @return string
   *   In PHP and Twig, the route name is a whole string. In YAML, it is a
   *   value, like "route_name: filter.tips" or a "route:filter.tips" link, not
   *   a key, because local task and menu link IDs are often route names.
   */
  private function getPattern(string $name, bool $is_yaml): string {
    $quoted = preg_quote($name, '/');
    if ($is_yaml) {
      return '/(?:[:\[,-]\s*[\'"]?|route:)' . $quoted . '(?=[\'"]?\s*(?:$|[,\]}#]))/';
    }
    return '/([\'"])' . $quoted . '\1/';
  }

  /**
   * Returns the deprecated routes and aliases of all modules and profiles.
   *
   * @return array<string, array{string, string}>
   *   The deprecation message and the extension directory of each deprecated
   *   route, keyed by route name.
   */
  private function getDeprecatedRoutes(): array {
    if ($this->deprecatedRoutes !== NULL) {
      return $this->deprecatedRoutes;
    }
    $this->deprecatedRoutes = [];
    foreach ($this->moduleExtensionList->getList() + $this->profileExtensionList->getList() as $name => $extension) {
      $directory = DRUPAL_ROOT . '/' . $extension->getPath();
      $file = "$directory/$name.routing.yml";
      if (!is_file($file)) {
        continue;
      }
      // Most routing files have no deprecated routes, and decoding takes
      // much longer than this check.
      $contents = file_get_contents($file);
      if (!str_contains($contents, 'deprecated')) {
        continue;
      }
      try {
        $routes = Yaml::decode($contents);
      }
      catch (InvalidDataTypeException) {
        continue;
      }
      foreach (is_array($routes) ? $routes : [] as $route => $definition) {
        $deprecation = is_array($definition) ? $definition['deprecated'] ?? NULL : NULL;
        if (is_array($deprecation)) {
          $this->deprecatedRoutes[(string) $route] = [$this->getMessage((string) $route, $deprecation), $directory];
        }
      }
    }
    return $this->deprecatedRoutes;
  }

  /**
   * Creates the message for a use of a deprecated route.
   *
   * @param string $route
   *   The route name.
   * @param array $deprecation
   *   The deprecated key of the route, with package, version and message.
   *
   * @return string
   *   The deprecation message, starting with the version information in the
   *   format that categorizes messages.
   */
  private function getMessage(string $route, array $deprecation): string {
    $message = trim(str_replace('%alias_id%', $route, (string) ($deprecation['message'] ?? '')));
    if (preg_match('/\bis deprecated ((?:in|as of) drupal:.*)$/is', $message, $matches)) {
      return 'Deprecated ' . $matches[1];
    }
    if ($message === '' && ($deprecation['package'] ?? '') === 'drupal/core' && isset($deprecation['version'])) {
      return "Deprecated in drupal:{$deprecation['version']}.";
    }
    return $message;
  }

  /**
   * Finds the PHP, Twig and YAML files in a directory.
   *
   * Test fixtures and code of other projects are skipped.
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
      ['css', 'dist', 'js', 'libraries', 'node_modules', 'vendor'],
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
      elseif (in_array(pathinfo($entry, PATHINFO_EXTENSION), self::FILE_EXTENSIONS, TRUE) && !str_ends_with($entry, '.api.php')) {
        $files[] = $file;
      }
    }
    return $files;
  }

}
