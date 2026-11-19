<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;

/**
 * The info.yml and composer deprecation analyzer.
 */
final class ExtensionMetadataDeprecationAnalyzer implements AnalyzerInterface {

  /**
   * Constructs a new extension metadata deprecation analyzer.
   *
   * @param \Drupal\upgrade_status\DeprecatedExtensionDescriber $deprecatedExtensionDescriber
   *   The deprecated extension describer.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly DeprecatedExtensionDescriber $deprecatedExtensionDescriber,
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Extension $extension): bool {
    return TRUE;
  }

  /**
   * Analyzes usages of deprecated extension metadata in an extension.
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
    $info_files = $this->getSubExtensionInfoFiles($project_dir);
    foreach ($info_files as $info_file) {
      try {

        // Manually add on info file incompatibility to results. Reading
        // .info.yml files directly, not from extension discovery because that
        // is cached.
        $file_contents = file_get_contents($info_file);
        $info = Yaml::decode($file_contents) ?: [];
        $error_path = str_replace(DRUPAL_ROOT . '/', '', $info_file);
        $deprecations = array_merge($deprecations, $this->analyzeDependencies($info, $project_dir, $error_path, $file_contents));
        if (!empty($info['package']) && $info['package'] == 'Testing' && !strpos($info_file, '/upgrade_status_test')) {
          // If this info file was for a testing project other than our own
          // testing projects, core does not need core_version_requirement in
          // it. But core does not install it if the value is not compatible,
          // so tests that use it fail.
          if (isset($info['core_version_requirement']) && !ProjectCollector::isCompatibleWithNextMajorDrupal($info['core_version_requirement'])) {
            $deprecations[] = $this->getCoreVersionRequirementMessage($info['core_version_requirement'], $error_path, $file_contents);
          }
          continue;
        }

        if (!isset($info['core_version_requirement'])) {
          $deprecations[] = new DeprecationMessage("Add core_version_requirement to designate which Drupal versions is the extension compatible with. See https://drupal.org/node/3070687.", $error_path, 1, 'ExtensionMetadataDeprecationAnalyzer');
        }
        elseif (!ProjectCollector::isCompatibleWithNextMajorDrupal($info['core_version_requirement'])) {
          $deprecations[] = $this->getCoreVersionRequirementMessage($info['core_version_requirement'], $error_path, $file_contents);
        }

        // @todo Change values to ExtensionLifecycle class constants
        // once at least Drupal 9.3 is required.
        if (!empty($info['lifecycle'])) {
          $line = $this->findKeyLine('lifecycle:', $file_contents);
          $link = !empty($info['lifecycle_link']) ? $info['lifecycle_link'] : 'https://www.drupal.org/node/3215042';
          if ($info['lifecycle'] == 'deprecated') {
            $deprecations[] = new DeprecationMessage("This extension is deprecated. Don't use it. See $link.", $error_path, $line, 'ExtensionMetadataDeprecationAnalyzer');
          }
          elseif ($info['lifecycle'] == 'obsolete') {
            $deprecations[] = new DeprecationMessage("This extension is obsolete. Obsolete extensions are usually uninstalled automatically when not needed anymore. You only need to do something about this if the uninstallation was unsuccessful. See $link.", $error_path, $line, 'ExtensionMetadataDeprecationAnalyzer');
          }
        }

      }
      catch (InvalidDataTypeException $e) {
        $deprecations[] = new DeprecationMessage('Parse error. ' . $e->getMessage(), $error_path, 1, 'ExtensionMetadataDeprecationAnalyzer');
      }

      // No need to check info files for PHP 8 compatibility information because
      // they can only define minimal PHP versions not maximum or excluded PHP
      // versions.
    }

    // Manually add on composer.json file incompatibility to results.
    if (file_exists($project_dir . '/composer.json')) {
      $error_path = $extension->getPath() . '/composer.json';
      $composer_json = json_decode(file_get_contents($project_dir . '/composer.json'));
      if (empty($composer_json) || !is_object($composer_json)) {
        $deprecations[] = new DeprecationMessage("Parse error in composer.json. Having a composer.json is not a requirement in general, but if there is one, it should be valid. See https://drupal.org/node/2514612.", $error_path, 1, 'ExtensionMetadataDeprecationAnalyzer');
      }
      elseif (!empty($composer_json->require->{'drupal/core'}) && !static::isValidConstraint($composer_json->require->{'drupal/core'})) {
        $deprecations[] = new DeprecationMessage("The drupal/core requirement in composer.json is not a valid version constraint. See https://www.drupal.org/docs/develop/using-composer/add-a-composerjson-file#core-compatibility.", $error_path, 1, 'ExtensionMetadataDeprecationAnalyzer');
      }
      elseif (!empty($composer_json->require->{'drupal/core'}) && !ProjectCollector::isCompatibleWithNextMajorDrupal($composer_json->require->{'drupal/core'})) {
        $deprecations[] = new DeprecationMessage("The drupal/core requirement is not compatible with the next major version of Drupal. Either remove it or update it to be compatible. See https://www.drupal.org/docs/develop/using-composer/add-a-composerjson-file#core-compatibility.", $error_path, 1, 'ExtensionMetadataDeprecationAnalyzer');
      }
      if (!empty($composer_json->require->{'php'}) && !static::isValidConstraint($composer_json->require->{'php'})) {
        $deprecations[] = new DeprecationMessage("The PHP requirement in composer.json is not a valid version constraint.", $error_path, 1, 'ExtensionMetadataDeprecationAnalyzer');
      }
      elseif (!empty($composer_json->require->{'php'}) && !ProjectCollector::isCompatibleWithPhp($composer_json->require->{'php'}, ProjectCollector::getNextMajorMinimumPhp())) {
        $php = preg_replace('!\.0$!', '', ProjectCollector::getNextMajorMinimumPhp());
        $deprecations[] = new DeprecationMessage("The PHP requirement ({$composer_json->require->{'php'}}) is not compatible with PHP $php, which the next major version of Drupal requires. Once the code works with PHP $php, update the requirement to allow it.", $error_path, 1, 'ExtensionMetadataDeprecationAnalyzer');
      }
      if (is_object($composer_json) && isset($composer_json->require) && is_object($composer_json->require)) {
        $deprecations = array_merge($deprecations, $this->analyzeSymfonyRequirements((array) $composer_json->require, $error_path, file_get_contents($project_dir . '/composer.json')));
        $deprecations = array_merge($deprecations, $this->analyzePackageRequirements((array) $composer_json->require, $project_dir, $error_path, file_get_contents($project_dir . '/composer.json')));
      }
    }
    return $deprecations;
  }

  /**
   * Analyzes the dependencies and the base theme of an extension.
   *
   * Dependencies on deprecated and obsolete modules and themes are reported,
   * unless they are in the same project.
   *
   * @param array $info
   *   The decoded info file.
   * @param string $project_dir
   *   The directory of the project.
   * @param string $error_path
   *   The path of the info file.
   * @param string $file_contents
   *   The contents of the info file.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeDependencies(array $info, string $project_dir, string $error_path, string $file_contents): array {
    $deprecations = [];
    $lines = explode("\n", $file_contents);
    $dependencies = is_array($info['dependencies'] ?? NULL) ? $info['dependencies'] : [];
    foreach (array_filter($dependencies, 'is_string') as $dependency) {
      // Dependencies are like "drupal:node", "node" or "drupal:node (>=10)".
      $name = trim(preg_replace('/\(.*$/', '', $dependency));
      $name = str_contains($name, ':') ? substr($name, strpos($name, ':') + 1) : $name;
      if ($description = $this->deprecatedExtensionDescriber->describe($name, $project_dir)) {
        $line = 1;
        foreach ($lines as $index => $text) {
          if (preg_match('/^\s*-\s*[\'"]?' . preg_quote($dependency, '/') . '[\'"]?\s*(#.*)?$/', $text)) {
            $line = $index + 1;
            break;
          }
        }
        $deprecations[] = new DeprecationMessage("This extension depends on $description", $error_path, $line, 'ExtensionMetadataDeprecationAnalyzer');
      }
    }
    if (is_string($info['base theme'] ?? NULL) && ($description = $this->deprecatedExtensionDescriber->describe($info['base theme'], $project_dir))) {
      $deprecations[] = new DeprecationMessage("The base theme is $description", $error_path, $this->findKeyLine('base theme:', $file_contents), 'ExtensionMetadataDeprecationAnalyzer');
    }
    return $deprecations;
  }

  /**
   * Analyzes the Composer requirements of drupal.org projects.
   *
   * A drupal/NAME package that provides a deprecated or obsolete extension
   * named NAME is reported, unless it is the project itself.
   *
   * @param array $requirements
   *   The requirements of the project, keyed by package name.
   * @param string $project_dir
   *   The directory of the project.
   * @param string $error_path
   *   The path of the composer.json file.
   * @param string $file_contents
   *   The contents of the composer.json file.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzePackageRequirements(array $requirements, string $project_dir, string $error_path, string $file_contents): array {
    $deprecations = [];
    foreach (array_keys($requirements) as $package) {
      if (!preg_match('@^drupal/([a-z0-9_]+)$@', (string) $package, $matches) || $matches[1] === 'core') {
        continue;
      }
      if ($description = $this->deprecatedExtensionDescriber->describe($matches[1], $project_dir)) {
        $deprecations[] = new DeprecationMessage("The $package package is required, and it provides $description", $error_path, $this->findKeyLine('"' . $package . '"', $file_contents), 'ExtensionMetadataDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Checks whether a value is a version constraint that Composer can parse.
   *
   * @param mixed $constraint
   *   The value of a requirement in a composer.json file.
   *
   * @return bool
   *   TRUE if the value is a valid version constraint.
   */
  protected static function isValidConstraint(mixed $constraint): bool {
    if (!is_string($constraint)) {
      return FALSE;
    }
    try {
      (new VersionParser())->parseConstraints($constraint);
      return TRUE;
    }
    catch (\UnexpectedValueException) {
      return FALSE;
    }
  }

  /**
   * Analyzes the requirements of Symfony packages that core also requires.
   *
   * The next major version of Drupal requires a new major version of these
   * Symfony packages, so a project that doesn't allow it can't be installed.
   * Other Symfony packages are not checked, because an older version may
   * still be installed with the next major version of Drupal.
   *
   * @param array $requirements
   *   The requirements of the project, keyed by package name.
   * @param string $error_path
   *   The path of the composer.json file.
   * @param string $file_contents
   *   The contents of the composer.json file.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  private function analyzeSymfonyRequirements(array $requirements, string $error_path, string $file_contents): array {
    $next = ProjectCollector::SYMFONY_MAJOR_VERSIONS[ProjectCollector::getDrupalCoreMajorVersion() + 1] ?? NULL;
    $core_composer_json = json_decode((string) @file_get_contents(DRUPAL_ROOT . '/core/composer.json'), TRUE);
    $core_requirements = $core_composer_json['require'] ?? [];
    if ($next === NULL) {
      return [];
    }
    $current = $next - 1;
    $version_parser = new VersionParser();
    $next_version = new Constraint('>=', $version_parser->normalize($next . '.0.0'));

    $deprecations = [];
    foreach ($requirements as $package => $constraint) {
      // Only check the packages that core requires in the current major
      // version of Symfony. Polyfills have versions of their own.
      if (!str_starts_with($package, 'symfony/') || !is_string($constraint) || !preg_match('/^\D*' . $current . '\./', $core_requirements[$package] ?? '')) {
        continue;
      }
      try {
        $compatible = $version_parser->parseConstraints($constraint)->matches($next_version);
      }
      catch (\UnexpectedValueException $e) {
        continue;
      }
      if (!$compatible) {
        $line = $this->findKeyLine('"' . $package . '"', $file_contents);
        $deprecations[] = new DeprecationMessage("The $package requirement ($constraint) is not compatible with Symfony $next, which the next major version of Drupal requires. Update it to allow Symfony $next.", $error_path, $line, 'ExtensionMetadataDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Creates the message for a core_version_requirement that is not compatible.
   *
   * @param string $requirement
   *   The value of core_version_requirement.
   * @param string $error_path
   *   The path of the info file.
   * @param string $file_contents
   *   The contents of the info file.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage
   *   The message.
   */
  private function getCoreVersionRequirementMessage(string $requirement, string $error_path, string $file_contents): DeprecationMessage {
    $line = $this->findKeyLine('core_version_requirement:', $file_contents);
    return new DeprecationMessage("Value of core_version_requirement: $requirement is not compatible with the next major version of Drupal core. See https://drupal.org/node/3070687.", $error_path, $line, 'ExtensionMetadataDeprecationAnalyzer');
  }

  /**
   * Finds all .info.yml files for extensions under a path.
   *
   * @param string $path
   *   Base path to find all info.yml files in.
   *
   * @return array
   *   A list of paths to .info.yml files found under the base path.
   */
  private function getSubExtensionInfoFiles(string $path) {
    $files = [];
    foreach ($this->fileFinder->getFiles($path, '.info.yml') as $file) {
      // Make sure the filename matches rules for an extension. There may be
      // info.yml files in shipped configuration which would have more parts.
      $parts = explode('.', basename($file));
      if (count($parts) == 3) {
        $files[] = $file;
      }
    }
    // Directories like node_modules can have many files, and no extensions
    // of the project. Drupal does not look for extensions there either.
    $ignore_directories = Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']);
    foreach ($this->fileFinder->getDirectories($path) as $dir) {
      if (!in_array(basename($dir), $ignore_directories, TRUE)) {
        $files = array_merge($files, $this->getSubExtensionInfoFiles($dir));
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
