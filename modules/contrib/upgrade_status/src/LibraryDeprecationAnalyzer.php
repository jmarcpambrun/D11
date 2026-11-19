<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Asset\LibraryDiscoveryParser;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\TwigEnvironment;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\Source;
use Twig\Util\TemplateDirIterator;

/**
 * A library deprecation analyzer.
 */
final class LibraryDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * Core libraries, with what scripts use from them and how to recognize it.
   *
   * The patterns skip names that are part of a longer name, like $.once() or
   * myDrupal.x.
   */
  private const CORE_GLOBALS = [
    'core/jquery' => ['jQuery', '/(?<![\w.$])jQuery\b/'],
    'core/drupal' => ['Drupal', '/(?<![\w.$])Drupal\s*[.\[,)]/'],
    'core/drupalSettings' => ['drupalSettings', '/(?<![\w.$])drupalSettings\b/'],
    'core/once' => ['once', '/(?<![\w.$])(?<!function )once\s*[.(]/'],
  ];

  /**
   * Library definitions read from files, keyed by extension name.
   *
   * @var array<string, array|null>
   */
  private array $libraryDefinitions = [];

  /**
   * The built libraries of installed extensions, or the exception of building.
   *
   * Building the libraries of an extension parses its libraries file and runs
   * hooks. Many references are to the same few extensions, like core.
   *
   * @var array<string, array|\Exception>
   */
  private array $builtLibraries = [];

  /**
   * The names of the installed modules and themes.
   *
   * @var string[]|null
   */
  private ?array $installedExtensions = NULL;

  /**
   * Files that moved out of libraries, keyed by the library they moved out of.
   *
   * @var array<string, list<array{string, array, array}>>|null
   */
  private ?array $movedFiles = NULL;

  /**
   * The library discovery parser.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryParser
   */
  protected $libraryDiscoveryParser;

  /**
   * The Twig environment.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twigEnvironment;

  /**
   * The list of available modules.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The list of available themes.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeExtensionList;

  /**
   * The list of available profiles.
   *
   * @var \Drupal\Core\Extension\ProfileExtensionList
   */
  protected $profileExtensionList;

  /**
   * The file finder.
   *
   * @var \Drupal\upgrade_status\FileFinder
   */
  protected $fileFinder;

  /**
   * Constructs a new library deprecation analyzer.
   *
   * @param \Drupal\Core\Asset\LibraryDiscoveryParser $library_discovery_parser
   *   The library discovery parser.
   * @param \Drupal\Core\Template\TwigEnvironment $twig_environment
   *   The Twig environment.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list
   *   The theme extension handler service.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list
   *   The profile extension handler service.
   * @param \Drupal\upgrade_status\FileFinder $file_finder
   *   The file finder.
   */
  public function __construct(LibraryDiscoveryParser $library_discovery_parser, TwigEnvironment $twig_environment, ModuleExtensionList $module_extension_list, ThemeExtensionList $theme_extension_list, ProfileExtensionList $profile_extension_list, FileFinder $file_finder) {
    $this->fileFinder = $file_finder;
    $this->libraryDiscoveryParser = $library_discovery_parser;
    $this->twigEnvironment = $twig_environment;
    $this->moduleExtensionList = $module_extension_list;
    $this->themeExtensionList = $theme_extension_list;
    $this->profileExtensionList = $profile_extension_list;
  }

  /**
   * Analyzes usages of deprecated libraries in an extension.
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
    $deprecations = array_merge($deprecations, $this->analyzeLibraryDependencies($extension));
    $deprecations = array_merge($deprecations, $this->analyzeSubExtensions($extension));
    if ($extension->getType() === 'theme') {
      $deprecations = array_merge($deprecations, $this->analyzeThemeLibraryOverrides($extension));
      $deprecations = array_merge($deprecations, $this->analyzeMovedFileOverrides(is_array($extension->info['libraries-override'] ?? NULL) ? $extension->info['libraries-override'] : [], $extension->getPathname()));
      $deprecations = array_merge($deprecations, $this->analyzeThemeLibraryExtends($extension));
      $deprecations = array_merge($deprecations, $this->analyzeThemeLibraries($extension));
    }
    $deprecations = array_merge($deprecations, $this->analyzeTwigLibraryDependencies($extension));
    $deprecations = array_merge($deprecations, $this->analyzePhpLibraryReferences($extension));
    $deprecations = array_merge($deprecations, $this->analyzeMissingCoreDependencies($extension));
    $deprecations = array_merge($deprecations, $this->analyzeNegativeWeights($extension));

    return $deprecations;
  }

  /**
   * Analyzes libraries for dependencies on deprecated libraries.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   Array of DeprecationMessage object represent deprecated
   *   libraries detected.
   */
  private function analyzeLibraryDependencies(Extension $extension): array {
    // Drupal\Core\Asset\LibraryDiscoveryParser::buildByExtension() assumes a
    // disabled module is a theme and fails not finding it then, so check if
    // the extension identified is an installed module or theme. The library
    // info will not be available otherwise.
    $installed_modules = array_keys($this->moduleExtensionList->getAllInstalledInfo());
    $installed_themes = array_keys($this->themeExtensionList->getAllInstalledInfo());
    if (!in_array($extension->getName(), $installed_modules) && !in_array($extension->getName(), $installed_themes)) {
      $message = sprintf("The '%s' extension is not installed. Cannot check deprecated library use.", $extension->getName());
      return [new DeprecationMessage($message, $extension->getPath(), 0, 'LibraryDeprecationAnalyzer')];
    }

    try {
      $libraries = $this->libraryDiscoveryParser->buildByExtension($extension->getName());
    }
    catch (\Exception $e) {
      return [new DeprecationMessage($e->getMessage(), $extension->getPath(), 0, 'LibraryDeprecationAnalyzer')];
    }
    $libraries_with_dependencies = array_filter($libraries, function ($library) {
      return isset($library['dependencies']);
    });

    $deprecations = [];
    $file = sprintf('%s/%s.libraries.yml', $extension->getPath(), $extension->getName());
    foreach ($libraries_with_dependencies as $key => $library_with_dependency) {
      foreach ($library_with_dependency['dependencies'] as $dependency) {
        $is_deprecated = $this->isLibraryDeprecated($dependency);
        if (is_null($is_deprecated)) {
          $message = sprintf("The '%s' library (a dependency of '%s') is not defined because the defining extension is not installed. Whether the library is deprecated cannot be checked.", $dependency, $key);
          $deprecations[] = new DeprecationMessage($message, $file, 0, 'LibraryDeprecationAnalyzer');
        }
        elseif (!empty($is_deprecated)) {
          if ($is_deprecated instanceof DeprecationMessage) {
            $is_deprecated->setFile($file);
            $deprecations[] = $is_deprecated;
          }
          else {
            $message = sprintf("The '%s' library is depending on a deprecated library. %s", $key, $is_deprecated);
            $deprecations[] = new DeprecationMessage($message, $file, 0, 'LibraryDeprecationAnalyzer');
          }
        }
      }
    }

    return $deprecations;
  }

  /**
   * Analyze library overrides in a theme.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension (theme) to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   An array of `DeprecationMessage` object for deprecated library overrides.
   *
   * @throws \Exception
   *   Throws an exception if the extension is not a theme.
   */
  private function analyzeThemeLibraryOverrides(Extension $extension): array {
    if ($extension->getType() !== 'theme') {
      throw new \Exception('Library overrides are only available in themes.');
    }
    if (!isset($extension->info['libraries-override'])) {
      return [];
    }

    return array_reduce(array_keys($extension->info['libraries-override']), function ($deprecated_libraries, $library) use ($extension) {
      $is_deprecated = $this->isLibraryDeprecated($library);
      if (is_null($is_deprecated)) {
        $message = sprintf("The '%s' library is not defined because the defining extension is not installed. Whether the library is deprecated cannot be checked.", $library);
        $deprecated_libraries[] = new DeprecationMessage($message, $extension->getFilename(), 0, 'LibraryDeprecationAnalyzer');
      }
      elseif (!empty($is_deprecated)) {
        if ($is_deprecated instanceof DeprecationMessage) {
          $is_deprecated->setFile($extension->getFilename());
          $deprecated_libraries[] = $is_deprecated;
        }
        else {
          $message = "Theme is overriding a deprecated library. $is_deprecated";
          $deprecated_libraries[] = new DeprecationMessage($message, $extension->getFilename(), 0, 'LibraryDeprecationAnalyzer');
        }
      }
      return $deprecated_libraries;
    }, []);
  }

  /**
   * Analyze library extends in a theme.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension (theme) to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   Array of `DeprecationMessage` objects for deprecated library extensions.
   *
   * @throws \Exception
   *   Throws an exception if the extension is not a theme.
   */
  private function analyzeThemeLibraryExtends(Extension $extension): array {
    if ($extension->getType() !== 'theme') {
      throw new \Exception('Library extends are only available in themes.');
    }
    if (!isset($extension->info['libraries-extend'])) {
      return [];
    }

    return array_reduce(array_keys($extension->info['libraries-extend']), function ($deprecated_libraries, $library) use ($extension) {
      $is_deprecated = $this->isLibraryDeprecated($library);
      if (is_null($is_deprecated)) {
        $message = sprintf("The '%s' library is not defined because the defining extension is not installed. Whether the library is deprecated cannot be checked.", $library);
        $deprecated_libraries[] = new DeprecationMessage($message, $extension->getFilename(), 0, 'LibraryDeprecationAnalyzer');
      }
      elseif (!empty($is_deprecated)) {
        if ($is_deprecated instanceof DeprecationMessage) {
          $is_deprecated->setFile($extension->getFilename());
          $deprecated_libraries[] = $is_deprecated;
        }
        else {
          $message = "Theme is extending a deprecated library. $is_deprecated";
          $deprecated_libraries[] = new DeprecationMessage($message, $extension->getFilename(), 0, 'LibraryDeprecationAnalyzer');
        }
      }
      return $deprecated_libraries;
    }, []);
  }

  /**
   * Analyzes libraries-override entries for files that moved to another place.
   *
   * Core libraries list files that moved in their moved_files key. Core applies
   * an override of the old file to the new file until the version in
   * removed_version, and ignores the override from then on. The
   * libraries-extend entries list libraries, not files, so they are not
   * affected.
   *
   * @param array $overrides
   *   The libraries-override entries of a theme.
   * @param string $info_file
   *   The path of the info file of the theme, relative to the Drupal root.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   *
   * @see \Drupal\Core\Asset\LibraryDiscoveryParser::applyLibrariesMovedOverrides()
   */
  private function analyzeMovedFileOverrides(array $overrides, string $info_file): array {
    $deprecations = [];
    foreach ($overrides as $old_library => $override) {
      if (!is_array($override) || (!isset($override['css']) && !isset($override['js']))) {
        continue;
      }
      foreach ($this->getMovedFiles()[$old_library] ?? [] as [$new_library, $definition, $moved]) {
        $files = [];
        foreach (is_array($override['css'] ?? NULL) ? $override['css'] : [] as $category => $category_files) {
          foreach (is_array($category_files) ? array_keys($category_files) : [] as $original) {
            $new = $moved['css'][$category][$original] ?? NULL;
            if (is_array($new) && !empty($new)) {
              [$new_category, $new_file] = $this->findCssFile($definition, $new_library, (string) array_key_first($new), (string) reset($new));
              $to = "$new_file in the $new_category category of $new_library";
              $files[] = [$original, " in the $category category", $to];
            }
          }
        }
        foreach (is_array($override['js'] ?? NULL) ? array_keys($override['js']) : [] as $original) {
          $new = $moved['js'][$original] ?? NULL;
          if (is_string($new)) {
            $files[] = [$original, '', "$new in $new_library"];
          }
        }
        if (empty($files)) {
          continue;
        }
        $deprecated = 'drupal:' . preg_replace('/^drupal:/', '', (string) ($moved['deprecation_version'] ?? ''));
        $removed = preg_replace('/^drupal:/', '', (string) ($moved['removed_version'] ?? ''));
        $major = explode('.', $removed)[0];
        $link = (string) ($moved['deprecation_link'] ?? '');
        foreach ($files as [$original, $from, $to]) {
          $deprecations[] = new DeprecationMessage("The libraries-override of $old_library targets $original$from. Deprecated in $deprecated and is removed from drupal:$removed. Drupal $major ignores this override, because the file moved. Override $to instead. See $link.", $info_file, $this->findOverrideLine($info_file, (string) $old_library, (string) $original), 'LibraryDeprecationAnalyzer');
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds the files that moved out of libraries.
   *
   * @return array<string, list<array{string, array, array}>>
   *   The library the files moved to, its definition and its moved_files entry,
   *   keyed by the library the files moved out of.
   */
  private function getMovedFiles(): array {
    if ($this->movedFiles === NULL) {
      $this->movedFiles = [];
      $names = ['core'];
      foreach ([$this->moduleExtensionList, $this->themeExtensionList, $this->profileExtensionList] as $list) {
        $names = array_merge($names, array_keys($list->getList()));
      }
      foreach (array_unique($names) as $name) {
        foreach ($this->getLibraryDefinitions($name) ?? [] as $library => $definition) {
          foreach (is_array($definition['moved_files'] ?? NULL) ? $definition['moved_files'] : [] as $old_library => $moved) {
            if (is_array($moved)) {
              $this->movedFiles[$old_library][] = ["$name/$library", $definition, $moved];
            }
          }
        }
      }
    }
    return $this->movedFiles;
  }

  /**
   * Finds the category and path of a CSS file in a library definition.
   *
   * Some moved_files entries in core name another category than the library
   * uses, or start the path with the extension name. This finds where the file
   * is in the library.
   *
   * @param array $definition
   *   The library definition.
   * @param string $library
   *   The library, like "core/drupal.fieldgroup".
   * @param string $category
   *   The category in the moved_files entry.
   * @param string $file
   *   The path in the moved_files entry.
   *
   * @return array{string, string}
   *   The category and the path of the file.
   */
  private function findCssFile(array $definition, string $library, string $category, string $file): array {
    $extension_name = explode('/', $library)[0];
    foreach (array_unique([$file, preg_replace('#^' . preg_quote($extension_name, '#') . '/#', '', $file)]) as $path) {
      foreach (is_array($definition['css'] ?? NULL) ? $definition['css'] : [] as $library_category => $files) {
        if (is_array($files) && array_key_exists($path, $files)) {
          return [(string) $library_category, $path];
        }
      }
    }
    return [$category, $file];
  }

  /**
   * Finds the line of a file in a libraries-override entry of an info file.
   *
   * @param string $file
   *   The path of the info file, relative to the Drupal root or absolute.
   * @param string $library
   *   The library that is overridden.
   * @param string $asset
   *   The path of the file that is overridden.
   *
   * @return int
   *   The line number if found, 0 otherwise.
   */
  private function findOverrideLine(string $file, string $library, string $asset): int {
    $path = str_starts_with($file, '/') ? $file : DRUPAL_ROOT . '/' . $file;
    $in_library = FALSE;
    foreach (explode("\n", (string) @file_get_contents($path)) as $index => $line) {
      if (preg_match('/^\s*[\'"]?' . preg_quote($library, '/') . '[\'"]?\s*:/', $line)) {
        $in_library = TRUE;
      }
      elseif ($in_library && preg_match('/^\s*[\'"]?' . preg_quote($asset, '/') . '[\'"]?\s*:/', $line)) {
        return $index + 1;
      }
    }
    return 0;
  }

  /**
   * Analyzes the libraries that a theme adds to every page.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension (theme) to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeThemeLibraries(Extension $extension): array {
    $deprecations = [];
    $libraries = is_array($extension->info['libraries'] ?? NULL) ? $extension->info['libraries'] : [];
    foreach (array_filter($libraries, 'is_string') as $library) {
      $line = $this->findListItemLine($extension->getPathname(), $library);
      $is_deprecated = $this->isLibraryDeprecated($library);
      if (is_null($is_deprecated)) {
        $message = sprintf("The '%s' library is not defined because the defining extension is not installed. Whether the library is deprecated cannot be checked.", $library);
        $deprecations[] = new DeprecationMessage($message, $extension->getPathname(), $line, 'LibraryDeprecationAnalyzer');
      }
      elseif ($is_deprecated instanceof DeprecationMessage) {
        $is_deprecated->setFile($extension->getPathname());
        $deprecations[] = $is_deprecated;
      }
      elseif (!empty($is_deprecated)) {
        $deprecations[] = new DeprecationMessage("Theme is adding a deprecated library to every page. $is_deprecated", $extension->getPathname(), $line, 'LibraryDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Finds the line of a list item in a YAML file.
   *
   * @param string $file
   *   The path of the file, relative to the Drupal root or absolute.
   * @param string $value
   *   The value of the list item.
   *
   * @return int
   *   The line number if found, 0 otherwise.
   */
  private function findListItemLine(string $file, string $value): int {
    $path = str_starts_with($file, '/') ? $file : DRUPAL_ROOT . '/' . $file;
    foreach (explode("\n", (string) @file_get_contents($path)) as $index => $line) {
      if (preg_match('/^\s*-\s*[\'"]?' . preg_quote($value, '/') . '[\'"]?\s*(#.*)?$/', $line)) {
        return $index + 1;
      }
    }
    return 0;
  }

  /**
   * Analyzes the use of deprecated libraries by submodules and test modules.
   *
   * Upgrade Status only analyzes the topmost extension of a project. Its
   * submodules, subthemes and test modules are usually not all installed, and
   * sites do not list test modules unless they scan for tests. So this reads
   * their files directly. They often depend on libraries of extensions that are
   * not on the site, which are skipped without a message.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeSubExtensions(Extension $extension): array {
    $deprecations = [];
    foreach ($this->getSubExtensionInfoFiles(DRUPAL_ROOT . '/' . $extension->getPath()) as $info_file) {
      $library_file = dirname($info_file) . '/' . basename($info_file, '.info.yml') . '.libraries.yml';
      foreach ($this->readYamlFile($library_file) as $key => $library) {
        foreach ((is_array($library) && is_array($library['dependencies'] ?? NULL)) ? $library['dependencies'] : [] as $dependency) {
          $deprecations[] = $this->getSubExtensionLibraryMessage($dependency, "The '$key' library is depending on a deprecated library.", $library_file, $this->findLibraryLine($library_file, (string) $key));
        }
      }

      $info = $this->readYamlFile($info_file);
      if (($info['type'] ?? '') === 'theme') {
        foreach (['libraries-override' => 'overriding', 'libraries-extend' => 'extending'] as $info_key => $verb) {
          foreach (array_keys(is_array($info[$info_key] ?? NULL) ? $info[$info_key] : []) as $library) {
            $deprecations[] = $this->getSubExtensionLibraryMessage((string) $library, "Theme is $verb a deprecated library.", $info_file, 0);
          }
        }
        $deprecations = array_merge($deprecations, $this->analyzeMovedFileOverrides(is_array($info['libraries-override'] ?? NULL) ? $info['libraries-override'] : [], str_replace(DRUPAL_ROOT . '/', '', $info_file)));
        foreach (array_filter(is_array($info['libraries'] ?? NULL) ? $info['libraries'] : [], 'is_string') as $library) {
          $deprecations[] = $this->getSubExtensionLibraryMessage($library, 'Theme is adding a deprecated library to every page.', $info_file, $this->findListItemLine($info_file, $library));
        }
      }
    }
    return array_filter($deprecations);
  }

  /**
   * Creates the message for a submodule or test module that uses a library.
   *
   * @param string $library
   *   The library to check.
   * @param string $text
   *   The start of the message.
   * @param string $file
   *   The path of the file that uses the library.
   * @param int $line
   *   The line in the file.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage|null
   *   The message, or NULL if the library is not known to be deprecated.
   */
  private function getSubExtensionLibraryMessage(string $library, string $text, string $file, int $line): ?DeprecationMessage {
    $is_deprecated = $this->isLibraryDeprecated($library);
    if (empty($is_deprecated)) {
      return NULL;
    }
    $error_path = str_replace(DRUPAL_ROOT . '/', '', $file);
    if ($is_deprecated instanceof DeprecationMessage) {
      $is_deprecated->setFile($error_path);
      return $is_deprecated;
    }
    return new DeprecationMessage("$text $is_deprecated", $error_path, $line, 'LibraryDeprecationAnalyzer');
  }

  /**
   * Finds the info files of the extensions in the directories of a path.
   *
   * @param string $path
   *   The directory to look in. Its own info files are not returned.
   * @param bool $is_root
   *   Whether this is the directory of the extension being analyzed.
   *
   * @return list<string>
   *   A list of paths to the info files.
   */
  private function getSubExtensionInfoFiles(string $path, bool $is_root = TRUE): array {
    $files = [];
    if (!$is_root) {
      foreach ($this->fileFinder->getFiles($path, '.info.yml') as $file) {
        // There may be info.yml files in shipped configuration, which have more
        // parts in their names.
        if (substr_count(basename($file), '.') === 2) {
          $files[] = $file;
        }
      }
    }
    $ignore_directories = Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']);
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE)) {
        $files = array_merge($files, $this->getSubExtensionInfoFiles($dir, FALSE));
      }
    }
    return $files;
  }

  /**
   * Analyzes Twig library dependencies.
   *
   * At the moment we analyze only libraries attached using `library_attach()`.
   * However, there could be other ways to attache a library in a template, such
   * as generating and rendering a render array with `#attached`.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   Array of DeprecationMessage object for deprecated library found in Twig.
   */
  private function analyzeTwigLibraryDependencies(Extension $extension): array {
    $iterator = new TemplateDirIterator(
      new \ArrayIterator($this->fileFinder->findTemplates($extension->getPath()))
    );

    $deprecations = [];
    foreach ($iterator as $name => $contents) {
      try {
        $libraries = $this->findLibrariesAttachedInTemplate($this->twigEnvironment->parse($this->twigEnvironment->tokenize(new Source($contents, $name))));
        foreach ($libraries as $library) {
          $is_deprecated = $this->isLibraryDeprecated($library['library']);
          if (is_null($is_deprecated)) {
            $message = sprintf("The '%s' library is not defined because the defining extension is not installed. Whether the library is deprecated cannot be checked.", $library['library']);
            $deprecations[] = new DeprecationMessage($message, $name, $library['line'], 'LibraryDeprecationAnalyzer');
          }
          elseif (!empty($is_deprecated)) {
            if ($is_deprecated instanceof DeprecationMessage) {
              $is_deprecated->setFile($name);
              $deprecations[] = $is_deprecated;
            }
            else {
              $message = 'Template is attaching a deprecated library. ' . $is_deprecated;
              $deprecations[] = new DeprecationMessage($message, $name, $library['line'], 'LibraryDeprecationAnalyzer');
            }
          }
        }
      }
      catch (SyntaxError $e) {
        // Ignore templates containing syntax errors.
      }
    }

    return $deprecations;
  }

  /**
   * Finds libraries attached using `libraries_attach()` in a Twig template.
   *
   * @param \Twig\Node\Node $node
   *   The Twig AST node to analyze for attached libraries.
   *
   * @return string[]
   *   An array of libraries attached in the template, including
   *   the library name and line number.
   */
  private function findLibrariesAttachedInTemplate(Node $node): array {
    if (!is_iterable($node)) {
      return [];
    }

    $libraries = [];
    foreach ($node as $item) {
      if ($item instanceof FunctionExpression) {
        if ($item->getAttribute('name') === 'attach_library') {
          foreach ($item->getNode('arguments') as $argument) {
            if ($argument instanceof ConstantExpression && $argument->hasAttribute('value')) {
              $libraries[] = [
                'library' => $argument->getAttribute('value'),
                'line' => $item->getTemplateLine(),
              ];
            }
          }
        }
      }
      else {
        $libraries = array_merge($libraries, $this->findLibrariesAttachedInTemplate($item));
      }
    }

    return $libraries;
  }

  /**
   * Analyzes libraries referenced in PHP.
   *
   * This can only analyze statically attached libraries. We are not checking
   * the context where the library is being referenced, so in some cases this
   * could lead into false negatives. Testing the context would be possible, but
   * could lead into not detecting all references to deprecated libraries.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   An array of `DeprecationMessage` objects for deprecated
   *   libraries referenced in PHP files.
   */
  private function analyzePhpLibraryReferences(Extension $extension): array {
    // Directories like node_modules can have many files, and no code of the
    // project.
    $paths = $this->fileFinder->findFiles(
      DRUPAL_ROOT . '/' . $extension->getPath(),
      static fn (string $name): bool => (bool) preg_match('/\.(php|module|theme|profile|inc)$/', $name),
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );

    $deprecations = [];
    foreach ($paths as $path) {
      // The messages name the files by the paths from the Drupal root.
      $file = substr($path, strlen(DRUPAL_ROOT) + 1);
      if (str_contains($file, '/tests/fixtures/') && str_ends_with($file, '.php')) {
        continue;
      }
      try {
        $tokens = token_get_all(file_get_contents($path));
      }
      catch (\ParseError $error) {
        // Ignore syntax errors.
        continue;
      }

      // Find nodes that look like attaching a library.
      $potential_libraries = array_values(
        array_map(
          function ($token) {
            [, $value, $line] = $token;
            return [
              'value' => substr($value, 1, -1),
              'line' => $line,
            ];
          },
          array_filter($tokens, function ($token) {
            if (is_array($token)) {
              [$type, $value] = $token;
              return ($type === T_CONSTANT_ENCAPSED_STRING && preg_match('/^[\"\'][a-zA-Z0-9\.\-\_]+\/[a-zA-Z0-9\.\-\_]+[\"\']$/', $value));
            }
            return FALSE;
          })
        )
      );
      foreach ($potential_libraries as $potential_library) {
        [$extension_name] = explode('/', $potential_library['value'], 2);
        $extension_lists = [
          $this->moduleExtensionList,
          $this->themeExtensionList,
          $this->profileExtensionList,
        ];
        // Iterate through all extension lists to see if we have found a valid
        // extension. Core has libraries too, but it is not in these lists.
        $valid_extension = array_reduce($extension_lists, function ($valid_extension, ExtensionList $extension_list) use ($extension_name) {
          if ($valid_extension || $extension_list->exists($extension_name)) {
            return TRUE;
          }
          return FALSE;
        }, $extension_name === 'core');
        if ($valid_extension) {
          $is_deprecated = $this->isLibraryDeprecated($potential_library['value']);
          if (!empty($is_deprecated)) {
            if ($is_deprecated instanceof DeprecationMessage) {
              $is_deprecated->setFile($file);
              $deprecations[] = $is_deprecated;
            }
            else {
              $message = "The referenced library is deprecated. $is_deprecated";
              $deprecations[] = new DeprecationMessage($message, $file, $potential_library['line'], 'LibraryDeprecationAnalyzer');
            }
          }
        }
      }
    }

    return $deprecations;
  }

  /**
   * Finds libraries with scripts that use core globals but miss the dependency.
   *
   * A script that uses jQuery, Drupal, drupalSettings or once needs the
   * matching core library as a dependency. Without it, the script only works on
   * pages where another library happens to load that one. This became a bigger
   * problem in Drupal 12, which loads core/jquery and core/drupal on fewer
   * pages.
   *
   * This reads the libraries.yml files directly, so it also works for
   * extensions that are not installed, and for submodules.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeMissingCoreDependencies(Extension $extension): array {
    $deprecations = [];
    foreach ($this->getLibraryFiles(DRUPAL_ROOT . '/' . $extension->getPath()) as $file) {
      $name = basename($file, '.libraries.yml');
      $definitions = $this->readYamlFile($file);
      $this->libraryDefinitions[$name] = $definitions;
      foreach ($definitions as $library => $definition) {
        $code = is_array($definition) ? $this->getLibraryCode($definition, dirname($file)) : '';
        if ($code === '') {
          continue;
        }
        $dependencies = $this->getAllDependencies("$name/$library");
        // A dependency that cannot be read may provide what the script needs.
        if ($dependencies === NULL) {
          continue;
        }
        foreach (self::CORE_GLOBALS as $core_library => [$global, $pattern]) {
          if (!isset($dependencies[$core_library]) && preg_match($pattern, $code)) {
            $deprecations[] = new DeprecationMessage("The '$name/$library' library uses $global in JavaScript, but does not depend on $core_library. Add $core_library to the dependencies of the library. Without the dependency, the script can fail on pages where no other library loads $core_library.", str_replace(DRUPAL_ROOT . '/', '', $file), $this->findLibraryLine($file, (string) $library), 'LibraryDeprecationAnalyzer');
          }
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds scripts with a negative weight that use what core libraries provide.
   *
   * Core libraries have no weights from Drupal 11.5, so such a script loads
   * before them. Scripts that do not use core libraries are not affected.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeNegativeWeights(Extension $extension): array {
    $deprecations = [];
    foreach ($this->getLibraryFiles(DRUPAL_ROOT . '/' . $extension->getPath()) as $file) {
      $name = basename($file, '.libraries.yml');
      foreach ($this->readYamlFile($file) as $library => $definition) {
        $scripts = is_array($definition) && is_array($definition['js'] ?? NULL) ? $definition['js'] : [];
        foreach ($scripts as $path => $options) {
          if (($options['weight'] ?? 0) >= 0) {
            continue;
          }
          $code = $this->getScriptCode((string) $path, $options, dirname($file));
          $globals = [];
          foreach (self::CORE_GLOBALS as [$global, $pattern]) {
            if (preg_match($pattern, $code)) {
              $globals[] = $global;
            }
          }
          if (!empty($globals)) {
            $deprecations[] = new DeprecationMessage("The '$name/$library' library sets a negative weight for $path, which uses " . implode(', ', $globals) . ". Core libraries have no weights from drupal:11.5.0, so this script loads before the core libraries and can fail, or the changes of the script to Drupal can be lost. Remove the weight, and use the before or after keys of the library if the order still matters. See https://www.drupal.org/node/3467489.", str_replace(DRUPAL_ROOT . '/', '', $file), $this->findLibraryLine($file, (string) $library), 'LibraryDeprecationAnalyzer');
          }
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds all dependencies of a library, directly or through other libraries.
   *
   * @param string $id
   *   The library, like "core/drupal".
   * @param array<string, true> $seen
   *   The dependencies found so far.
   *
   * @return array<string, true>|null
   *   The dependencies as keys, or NULL if the extension of one of the
   *   libraries is not found.
   */
  private function getAllDependencies(string $id, array $seen = []): ?array {
    [$extension_name, $library] = array_pad(explode('/', $id, 2), 2, '');
    $definitions = $this->getLibraryDefinitions($extension_name);
    if ($definitions === NULL) {
      return NULL;
    }
    foreach ($definitions[$library]['dependencies'] ?? [] as $dependency) {
      if (!isset($seen[$dependency])) {
        $seen[$dependency] = TRUE;
        $seen = $this->getAllDependencies($dependency, $seen);
        if ($seen === NULL) {
          return NULL;
        }
      }
    }
    return $seen;
  }

  /**
   * Reads the library definitions of an extension from its libraries.yml file.
   *
   * @param string $extension_name
   *   The extension name, or "core".
   *
   * @return array|null
   *   The library definitions, or NULL if the extension is not found.
   */
  private function getLibraryDefinitions(string $extension_name): ?array {
    if (!array_key_exists($extension_name, $this->libraryDefinitions)) {
      $path = NULL;
      if ($extension_name === 'core') {
        $path = 'core/core';
      }
      foreach ([$this->moduleExtensionList, $this->themeExtensionList, $this->profileExtensionList] as $list) {
        if ($path === NULL && $list->exists($extension_name)) {
          $path = $list->getPath($extension_name) . '/' . $extension_name;
        }
      }
      $this->libraryDefinitions[$extension_name] = $path === NULL ? NULL : $this->readYamlFile(DRUPAL_ROOT . '/' . $path . '.libraries.yml');
    }
    return $this->libraryDefinitions[$extension_name];
  }

  /**
   * Reads the data from a YAML file.
   *
   * @param string $file
   *   The path of a libraries.yml or info.yml file.
   *
   * @return array
   *   The data, empty if the file cannot be read.
   */
  private function readYamlFile(string $file): array {
    try {
      $definitions = is_file($file) ? Yaml::decode(file_get_contents($file)) : [];
    }
    catch (InvalidDataTypeException) {
      return [];
    }
    return is_array($definitions) ? $definitions : [];
  }

  /**
   * Returns the code of a library's scripts, without comments and strings.
   *
   * External scripts are skipped, because their code is not available here.
   *
   * @param array $definition
   *   The library definition.
   * @param string $directory
   *   The directory of the libraries.yml file.
   *
   * @return string
   *   The code, or an empty string if there is none to check.
   */
  private function getLibraryCode(array $definition, string $directory): string {
    $code = '';
    foreach ($definition['js'] ?? [] as $path => $options) {
      $code .= $this->getScriptCode((string) $path, $options, $directory);
    }
    return $code;
  }

  /**
   * Returns the code of a script, without comments and strings.
   *
   * External scripts are skipped, because their code is not available here.
   *
   * @param string $path
   *   The path of the script in the library definition.
   * @param mixed $options
   *   The options of the script in the library definition.
   * @param string $directory
   *   The directory of the libraries.yml file.
   *
   * @return string
   *   The code, or an empty string if there is none to check.
   */
  private function getScriptCode(string $path, mixed $options, string $directory): string {
    $external = str_contains($path, '://') || str_starts_with($path, '//') || (($options['type'] ?? '') === 'external');
    $file = (str_starts_with($path, '/') ? DRUPAL_ROOT : $directory . '/') . $path;
    return !$external && is_file($file) ? $this->removeCommentsAndStrings(file_get_contents($file)) . "\n" : '';
  }

  /**
   * Removes comments and strings from JavaScript code.
   *
   * This keeps names in comments, and in strings like 'Drupal.foo', from being
   * reported. Regular expression literals are not recognized. A quote in one
   * makes the rest of the line count as a string, so code can be missed, but
   * nothing is reported that is not in the file.
   *
   * @param string $code
   *   The JavaScript code.
   *
   * @return string
   *   The code without comments and with empty strings.
   */
  private function removeCommentsAndStrings(string $code): string {
    return JavaScriptDeprecationAnalyzer::replaceCommentsAndStrings(
      $code,
      static fn (array $match): string => in_array($match[0][0], ['"', "'", '`'], TRUE) ? '""' : ' ',
    );
  }

  /**
   * Finds the line of a library in a libraries.yml file.
   *
   * @param string $file
   *   The path of the file.
   * @param string $library
   *   The library name.
   *
   * @return int
   *   The line number if found, 1 otherwise.
   */
  private function findLibraryLine(string $file, string $library): int {
    foreach (explode("\n", file_get_contents($file)) as $index => $line) {
      if (preg_match('/^[\'"]?' . preg_quote($library, '/') . '[\'"]?\s*:/', $line)) {
        return $index + 1;
      }
    }
    return 1;
  }

  /**
   * Finds all libraries.yml files under a path.
   *
   * @param string $path
   *   Base path to find the files in.
   *
   * @return list<string>
   *   A list of paths to the files found under the base path.
   */
  private function getLibraryFiles(string $path): array {
    $files = $this->fileFinder->getFiles($path, '.libraries.yml');
    $ignore_directories = Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']);
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE)) {
        $files = array_merge($files, $this->getLibraryFiles($dir));
      }
    }
    return $files;
  }

  /**
   * Tests if a library is deprecated.
   *
   * @param string $library
   *   A string representing the library to check (e.g., 'node/drupal.node').
   *
   * @return bool|string|null|\Drupal\upgrade_status\DeprecationMessage
   *   FALSE if the library is not deprecated or not defined, NULL if the
   *   library's extension is not found, or the text or a `DeprecationMessage`
   *   if the library is deprecated.
   */
  private function isLibraryDeprecated($library) {
    if (!strpos($library, '/')) {
      return new DeprecationMessage('The ' . $library . ' library does not have an extension name and is therefore not valid.', '', 0, 'LibraryDeprecationAnalyzer');
    }
    [$extension_name, $library_name] = explode('/', $library, 2);

    // Drupal\Core\Asset\LibraryDiscoveryParser::buildByExtension() assumes a
    // disabled module is a theme and fails not finding it then, so check if
    // the extension identified is an installed module or theme. The library
    // info will not be available otherwise.
    if ($extension_name != 'core') {
      $this->installedExtensions ??= array_merge(
        array_keys($this->moduleExtensionList->getAllInstalledInfo()),
        array_keys($this->themeExtensionList->getAllInstalledInfo()),
      );
      if (!in_array($extension_name, $this->installedExtensions)) {
        // The library info is not available, but the libraries.yml file may be.
        $definitions = $this->getLibraryDefinitions($extension_name);
        if ($definitions === NULL) {
          return NULL;
        }
        $deprecated = $definitions[$library_name]['deprecated'] ?? FALSE;
        return $deprecated ? str_replace('%library_id%', $library, $deprecated) : FALSE;
      }
    }

    if (!isset($this->builtLibraries[$extension_name])) {
      try {
        $this->builtLibraries[$extension_name] = $this->libraryDiscoveryParser->buildByExtension($extension_name);
      }
      catch (\Exception $e) {
        $this->builtLibraries[$extension_name] = $e;
      }
    }
    $dependency_libraries = $this->builtLibraries[$extension_name];
    if ($dependency_libraries instanceof \Exception) {
      return new DeprecationMessage($dependency_libraries->getMessage(), '', 0, 'LibraryDeprecationAnalyzer');
    }

    if (isset($dependency_libraries[$library_name]) && isset($dependency_libraries[$library_name]['deprecated'])) {
      return str_replace('%library_id%', "$extension_name/$library_name", $dependency_libraries[$library_name]['deprecated']);
    }

    return FALSE;
  }

}
