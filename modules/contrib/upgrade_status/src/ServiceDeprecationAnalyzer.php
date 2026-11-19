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
 * The service deprecation analyzer.
 *
 * Services and service aliases are deprecated with a deprecated key in their
 * definition. The container only reports this when the service is created,
 * and PHPStan only finds services that PHP code gets from the container. So a
 * services file that passes a deprecated service as an argument, extends it
 * or decorates it is not reported, and breaks when the service is removed.
 */
final class ServiceDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * Deprecated services, keyed by service ID.
   *
   * Each value has the deprecation message and the file that defines the
   * service.
   *
   * @var array<string, array{string, string}>|null
   */
  private ?array $deprecatedServices = NULL;

  /**
   * Constructs a new service deprecation analyzer.
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
   * Analyzes uses of deprecated services in the services files of a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array {
    $deprecated_services = $this->getDeprecatedServices();
    $deprecations = [];
    foreach ($this->getServicesFiles(DRUPAL_ROOT . '/' . $extension->getPath()) as $file) {
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $file);
      $lines = explode("\n", file_get_contents($file));
      foreach ($deprecated_services as $id => [$message, $definition_file]) {
        // The file that deprecates the service still defines it.
        if ($definition_file === $file) {
          continue;
        }
        foreach ($lines as $index => $line) {
          if (!preg_match('/^\s*#/', $line) && $this->isReferenced($id, $line)) {
            $deprecations[] = new DeprecationMessage($message, $error_path, $index + 1, 'ServiceDeprecationAnalyzer');
          }
        }
      }
    }
    return $deprecations;
  }

  /**
   * Checks whether a line of a services file refers to a service.
   *
   * @param string $id
   *   The service ID.
   * @param string $line
   *   The line.
   *
   * @return bool
   *   TRUE if the line refers to the service as an argument, a parent, an
   *   alias, a decorated service or a factory. Optional references, like
   *   '@?service', are not counted, because they work without the service.
   */
  private function isReferenced(string $id, string $line): bool {
    $quoted = preg_quote($id, '/');
    return preg_match('/(?<!@)@!?' . $quoted . '(?![\w.\\\\])/', $line)
      || preg_match('/^\s*(?:parent|alias|decorates)\s*:\s*[\'"]?' . $quoted . '[\'"]?\s*(?:#.*)?$/', $line)
      || preg_match('/^\s*factory\s*:\s*[\'"]?' . $quoted . ':/', $line);
  }

  /**
   * Returns the deprecated services of core and all modules and profiles.
   *
   * @return array<string, array{string, string}>
   *   The deprecation message and the file of each deprecated service, keyed
   *   by service ID.
   */
  private function getDeprecatedServices(): array {
    if ($this->deprecatedServices !== NULL) {
      return $this->deprecatedServices;
    }
    $this->deprecatedServices = [];
    $files = [DRUPAL_ROOT . '/core/core.services.yml'];
    foreach ($this->moduleExtensionList->getList() + $this->profileExtensionList->getList() as $name => $extension) {
      $files[] = DRUPAL_ROOT . '/' . $extension->getPath() . '/' . $name . '.services.yml';
    }
    foreach (array_filter($files, 'file_exists') as $file) {
      // Most services files have no deprecated services, and decoding takes
      // much longer than this check.
      $contents = file_get_contents($file);
      if (!str_contains($contents, 'deprecated')) {
        continue;
      }
      try {
        $services = Yaml::decode($contents)['services'] ?? [];
      }
      catch (InvalidDataTypeException $e) {
        continue;
      }
      foreach (is_array($services) ? $services : [] as $id => $definition) {
        $deprecation = is_array($definition) ? $definition['deprecated'] ?? NULL : NULL;
        // Symfony also allows an array with the message in it.
        if (is_array($deprecation)) {
          $deprecation = $deprecation['message'] ?? 'The "%service_id%" service is deprecated.';
        }
        if (is_string($deprecation)) {
          $this->deprecatedServices[(string) $id] = [$this->getMessage((string) $id, $deprecation), $file];
        }
      }
    }
    return $this->deprecatedServices;
  }

  /**
   * Creates the message for a use of a deprecated service.
   *
   * @param string $id
   *   The service ID.
   * @param string $deprecation
   *   The deprecation message of the service definition.
   *
   * @return string
   *   The message to report.
   */
  private function getMessage(string $id, string $deprecation): string {
    $deprecation = trim(str_replace(['%service_id%', '%alias_id%'], $id, $deprecation));
    // Keep the version information in the format that categorizes messages.
    if (preg_match('/\bis deprecated ((?:in|as of) drupal:.*)$/is', $deprecation, $matches)) {
      $deprecation = 'Deprecated ' . $matches[1];
    }
    return "The $id service is used in a services file. $deprecation";
  }

  /**
   * Finds the services files in a directory.
   *
   * Test fixtures and code of other projects are skipped.
   *
   * @param string $path
   *   The directory to search.
   *
   * @return string[]
   *   The paths of the services files.
   */
  private function getServicesFiles(string $path): array {
    $files = $this->fileFinder->getFiles($path, '.services.yml');
    $ignore_directories = array_merge(
      ['config', 'dist', 'libraries', 'node_modules', 'vendor'],
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    foreach ($this->fileFinder->getDirectories($path, TRUE) as $directory) {
      if (!in_array(basename($directory), $ignore_directories, TRUE) && !preg_match('~/tests/fixtures$~', $directory)) {
        $files = array_merge($files, $this->getServicesFiles($directory));
      }
    }
    return $files;
  }

}
