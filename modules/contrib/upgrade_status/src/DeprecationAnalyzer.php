<?php

namespace Drupal\upgrade_status;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use DrupalFinder\DrupalFinderComposerRuntime;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Deprecation Analyzer.
 */
final class DeprecationAnalyzer {

  /**
   * Upgrade status scan result storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $scanResultStorage;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Path to the PHPStan neon configuration.
   *
   * @var string
   */
  protected $phpstanNeonPath;

  /**
   * Path to the vendor directory.
   *
   * @var string
   */
  protected $vendorPath;

  /**
   * Path to the binaries.
   *
   * @var string
   */
  protected $binPath;

  /**
   * Path to the PHP binary.
   *
   * @var string
   */
  protected $phpPath;

  /**
   * Temporary directory to use for running phpstan.
   *
   * @var string
   */
  protected $temporaryDirectory;

  /**
   * HTTP Client for drupal.org API calls.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The Twig deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\TwigDeprecationAnalyzer
   */
  protected $twigDeprecationAnalyzer;

  /**
   * The library deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\LibraryDeprecationAnalyzer
   */
  protected $libraryDeprecationAnalyzer;

  /**
   * The route deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\RouteDeprecationAnalyzer
   */
  protected $routeDeprecationAnalyzer;

  /**
   * The extension metadata deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\ExtensionMetadataDeprecationAnalyzer
   */
  protected $extensionMetadataDeprecationAnalyzer;

  /**
   * The config schema deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\ConfigSchemaDeprecationAnalyzer
   */
  protected $configSchemaDeprecationAnalyzer;

  /**
   * The CSS deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\CSSDeprecationAnalyzer
   */
  protected $cssDeprecationAnalyzer;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Drupal project finder.
   *
   * @var \DrupalFinder\DrupalFinderComposerRuntime
   */
  protected $finder;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Whether the analyzer environment is initialized.
   *
   * @var bool
   */
  protected $environmentInitialized = FALSE;

  /**
   * Constructs a deprecation analyzer.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \GuzzleHttp\Client $http_client
   *   HTTP client.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File system service.
   * @param \Drupal\upgrade_status\TwigDeprecationAnalyzer $twig_deprecation_analyzer
   *   The Twig deprecation analyzer.
   * @param \Drupal\upgrade_status\LibraryDeprecationAnalyzer $library_deprecation_analyzer
   *   The library deprecation analyzer.
   * @param \Drupal\upgrade_status\RouteDeprecationAnalyzer $route_deprecation_analyzer
   *   The route deprecation analyzer.
   * @param \Drupal\upgrade_status\ExtensionMetadataDeprecationAnalyzer $extension_metadata_analyzer
   *   The extension metadata analyzer.
   * @param \Drupal\upgrade_status\ConfigSchemaDeprecationAnalyzer $config_schema_analyzer
   *   The config schema analyzer.
   * @param \Drupal\upgrade_status\CSSDeprecationAnalyzer $css_deprecation_analyzer
   *   The CSS deprecation analyzer.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(
    KeyValueFactoryInterface $key_value_factory,
    LoggerInterface $logger,
    Client $http_client,
    FileSystemInterface $file_system,
    TwigDeprecationAnalyzer $twig_deprecation_analyzer,
    LibraryDeprecationAnalyzer $library_deprecation_analyzer,
    RouteDeprecationAnalyzer $route_deprecation_analyzer,
    ExtensionMetadataDeprecationAnalyzer $extension_metadata_analyzer,
    ConfigSchemaDeprecationAnalyzer $config_schema_analyzer,
    CSSDeprecationAnalyzer $css_deprecation_analyzer,
    TimeInterface $time,
    ModuleExtensionList $module_extension_list,
  ) {
    $this->scanResultStorage = $key_value_factory->get('upgrade_status_scan_results');
    $this->logger = $logger;
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->twigDeprecationAnalyzer = $twig_deprecation_analyzer;
    $this->libraryDeprecationAnalyzer = $library_deprecation_analyzer;
    $this->routeDeprecationAnalyzer = $route_deprecation_analyzer;
    $this->extensionMetadataDeprecationAnalyzer = $extension_metadata_analyzer;
    $this->configSchemaDeprecationAnalyzer = $config_schema_analyzer;
    $this->cssDeprecationAnalyzer = $css_deprecation_analyzer;
    $this->time = $time;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * Initialize the external environment.
   *
   * @throws \Exception
   *   In case initialization failed. The analyzer will not work in this case.
   */
  public function initEnvironment() {
    if (!empty($this->environmentInitialized)) {
      // Already successfully initialized, no need to do it again.
      return;
    }

    $this->phpPath = $this->findPhpPath();

    $this->finder = new DrupalFinderComposerRuntime();

    // If a Drupal project is built with Composer scaffolding, the "name"
    // property in composer.json MUST NOT be "drupal/drupal". If it is, the
    // webflo/drupal-finder package will assume we are NOT in a Composer
    // scaffolded project and assume Drupal core is in the root directory.
    // @see https://www.drupal.org/project/upgrade_status/issues/3229725
    if (!is_dir($this->finder->getDrupalRoot() . '/core')) {
      $composer_json_path = dirname(DRUPAL_ROOT) . '/composer.json';
      if (!file_exists($composer_json_path)) {
        throw new \Exception('Could not find the composer.json file for your Drupal site, assumed: ' . $composer_json_path);
      }
      $composer_data = \json_decode(file_get_contents($composer_json_path), TRUE);
      if ($composer_data['name'] === 'drupal/drupal') {
        throw new \Exception('Change the "name" property in ' . $composer_json_path . ' from "drupal/drupal" to a custom value.');
      }
      else {
        throw new \Exception('Could not detect the location of "drupal/core", please open an issue at https://www.drupal.org/project/issues/upgrade_status.');
      }
    }

    $this->vendorPath = $this->finder->getVendorDir();
    $this->binPath = $this->findBinPath();

    $system_temporary = $this->fileSystem->getTempDirectory();
    $this->temporaryDirectory = $system_temporary . '/upgrade_status';
    if (!file_exists($this->temporaryDirectory)) {
      $this->prepareTempDirectory();
    }

    $this->phpstanNeonPath = $this->temporaryDirectory . '/deprecation_testing.neon';
    $this->createModifiedNeonFile();

    $this->environmentInitialized = TRUE;
  }

  /**
   * Finds bin-dir location.
   *
   * This can be set in composer.json via `bin-dir` config and may not be
   * inside the vendor directory. The logic somewhat duplicates the old
   * DrupalFinder's vendor directory detection for best developer guidance
   * in case of errors.
   *
   * @return string
   *   Bin directory path if found.
   *
   * @throws \Exception
   */
  protected function findBinPath() {
    $composer_name = trim(getenv('COMPOSER')) ?: 'composer.json';
    $composer_json_path = $this->finder->getComposerRoot() . '/' . $composer_name;
    if ($composer_json_path && file_exists($composer_json_path)) {
      $json = json_decode(file_get_contents($composer_json_path), TRUE);
      if (is_null($json) || !is_array($json)) {
        throw new \Exception('Unable to decode composer information from ' . $composer_json_path . '.');
      }
    }
    else {
      throw new \Exception('The composer.json file was not found at ' . $composer_json_path . '.');
    }

    // If a bin-dir is specified, that is most specific.
    if (isset($json['config']['bin-dir'])) {
      $binPath = $this->finder->getComposerRoot() . '/' . rtrim($json['config']['bin-dir'], '/');
      if (file_exists($binPath . '/phpstan')) {
        return $binPath;
      }
      else {
        throw new \Exception('The PHPStan binary was not found in the bin-dir specified by ' . $composer_json_path . '. Attempted: ' . $binPath . '/phpstan.');
      }
    }

    // If a vendor-dir is specified, that is slightly less specific.
    if (isset($json['config']['vendor-dir'])) {
      $binPath = $this->finder->getComposerRoot() . '/' . rtrim($json['config']['vendor-dir'], '/') . '/bin';
      if (file_exists($binPath . '/phpstan')) {
        return $binPath;
      }
      else {
        throw new \Exception('The PHPStan binary was not found in the vendor-dir specified by ' . $composer_json_path . '. Attempted: ' . $binPath . '/phpstan.');
      }
    }

    // Try the assumed default vendor directory as a last resort.
    $binPath = $this->finder->getComposerRoot() . '/vendor/bin';
    if (file_exists($binPath . '/phpstan')) {
      return $binPath;
    }

    throw new \Exception('The PHPStan binary was not found in the default vendor directory based on the location of ' . $composer_json_path . '. You may need to configure a vendor-dir in composer.json. See https://getcomposer.org/doc/06-config.md#vendor-dir. Attempted: ' . $binPath . '/phpstan.');
  }

  /**
   * Finds the PHP path.
   *
   * This ensures we execute PHPStan with the same PHP binary that is used by
   * the web server.
   *
   * @return string
   *   PHP path if found.
   *
   * @throws \Exception
   */
  protected function findPhpPath() {
    $finder = new PhpExecutableFinder();
    $binary = $finder->find();
    if ($binary === FALSE) {
      throw new \Exception('The PHP binary was not found.');
    }
    return $binary;
  }

  /**
   * Analyze the codebase of an extension including all its sub-components.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to analyze.
   * @param array $options
   *   Options for the analysis. Only the phpstan-memory-limit key is used
   *   with a default value of 1500M.
   *
   * @return null
   *   Errors are logged to the logger, data is stored to keyvalue storage.
   */
  public function analyze(Extension $extension, array $options = []) {
    try {
      $this->initEnvironment();
    }
    catch (\Exception $e) {
      // Should not get here as integrations are expected to invoke
      // initEnvironment() first by itself to ensure the environment
      // is going to work when needed (and inform users about any
      // issues). That said, if they did not do that and there was
      // no issue with the environment, then they are lucky.
      return;
    }

    $project_dir = DRUPAL_ROOT . '/' . $extension->getPath();
    $this->logger->notice('Processing %path.', ['%path' => $project_dir]);

    $memory_limit = $options['phpstan-memory-limit'] ?? '1500M';
    $command = [
      $this->phpPath,
      $this->binPath . '/phpstan',
      'analyse',
      '--memory-limit=' . $memory_limit,
      '--error-format=json',
      '--configuration=' . $this->phpstanNeonPath,
      $project_dir,
    ];

    $process = new Process($command, DRUPAL_ROOT, NULL, NULL, NULL);
    $process->run();

    // If there was an error about lack of files, that is fine for us, an
    // extension does not necessarily need PHP files. Use a standard
    // empty resultset for this case.
    $stderr = trim($process->getErrorOutput()) ?: 'Empty.';
    if (strpos($stderr, 'No files found to analyse.') !== FALSE) {
      $json = [
        'files' => [],
        'errors' => [],
        'totals' => [
          'errors' => 0,
          'file_errors' => 0,
        ],
      ];
    }
    else {
      $json = json_decode($process->getOutput(), TRUE);
    }

    // If there was a JSON parsing error, that may be a fatal that
    // PHPStan did not catch, so report the raw output as error.
    if (json_last_error() !== JSON_ERROR_NONE) {
      $stdout = trim($process->getOutput()) ?: 'Empty.';
      $json = [
        'files' => [],
        'errors' => [],
        'totals' => [
          'errors' => 0,
          'file_errors' => 0,
        ],
      ];
      $formatted_error =
        "<h6>PHPStan command failed:</h6> <p>" . implode(" ", $command) .
        "</p> <h6>Command output:</h6> <p>" . $stdout .
        "</p> <h6>Command error:</h6> <p>" . $stderr . '</p>';
      $this->logger->error('%phpstan_fail', ['%phpstan_fail' => strip_tags($formatted_error)]);
      // Add a failure message with the nonexistent 'PHPStan failed'
      // filename, so the error conforms to the expected format.
      $json['files']['PHPStan failed'] = [
        'messages' => [
          [
            'message' => $formatted_error,
            'line' => 0,
          ],
        ],
      ];
      $json['totals']['errors']++;
      $json['totals']['file_errors']++;
    }

    // Convert "non-file" errors to file errors.
    foreach ($json['errors'] as $error) {
      if (preg_match('!^(.+) on line (\d+) while analysing file (.+)$!', $error, $parts)) {
        $json['totals']['file_errors']++;
        @$json['files'][$parts[3]]['messages'][] = [
          'message' => $parts[1],
          'line' => $parts[2],
        ];
      }
    }

    // Add analyzer info.
    foreach ($json['files'] as &$errors) {
      foreach ($errors['messages'] as &$error) {
        $error['analyzer'] = 'PHPStan';
      }
    }
    $result = [
      'date' => $this->time->getRequestTime(),
      'data' => $json,
    ];

    $metadataDeprecations = $this->extensionMetadataDeprecationAnalyzer->analyze($extension);
    $result['data']['totals']['upgrade_status_split']['declared_ready'] = empty($metadataDeprecations);

    // Run further deprecation analyzers and collect results.
    $more_deprecations = array_merge(
      $this->twigDeprecationAnalyzer->analyze($extension),
      $this->libraryDeprecationAnalyzer->analyze($extension),
      $this->routeDeprecationAnalyzer->analyze($extension),
      $this->cssDeprecationAnalyzer->analyze($extension),
      $this->configSchemaDeprecationAnalyzer->analyze($extension),
      $metadataDeprecations,
    );

    foreach ($more_deprecations as $one_deprecation) {
      $result['data']['files'][$one_deprecation->getFile()]['messages'][] = [
        'message' => $one_deprecation->getMessage(),
        'line' => $one_deprecation->getLine(),
        'analyzer' => $one_deprecation->getAnalyzer(),
      ];
      $result['data']['totals']['errors']++;
      $result['data']['totals']['file_errors']++;
    }

    // Assume next step is to relax (there were no errors found).
    $result['data']['totals']['upgrade_status_next'] = ProjectCollector::NEXT_RELAX;

    foreach ($result['data']['files'] as &$errors) {
      foreach ($errors['messages'] as &$error) {

        // Overwrite message with processed text. Save category.
        [$message, $category] = $this->categorizeMessage($error['message'], $extension);
        $error['message'] = $message;
        $error['upgrade_status_category'] = $category;

        // If the category was 'rector' that means at least one error was
        // identified as covered by rector, so next step should be to run
        // rector on this project.
        if ($category == 'rector') {
          $result['data']['totals']['upgrade_status_next'] = ProjectCollector::NEXT_RECTOR;
        }
        // If the category was not rector, if the next step is still to
        // relax, modify that to fix manually.
        elseif ($result['data']['totals']['upgrade_status_next'] == ProjectCollector::NEXT_RELAX) {
          $result['data']['totals']['upgrade_status_next'] = ProjectCollector::NEXT_MANUAL;
        }

        // Sum up the error based on the category it ended up in. Split the
        // categories into two high level buckets needing attention now or
        // later for compatibility with the next major version. Issues in the
        // 'ignore' category are intentionally not counted in either.
        @$result['data']['totals']['upgrade_status_category'][$category]++;
        if (in_array($category, ['safe', 'old', 'rector'])) {
          @$result['data']['totals']['upgrade_status_split']['error']++;
        }
        elseif (in_array($category, ['later', 'uncategorized'])) {
          @$result['data']['totals']['upgrade_status_split']['warning']++;
        }
      }
    }

    // Store the analysis results in our storage bin.
    $this->scanResultStorage->set($extension->getName(), $result);
  }

  /**
   * Prepare temporary directories for Upgrade Status.
   *
   * The created directories in Drupal's temporary directory are needed to
   * dynamically set a temporary directory for PHPStan's cache in the neon file
   * provided by Upgrade Status.
   *
   * @throws \Exception
   *   If creating the temporary directory failed.
   */
  protected function prepareTempDirectory() {
    $success = $this->fileSystem->prepareDirectory($this->temporaryDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if (!$success) {
      throw new \Exception('Unable to create temporary directory for Upgrade Status at ' . $this->temporaryDirectory);
    }

    $phpstan_cache_directory = $this->temporaryDirectory . '/phpstan';
    $success = $this->fileSystem->prepareDirectory($phpstan_cache_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if (!$success) {
      throw new \Exception('Unable to create temporary directory for PHPStan at ' . $phpstan_cache_directory);
    }
  }

  /**
   * Creates the final config file in the temporary directory.
   *
   * @throws \Exception
   *   If the PHPStan configuration file cannot be written.
   */
  protected function createModifiedNeonFile() {
    $module_path = DRUPAL_ROOT . '/' . $this->moduleExtensionList->getPath('upgrade_status');
    $config = file_get_contents($module_path . '/deprecation_testing_template.neon');
    $config = str_replace(
      'parameters:',
      "parameters:\n\ttmpDir: '" . $this->temporaryDirectory . '/phpstan' . "'",
      $config
    );

    if (!class_exists('PHPStan\ExtensionInstaller\GeneratedConfig')) {
      $extension_neon = $this->vendorPath . '/mglaman/phpstan-drupal/extension.neon';
      $rules_neon = $this->vendorPath . '/phpstan/phpstan-deprecation-rules/rules.neon';
      if (!file_exists($extension_neon) || !file_exists($rules_neon)) {
        throw new \Exception('Vendor source files were not found. You may need to configure a vendor-dir in composer.json. See https://getcomposer.org/doc/06-config.md#vendor-dir. Missing ' . $extension_neon . ' and ' . $rules_neon . '.');
      }
      $config .= "\nincludes:\n\t- '" . $extension_neon . "'\n\t- '" . $rules_neon . "'\n";

      // phpstan-drupal 1.1.16 introduced a new rules.neon file, include it if
      // it exists. phpstan-drupal 1.1.4 and earlier are the only versions that
      // still support PHP 7.3 and earlier, and this file does not exist there.
      $drupal_rules_neon = $this->vendorPath . '/mglaman/phpstan-drupal/rules.neon';
      if (file_exists($drupal_rules_neon)) {
        $config .= "\t- '" . $drupal_rules_neon . "'\n";
      }
    }

    $success = file_put_contents($this->phpstanNeonPath, $config);

    if (!$success) {
      throw new \Exception('Unable to write configuration for PHPStan to ' . $this->phpstanNeonPath . '.');
    }
  }

  /**
   * Annotate and categorize the error message.
   *
   * @param string $error
   *   Error message as identified by phpstan.
   * @param \Drupal\Core\Extension\Extension $extension
   *   Extension where the error was found.
   *
   * @return array
   *   Two item array. The reformatted error and the category.
   */
  protected function categorizeMessage(string $error, Extension $extension) {
    // Make the error more readable in case it has the deprecation text.
    $error = preg_replace('!\s+!', ' ', trim($error));
    $error = preg_replace('!:\s+(in|as of)!', '. Deprecated \1', $error);
    $error = preg_replace('!(u|U)se \\\\Drupal!', '\1se Drupal', $error);

    // TestBase and WebTestBase replacements are available at least from Drupal
    // 8.6.0, so use that version number. Otherwise use the number from the
    // message.
    $version = '';
    if (preg_match('!\\\\(Web|)TestBase. Deprecated in [Dd]rupal[ :]8\.8\.0 !', $error)) {
      $version = '8.6.0';
      $error .= " Replacement available from drupal:8.6.0.";
    }
    elseif (preg_match('!Deprecated (in|as of) [Dd]rupal[ :](\d+\.\d)!', $error, $version_found)) {
      $version = $version_found[2];
    }

    // Set a default category for the messages we can't categorize.
    $category = 'uncategorized';

    if (!empty($version)) {

      // Categorize deprecations for contributed projects based on
      // community rules.
      if (!empty($extension->info['project'])) {
        // If the found deprecation is older or equal to the oldest
        // supported core version, it should be old enough to update
        // either way.
        if (version_compare($version, ProjectCollector::getOldestSupportedMinor()) <= 0) {
          $category = 'old';
        }
        // If the deprecation is not old and we are dealing with a contrib
        // module, the deprecation should be dealt with later.
        else {
          $category = 'later';
        }
      }
      // For custom projects, look at this site's version specifically.
      else {
        // If the found deprecation is older or equal to the current
        // Drupal version on this site, it should be safe to update.
        if (version_compare($version, \Drupal::VERSION) <= 0) {
          $category = 'safe';
        }
        else {
          $category = 'later';
        }
      }
    }

    // If the error is covered by rector, override the result.
    if ($this->isRectorCovered($error)) {
      $category = 'rector';
    }

    // Ignore the broken messages for EntityStorageInterface deprecation.
    if (strpos($error, 'of interface Drupal\Core\Entity\EntityStorageInterface. Deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use Drupal\Core\Entity\RevisionableStorageInterface') !== FALSE) {
      $category = 'ignore';
    }

    // If the deprecation is already for after the next Drupal major,
    // put it in the ignore category.
    // This overwrites any categorization before intentionally.
    if (preg_match('!(will be|is) removed (before|from) [Dd]rupal[ :](\d+)\.!', $error, $version_removed)) {
      if ($version_removed[3] > ProjectCollector::getDrupalCoreMajorVersion() + 1) {
        $category = 'ignore';
      }
    }

    // Check for "guzzlehttp/guzzle:8.0" and ignore those errors.
    // That major is not
    // released yet, so compatibility cannot be proven.
    // Stop ignoring this error from
    // Drupal 11 as a safeguard.
    if (strpos($error, 'guzzlehttp/guzzle:8.0') !== FALSE && ProjectCollector::getDrupalCoreMajorVersion() < 11) {
      $category = 'ignore';
    }

    // Ignore twig 3.12 and 3.15 false positives (until core fixes them).
    foreach ([3.12, 3.15] as $version) {
      if (strpos($error, 'Since twig/twig ' . $version) !== FALSE && ProjectCollector::getDrupalCoreMajorVersion() < 11) {
        $category = 'ignore';
      }
    }

    return [$error, $category];
  }

  /**
   * Checks whether an error message is covered by rector.
   *
   * @return bool
   *   Returns bool value.
   */
  protected function isRectorCovered($string) {
    // Hardcoded lo-fi implementation for now. This should be the same as in
    // https://git.drupalcode.org/project/deprecation_status/-/blob/script/stats.php
    $rector_covered = [
      // 0.3.3
      'Call to deprecated function drupal_set_message(). Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\Core\Messenger\MessengerInterface::addMessage() instead.',
      'Call to deprecated method entityManager() of class Drupal. Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal::entityTypeManager() instead in most cases. If the needed method is not on \Drupal\Core\Entity\EntityTypeManagerInterface, see the deprecated \Drupal\Core\Entity\EntityManager to find the correct interface or service.',
      'Call to deprecated method entityManager() of class Drupal\Core\Controller\ControllerBase. Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Most of the time static::entityTypeManager() is supposed to be used instead.',
      'Call to deprecated function db_insert(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Instead, get a database connection injected into your service from the container and call insert() on it. For example,',
      'Call to deprecated function db_select(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Instead, get a database connection injected into your service from the container and call select() on it. For example,',
      'Call to deprecated function db_query(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Instead, get a database connection injected into your service from the container and call query() on it. For example,',
      'Call to deprecated function file_prepare_directory(). Deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::prepareDirectory().',
      'Call to deprecated method getMock() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\Tests\PhpunitCompatibilityTrait::createMock() instead.',
      'Call to deprecated method getMock() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\Tests\PhpunitCompatibilityTrait::createMock() instead.',
      'Call to deprecated method getMock() of class Drupal\Tests\UnitTestCase. Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\Tests\PhpunitCompatibilityTrait::createMock() instead.',
      'Call to deprecated method url() of class Drupal. Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Instead create a \Drupal\Core\Url object directly, for example using Url::fromRoute().',

      // 0.4.0
      'Call to deprecated function format_date(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal::service(\'date.formatter\')->format().',
      'Call to deprecated method strtolower() of class Drupal\Component\Utility\Unicode. Deprecated in drupal:8.6.0 and is removed from drupal:9.0.0. Use mb_strtolower() instead.',
      'Call to deprecated constant FILE_CREATE_DIRECTORY: Deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY.',
      'Call to deprecated constant FILE_EXISTS_REPLACE: Deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE.',
      'Call to deprecated method l() of class Drupal. Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\Core\Link::fromTextAndUrl() instead.',
      'Call to deprecated function drupal_render(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use the',
      'Call to deprecated function drupal_render_root(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\Core\Render\RendererInterface::renderRoot() instead.',

      // 0.5.0
      'Call to deprecated function file_unmanaged_save_data(). Deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::saveData().',

      // 0.5.1
      'Call to deprecated constant FILE_MODIFY_PERMISSIONS: Deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS.',
      'Call to deprecated function db_delete(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Instead, get a database connection injected into your service from the container and call delete() on it. For example,',

      // 0.5.2
      'Call to deprecated function entity_get_form_display(). Deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use EntityDisplayRepositoryInterface::getFormDisplay() instead.',
      'Call to deprecated function entity_get_display(). Deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use EntityDisplayRepositoryInterface::getViewDisplay() instead.',
      'Call to deprecated constant REQUEST_TIME: Deprecated in drupal:8.3.0 and is removed from drupal:11.0.0. Use Drupal::time()->getRequestTime();',
      'Call to deprecated method urlInfo() of class Drupal\Core\Entity\EntityInterface. Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\Core\Entity\EntityInterface::toUrl() instead.',
      'Call to deprecated function file_scan_directory(). Deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::scanDirectory() instead.',
      'Call to deprecated function file_default_scheme(). Deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use Drupal::config(\'system.file\')->get(\'default_scheme\') instead.',
      'Call to deprecated function db_update(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Instead, get a database connection injected into your service from the container and call update() on it. For example,',

      // 0.5.3
      'Call to deprecated method strtolower() of class Drupal\Component\Utility\Unicode. Deprecated in drupal:8.6.0 and is removed from drupal:9.0.0. Use mb_strtolower() instead.',
      'Call to deprecated method strlen() of class Drupal\Component\Utility\Unicode. Deprecated in drupal:8.6.0 and is removed from drupal:9.0.0. Use mb_strlen() instead.',
      'Call to deprecated method substr() of class Drupal\Component\Utility\Unicode. Deprecated in drupal:8.6.0 and is removed from drupal:9.0.0. Use mb_substr() instead.',
      'Call to deprecated method link() of class Drupal\Core\Entity\EntityInterface. Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\Core\EntityInterface::toLink()->toString() instead.',
      'Call to deprecated function entity_load(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use the entity type storage\'s load() method.',
      'Call to deprecated function node_load(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\node\Entity\Node::load().',
      'Call to deprecated function file_load(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\file\Entity\File::load().',
      'Call to deprecated function user_load(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\user\Entity\User::load().',
      'Call to deprecated function file_directory_temp(). Deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::getTempDirectory() instead.',
      'Call to deprecated function file_directory_os_temp(). Deprecated in drupal:8.3.0 and is removed from drupal:9.0.0. Use Drupal\Component\FileSystem\FileSystem::getOsTemporaryDirectory().',
      'Call to deprecated function drupal_realpath(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystem::realpath().',
      'Call to deprecated function file_uri_target(). Deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface::getTarget() instead.',

      // 0.5.4
      'Call to deprecated method format() of class Drupal\Component\Utility\SafeMarkup. Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use Drupal\Component\Render\FormattableMarkup.',
      'Call to deprecated constant FILE_EXISTS_RENAME: Deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. Use Drupal\Core\File\FileSystemInterface::EXISTS_RENAME.',
      // Covered below with the pattern.
      // 'Call to deprecated method l() of class [redacted].
      // Deprecated in drupal:8.0.0 and is removed from
      // drupal:9.0.0. Use Drupal\Core\Link::fromTextAndUrl() instead.',.
      'Call to deprecated function entity_create(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use The method overriding Entity::create() for the entity type, e.g. \Drupal\node\Entity\Node::create() if the entity type is known. If the entity type is variable, use the entity storage\'s create() method to construct a new entity:',

      // 0.5.5
      // No new rules
      // 0.5.6
      'Call to deprecated constant DATETIME_STORAGE_TIMEZONE: Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface::STORAGE_TIMEZONE instead.',
      'Call to deprecated constant DATETIME_DATETIME_STORAGE_FORMAT: Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface::DATETIME_STORAGE_FORMAT instead.',
      'Call to deprecated constant DATETIME_DATE_STORAGE_FORMAT: Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface::DATE_STORAGE_FORMAT instead.',

      // 0.10.0
      'Call to deprecated method getLowercaseLabel() of class Drupal\Core\Entity\EntityTypeInterface. Deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Instead, you should call getSingularLabel(). See https://www.drupal.org/node/3075567',
      'Call to deprecated function entity_delete_multiple(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use the entity storage\'s \Drupal\Core\Entity\EntityStorageInterface::delete() method to delete multiple entities:',
      'Call to deprecated function entity_view(). Deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use the entity view builder\'s view() method for creating a render array:',

      // 0.11.0
      // No new rules
      // 0.11.1
      'Call to deprecated method drupalPostForm() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use $this->submitForm() instead.',

      // 0.11.2
      'Call to deprecated method assertText() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use - $this->assertSession()->responseContains() for non-HTML responses, like XML or Json. - $this->assertSession()->pageTextContains() for HTML responses. Unlike the deprecated assertText(), the passed text should be HTML decoded, exactly as a human sees it in the browser.',
      'Call to deprecated method assertEqual() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertEquals() instead.',
      'Call to deprecated method assertEqual() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertEquals() instead.',
      'Call to deprecated method assertIdentical() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertSame() instead.',
      'Call to deprecated method assertIdentical() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertSame() instead.',
      'Call to deprecated method assertResponse() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->statusCodeEquals() instead.',
      'Call to deprecated method assertRaw() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->responseContains() instead.',
      'Call to deprecated method assertFieldByName() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->fieldExists() or $this->assertSession()->buttonExists() or $this->assertSession()->fieldValueEquals() instead.',
      'Call to deprecated method buildXPathQuery() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->buildXPathQuery() instead.',
      'Call to deprecated method assertHeader() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.3.0 and is removed from drupal:10.0.0. Use $this->assertSession()->responseHeaderEquals() instead.',
      'Call to deprecated method assertNoCacheTag() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.4.0 and is removed from drupal:10.0.0. Use $this->assertSession()->responseHeaderNotContains() instead.',
      'Call to deprecated method assertCacheTag() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->responseHeaderContains() instead.',
      'Call to deprecated method assertNoPattern() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.4.0 and is removed from drupal:10.0.0. Use $this->assertSession()->responseNotMatches() instead.',
      'Call to deprecated method assertPattern() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->responseMatches() instead.',
      'Call to deprecated method assertEscaped() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->assertEscaped() instead.',
      'Call to deprecated method assertNoEscaped() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->assertNoEscaped() instead.',
      'Call to deprecated method assertNotEqual() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertNotEquals() instead.',
      'Call to deprecated method assertNotEqual() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertNotEquals() instead.',
      'Call to deprecated method assertNotIdentical() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertNotSame() instead.',
      'Call to deprecated method assertNotIdentical() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertNotSame() instead.',
      'Call to deprecated method assertIdenticalObject() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertEquals() instead.',
      'Call to deprecated method assertIdenticalObject() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertEquals() instead.',
      'Call to deprecated method assert() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertTrue() instead.',
      'Call to deprecated method assert() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. Use $this->assertTrue() instead.',
      'Call to deprecated method assertElementNotPresent() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->elementNotExists() instead.',
      'Call to deprecated method assertElementPresent() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->elementExists() instead.',
      'Call to deprecated method assertNoText() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use - $this->assertSession()->responseNotContains() for non-HTML responses, like XML or Json. - $this->assertSession()->pageTextNotContains() for HTML responses. Unlike the deprecated assertNoText(), the passed text should be HTML decoded, exactly as a human sees it in the browser.',
      'Call to deprecated method assertNoRaw() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->responseNotContains() instead.',
      'Call to deprecated method assertTitle() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->titleEquals() instead.',
      'Call to deprecated method assertNoLink() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->linkNotExists() instead.',
      'Call to deprecated method assertLink() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->linkExists() instead.',
      'Call to deprecated method assertLinkByHref() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->linkByHrefExists() instead.',
      'Call to deprecated method assertNoLinkByHref() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->linkByHrefNotExists() instead.',

      // 0.11.3
      'Call to deprecated method pass() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. PHPUnit interrupts a test as soon as a test assertion fails, so there is usually no need to call this method. If a test\'s logic relies on this method, refactor the test.',
      'Call to deprecated method pass() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:8.0.0 and is removed from drupal:10.0.0. PHPUnit interrupts a test as soon as a test assertion fails, so there is usually no need to call this method. If a test\'s logic relies on this method, refactor the test.',
      'Call to deprecated method assertNoUniqueText() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Instead, use $this->getSession()->pageTextMatchesCount() if you know the cardinality in advance, or $this->getSession()->getPage()->getText() and substr_count().',
      'Call to deprecated method assertUniqueText() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->getSession()->pageTextContainsOnce() or $this->getSession()->pageTextMatchesCount() instead.',
      'Call to deprecated method assertNoFieldByName() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->fieldNotExists() or $this->assertSession()->buttonNotExists() or $this->assertSession()->fieldValueNotEquals() instead.',
      'Call to deprecated method assertFieldChecked() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->checkboxChecked() instead.',
      'Call to deprecated method assertNoFieldChecked() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->checkboxNotChecked() instead.',
      'Call to deprecated method assertNoOption() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->optionNotExists() instead.',
      'Call to deprecated method assertOptionByText() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.4.0 and is removed from drupal:10.0.0. Use $this->assertSession()->optionExists() instead.',
      'Call to deprecated method assertOption() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->optionExists() instead.',
      'Call to deprecated method assertUrl() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->addressEquals() instead.',
      'Call to deprecated method constructFieldXpath() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.5.0 and is removed from drupal:10.0.0. Use $this->getSession()->getPage()->findField() instead.',
      // getAllOptions: rule exists but no instance in contrib.
      'Call to deprecated method getRawContent() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->getSession()->getPage()->getContent() instead.',
      'Call to deprecated method assertFieldById() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->fieldExists() or $this->assertSession()->buttonExists() or $this->assertSession()->fieldValueEquals() instead.',
      'Call to deprecated method assertField() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->fieldExists() or $this->assertSession()->buttonExists() instead.',
      'Call to deprecated method assertNoFieldById() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->fieldNotExists() or $this->assertSession()->buttonNotExists() or $this->assertSession()->fieldValueNotEquals() instead.',
      'Call to deprecated method assertNoField() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->fieldNotExists() or $this->assertSession()->buttonNotExists() instead.',
      'Call to deprecated method assertOptionSelected() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:8.2.0 and is removed from drupal:10.0.0. Use $this->assertSession()->optionExists() instead and check the "selected" attribute yourself.',

      // 0.12.1
      'Call to deprecated function drupal_get_path(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\Core\Extension\ExtensionPathResolver::getPath() instead.',
      'Call to deprecated function file_create_url(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use the appropriate method on \Drupal\Core\File\FileUrlGeneratorInterface instead.',
      'Call to deprecated function file_url_transform_relative(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\Core\File\FileUrlGenerator::transformRelative() instead.',
      'Call to deprecated function render(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\Core\Render\RendererInterface::render() instead.',
      // MetadataBag::clearCsrfTokenSeed()
      'Call to deprecated function drupal_get_filename(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\Core\Extension\ExtensionPathResolver::getPathname() instead.',
      'Call to deprecated function file_copy(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\file\FileRepositoryInterface::copy() instead.',
      'Call to deprecated function file_move(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\file\FileRepositoryInterface::move() instead.',
      'Call to deprecated function file_save_data(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\file\FileRepositoryInterface::writeData() instead.',

      // 0.12.2
      // No new rules
      // 0.12.3
      'Call to deprecated function user_password(). Deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use Drupal\Core\Password\PasswordGeneratorInterface::generate() instead.',

      // 0.12.4
      'Call to deprecated function file_build_uri(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0 without replacement.',

      // 0.13.0
      // No new rules
      // 0.13.1
      // Covers https://www.drupal.org/node/2909426
      // ($modules property in tests), but not
      // identified in contrib by phpstan.
      // 0.15.0
      // No new rules
      // 0.15.1
      // No new rules
      // 0.18.0
      // Add TwigSetList::TWIG_240 to D9 deprecations
      // (https://github.com/palantirnet/drupal-rector/pull/223)
      // -- not tracking non-Drupal coverage here
      // system_sort_modules_by_info_name:
      // (https://www.drupal.org/node/3225999)
      // -- not found in contrib
      'Call to deprecated function module_load_install(). Deprecated in drupal:9.4.0 and is removed from drupal:10.0.0. Use Drupal::moduleHandler()->loadInclude($module, \'install\') instead. Note, the replacement no longer allows including code from uninstalled modules.',
      'Call to deprecated function watchdog_exception(). Deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use Use Drupal\Core\Utility\Error::logException() instead.',
      'Call to deprecated function taxonomy_vocabulary_get_names(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal::entityQuery(\'taxonomy_vocabulary\')->execute() instead.',
      // taxonomy_term_uri.
      'Call to deprecated function taxonomy_term_load_multiple_by_name(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal::entityTypeManager()->getStorage(\'taxonomy_term\')->loadByProperties([\'name\' => $name, \'vid\' => $vid]) instead, to get a list of taxonomy term entities having the same name and keyed by their term ID.',
      // taxonomy_terms_static_reset -- not found in contrib
      // taxonomy_vocabulary_static_reset -- not found in contrib.
      'Call to deprecated function taxonomy_implode_tags(). Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\Core\Entity\Element\EntityAutocomplete::getEntityLabels() instead.',
      // taxonomy_term_title -- not found in contrib
      // Drupal 9 rector now includes PHPUnit rector
      // (PHPUnitLevelSetList::UP_TO_PHPUNIT_90)
      // 0.18.1.
      'Drupal\Tests\BrowserTestBase::$defaultTheme is required in drupal:9.0.0 when using an install profile that does not set a default theme. See https://www.drupal.org/node/3083055, which includes recommendations on which theme to use.',

      // 0.18.2
      'Call to deprecated function system_time_zones(). Deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. This function is no longer used in Drupal core. Use Drupal\Core\Datetime\TimeZoneFormHelper::getOptionsList() or \DateTimeZone::listIdentifiers() instead.',

      // 0.18.3
      'Missing call to parent::setUp() method.',
      'Missing call to parent::tearDown() method.',

      // 0.18.4
      'Call to deprecated function module_load_include(). Deprecated in drupal:9.4.0 and is removed from drupal:11.0.0. Use Drupal::moduleHandler()->loadInclude($module, $type, $name = NULL). Note that including code from uninstalled extensions is no longer supported.',
      'Call to deprecated function module_load_install(). Deprecated in drupal:9.4.0 and is removed from drupal:10.0.0. Use Drupal::moduleHandler()->loadInclude($module, \'install\') instead. Note, the replacement no longer allows including code from uninstalled modules.',

      // 0.18.5
      'Call to deprecated constant FILE_STATUS_PERMANENT: Deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use Drupal\file\FileInterface::STATUS_PERMANENT or \Drupal\file\FileInterface::setPermanent().',
      'Call to deprecated method toInt() of class Drupal\Component\Utility\Bytes. Deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use Drupal\Component\Utility\Bytes::toNumber() instead.',

      // 0.18.6
      // no new rules
      // 0.19.0
      'Call to deprecated function format_size(). Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Drupal\Core\StringTranslation\ByteSizeMarkup::create($size, $langcode) instead.',

      // 0.19.1
      // no new rules
      // 0.19.2
      // no new rules
      // 0.20.0
      'Call to deprecated method getResource() of class Drupal\system\Plugin\ImageToolkit\GDToolkit. Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Drupal\system\Plugin\ImageToolkit\GDToolkit::getImage() instead.',
      'Call to deprecated method setResource() of class Drupal\system\Plugin\ImageToolkit\GDToolkit. Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Drupal\system\Plugin\ImageToolkit\GDToolkit::setImage() instead.',
      // Symfony level was added, adding support for multiple
      // non-drupal deprecations.
      'Fetching deprecated class constant MASTER_REQUEST of interface Symfony\Component\HttpKernel\HttpKernelInterface: since symfony/http-kernel 5.3, use MAIN_REQUEST instead. To ease the migration, this constant won\'t be removed until Symfony 7.0.',
      'Call to deprecated method getContentType() of class Symfony\Component\HttpFoundation\Request: since Symfony 6.2, use getContentTypeFormat() instead',
      'Call to deprecated method enableAnnotationMapping() of class Symfony\Component\Validator\ValidatorBuilder: since Symfony 6.4, use "enableAttributeMapping()" instead.',
      'Call to deprecated method attachPart() of class Symfony\Component\Mime\Email: since Symfony 6.2, use addPart() instead',
      'Class [redacted] implements deprecated interface Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface: since Symfony 6.2, implement ValueResolverInterface instead',

      // 0.20.1
      'Symfony\Cmf\Component\Routing\RouteObjectInterface::ROUTE_OBJECT is deprecated and removed in Drupal 10. Use Drupal\Core\Routing\RouteObjectInterface::ROUTE_OBJECT instead.',
      'Symfony\Cmf\Component\Routing\RouteObjectInterface::ROUTE_NAME is deprecated and removed in Drupal 10. Use Drupal\Core\Routing\RouteObjectInterface::ROUTE_NAME instead.',
      'Symfony\Cmf\Component\Routing\RouteObjectInterface::CONTROLLER_NAME is deprecated and removed in Drupal 10. Use Drupal\Core\Routing\RouteObjectInterface::CONTROLLER_NAME instead.',

      // 0.20.2
      'Call to deprecated function _drupal_flush_css_js(). Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Use Drupal\Core\Asset\AssetQueryStringInterface::reset() instead.',
      'drupal_theme_rebuild() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use theme.registry service reset() method instead. See https://www.drupal.org/node/3348853',

      // 0.20.3
      'Call to deprecated function file_icon_class(). Deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. Use Drupal\file\IconMimeTypes::getIconClass() instead.',
      'Call to deprecated method setMethods() of class PHPUnit\Framework\MockObject\MockBuilder: https://github.com/sebastianbergmann/phpunit/pull/3687',

      // 1.0.0
      'Call to deprecated function _content_translation_install_field_storage_definitions(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use ContentTranslationHooks::installFieldStorageDefinitions() instead.',
      'Call to deprecated function _contextual_id_to_links(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contextual\ContextualLinksSerializer::idToLinks() instead.',
      'Call to deprecated function _contextual_links_to_id(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contextual\ContextualLinksSerializer::linksToId() instead.',
      'Call to deprecated function _dblog_get_message_types(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\Drupal\dblog\DbLogFilters::class)->getMessageTypes() instead.',
      'Call to deprecated function _filter_autop(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The logic has been included in \Drupal\filter\Plugin\Filter\FilterAutoP::process() and no replacement is provided.',
      'Call to deprecated function _filter_html_escape(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The logic has been included in \Drupal\filter\Plugin\Filter\FilterHtmlEscape::process() and no replacement is provided.',
      'Call to deprecated function _filter_html_image_secure_process(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The logic is included in \Drupal\filter\Plugin\Filter\FilterHtmlImageSecure::process() and no replacement is provided.',
      'Call to deprecated function _filter_tips(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_configure_form_display(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_configure_view_display(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_media_type_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_views_form_media_library_after_build(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _menu_ui_node_save(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\MenuUiUtility::menuUiNodeSave() instead.',
      'Call to deprecated function _responsive_image_build_source_attributes(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::buildSourceAttributes() instead.',
      'Call to deprecated function _responsive_image_image_style_url(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::getImageStyleUrl() instead.',
      'Call to deprecated function _system_default_theme_features(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal\Core\Extension\ThemeSettingsProvider::DEFAULT_THEME_FEATURES instead.',
      'Call to deprecated function _update_ckeditor5_html_filter(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\ckeditor5\Hook\Ckeditor5Hooks::updateCkeditor5HtmlFilter() instead.',
      'Call to deprecated function _update_manager_cache_directory(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function _update_manager_extract_directory(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function _update_manager_unique_identifier(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function _views_field_get_entity_type_storage(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'views.field_data_provider\') ->getSqlStorageForField($field_storage); instead.',
      'Call to deprecated function _views_field_get_entity_type_storage(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use views.field_data_provider service instead.',
      'Call to deprecated function automated_cron_settings_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use AutomatedCronHooks::automatedCronSettingsSubmit() instead.',
      'Call to deprecated function block_content_add_body_field(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function block_theme_initialize(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. No replacement is provided.',
      'Call to deprecated function check_markup(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no direct replacement. It\'s recommended to always return a renderable array without flattening as markup to pass the cacheability metadata.',
      'Call to deprecated function ckeditor5_filter_format_edit_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\ckeditor5\Hook\Ckeditor5Hooks::filterFormatEditFormSubmit() instead.',
      'Call to deprecated function contact_form_user_admin_settings_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contact\Hook\ContactFormHooks::userAdminSettingsSubmit instead.',
      'Call to deprecated function contact_user_profile_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contact\Hook\ContactFormHooks::profileFormSubmit() instead.',
      'Call to deprecated function content_translation_enable_widget(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::getWidget() instead.',
      'Call to deprecated function content_translation_language_configuration_element_process(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::configElementProcess() instead.',
      'Call to deprecated function content_translation_language_configuration_element_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::configElementSubmit() instead.',
      'Call to deprecated function content_translation_language_configuration_element_validate(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::configElementValidate() instead.',
      'Call to deprecated function content_translation_translate_access(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the access() method of the content_translation.manager service instead.',
      'Call to deprecated function datetime_type_field_views_data_helper(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'datetime.views_helper\') ->buildViewsData($field_storage, $data, $column_name); instead.',
      'Call to deprecated function dblog_filters(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\Drupal\dblog\DbLogFilters::class)->filters() instead.',
      'Call to deprecated function drupal_common_theme(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use Drupal\Core\Theme\ThemeCommonElements::commonElements() instead,',
      'Call to deprecated function drupal_requirements_severity(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::getMaxSeverity() instead.',
      'Call to deprecated function editor_filter_xss(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Instead, use the method ::filterXss() from the element.editor service.',
      'Call to deprecated function editor_load(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::entityTypeManager()->getStorage(\'editor\')->load($format_id) instead.',
      'Call to deprecated function entity_test_create_bundle(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\entity_test\EntityTestHelper::createBundle() instead.',
      'Call to deprecated function entity_test_delete_bundle(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\entity_test\EntityTestHelper::deleteBundle() instead.',
      'Call to deprecated function field_purge_batch(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\Core\Field\FieldPurger::purgeBatch() instead.',
      'Call to deprecated function field_ui_form_manage_field_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\field_ui\Hook\FieldUiHooks::manageFieldFormSubmit() instead.',
      'Call to deprecated function file_get_content_headers(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\file\Entity\FileInterface::getDownloadHeaders() instead.',
      'Call to deprecated function file_managed_file_submit(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\file\Element\ManagedFile::submit() instead.',
      'Call to deprecated function file_system_settings_submit(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\file\Hook\FileHooks::settingsSubmit() instead.',
      'Call to deprecated function filter_default_format(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatRepositoryInterface service with the ::getDefaultFormat() method instead.',
      'Call to deprecated function filter_fallback_format(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatRepositoryInterface service with the ::getFallbackFormatId() method instead.',
      'Call to deprecated function filter_formats(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the Drupal\filter\FilterFormatRepositoryInterface service with the ::getAllFormats() method to get all formats, or with the ::getFormatsForAccount() method to get all formats that a user is able to access.',
      'Call to deprecated function filter_get_formats_by_role(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatRepositoryInterface service with the ::getFormatsByRole() method instead.',
      'Call to deprecated function filter_get_roles_by_format(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatInterface::getRoles() method instead.',
      'Call to deprecated function image_filter_keyword(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use Drupal\Component\Utility\Image::getKeywordOffset() instead.',
      'Call to deprecated function image_path_flush(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ImageDerivativeUtilities::pathFlush() instead.',
      'Call to deprecated function image_style_options(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ImageDerivativeUtilities::styleOptions() instead.',
      'Call to deprecated function language_configuration_element_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\language\Element\LanguageConfiguration::submit() instead.',
      'Call to deprecated function language_process_language_select(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\language\Hook\LanguageHooks::processLanguageSelect() instead.',
      'Call to deprecated function locale_config_batch_refresh_name(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use locale_config_batch_update_config_translations() instead.',
      'Call to deprecated function locale_config_batch_set_config_langcodes(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use locale_config_batch_update_default_config_langcodes() instead.',
      'Call to deprecated function locale_translation_batch_update_build(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleFetch::class) ->buildUpdateBatch($projects, $langcodes, $options) instead.',
      'Call to deprecated function media_filter_format_edit_form_validate(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use MediaHooks::formatEditFormValidate() instead.',
      'Call to deprecated function menu_ui_form_node_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::formNodeFormSubmit() instead.',
      'Call to deprecated function menu_ui_form_node_type_form_builder(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::formNodeTypeFormBuilder() instead.',
      'Call to deprecated function menu_ui_form_node_type_form_validate(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::formNodeTypeFormValidate() instead.',
      'Call to deprecated function menu_ui_get_menu_link_defaults(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\menu_ui\MenuUiUtility::getMenuLinkDefaults() instead.',
      'Call to deprecated function menu_ui_node_builder(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::nodeBuilder() instead.',
      'Call to deprecated function node_access_grants(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'Drupal\node\NodeGrantsHelper\')->nodeAccessGrants() instead.',
      'Call to deprecated function node_access_needs_rebuild(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'\Drupal\node\NodeAccessRebuild\')->setNeedsRebuild() and \Drupal::service(\'\Drupal\node\NodeAccessRebuild\')->needsRebuild() instead.',
      'Call to deprecated function node_access_rebuild(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'\Drupal\node\NodeAccessRebuild\')->rebuild($batch_mode) instead.',
      'Call to deprecated function node_access_view_all_nodes(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal::entityTypeManager()->getAccessControlHandler(\'node\')->checkAllGrants() instead.',
      'Call to deprecated function node_add_body_field(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function node_get_type_label(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use $node->getBundleEntity()->label() instead.',
      'Call to deprecated function node_type_get_description(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use $node_type->getDescription() instead.',
      'Call to deprecated function node_type_get_names(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal::service(\'entity_type.bundle.info\')->getBundleLabels(\'node\') instead.',
      'Call to deprecated function responsive_image_get_image_dimensions(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::getImageDimensions() instead.',
      'Call to deprecated function responsive_image_get_mime_type(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::getMimeType() instead.',
      'Call to deprecated function syslog_facility_list(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function syslog_logging_settings_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function system_default_region(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'theme_handler\')->getTheme()->getDefaultRegion() instead.',
      'Call to deprecated function system_region_list(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'theme_handler\')->getTheme()->listAllRegions() or \Drupal::service(\'theme_handler\')->getTheme()->listVisibleRegions() instead.',
      'Call to deprecated function system_sort_themes(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function taxonomy_build_node_index(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function taxonomy_delete_node_index(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function template_preprocess(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement, default preprocess variables are added for all theme hooks directly in \Drupal\Core\Theme\ThemeManager.',
      'Call to deprecated function template_preprocess_container(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_datetime_form(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_datetime_wrapper(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_html(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_layout(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_links(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_page(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_page(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use hook_theme() to define page variables instead.',
      'Call to deprecated function template_preprocess_time(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function text_summary(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(TextSummary::class)->generate() instead.',
      'Call to deprecated function theme_get_setting(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal::service(\'Drupal\Core\Extension\ThemeSettingsProvider\')->getSetting() instead.',
      'Call to deprecated function twig_render_template(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0.',
      'Call to deprecated function update_clear_update_disk_cache(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function update_delete_file_if_stale(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function user_form_process_password_confirm(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use UserThemeHooks::processPasswordConfirm() instead.',
      'Call to deprecated function views_add_contextual_links(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\views\ContextualLinksHelper::addLinks() instead.',
      'Call to deprecated function views_disable_view(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use $view->disable()->save() instead.',
      'Call to deprecated function views_enable_view(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use $view->enable()->save() instead.',
      'Call to deprecated function views_entity_field_label(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'entity_field.manager\')->getFieldLabels() instead.',
      'Call to deprecated function views_entity_field_label(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use entity_field.manager service instead.',
      'Call to deprecated function views_field_default_views_data(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'views.field_data_provider\') ->defaultFieldImplementation($field_storage); instead.',
      'Call to deprecated function views_field_default_views_data(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use views.field_data_provider service instead.',
      'Call to deprecated function views_get_view_result(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Views::getViewResult($name, $display_id, ...$args) instead.',
      'Call to deprecated function views_ui_contextual_links_suppress(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function views_ui_contextual_links_suppress_pop(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function views_ui_contextual_links_suppress_push(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function views_view_is_disabled(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use !$view->status() instead.',
      'Call to deprecated function views_view_is_enabled(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use $view->status() instead.',
      'Call to deprecated method addModule() of class Drupal\Core\Extension\ModuleHandler. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method addModule() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method addProfile() of class Drupal\Core\Extension\ModuleHandler. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method addProfile() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method basename() of class Drupal\Core\File\FileSystem. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use PHP native basename() instead.',
      'Call to deprecated method basename() of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use PHP native basename() instead.',
      'Call to deprecated method countDefaultLanguageRevisions() of interface Drupal\node\NodeStorageInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method currentErrorHandler() of class Drupal\Core\Utility\Error. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use get_error_handler() instead.',
      'Call to deprecated method delete() of class Drupal\Core\Session\SessionManager. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\user\UserSessionRepositoryInterface::deleteAll() instead.',
      'Call to deprecated method delete() of interface Drupal\Core\Session\SessionManagerInterface. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\user\UserSessionRepositoryInterface::deleteAll() instead.',
      'Call to deprecated method fetchColumn() of class Drupal\Core\Database\StatementPrefetchIterator. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use ::fetchField() instead.',
      'Call to deprecated method getCountNewComments() of class Drupal\comment\CommentManager. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\history\HistoryManager::getCountNewComments instead.',
      'Call to deprecated method getCountNewComments() of interface Drupal\comment\CommentManagerInterface. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\history\HistoryManager::getCountNewComments instead.',
      'Call to deprecated method getEntityTypeIdKeyType() of class Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. To determine if an entity type has an integer ID key, use Drupal\Core\Entity\EntityTypeInterface::hasIntegerId().',
      'Call to deprecated method getHookInfo() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Not needed any more.',
      'Call to deprecated method getName() of class Drupal\Core\Extension\ModuleHandler. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.module\')->getName($module) instead.',
      'Call to deprecated method getName() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.module\')->getName($module) instead.',
      'Call to deprecated method getOriginalClass() of interface Drupal\Core\Entity\EntityTypeInterface. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use getDecoratedClasses() instead.',
      'Call to deprecated method getRowCacheKeys() of class Drupal\views\Plugin\views\cache\CachePluginBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method getRowId() of class Drupal\views\Plugin\views\cache\CachePluginBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method hasIntegerId() of class Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\Core\Entity\EntityTypeInterface::hasIntegerId() instead.',
      'Call to deprecated method installModule() of class Drupal\Core\Recipe\RecipeRunner. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\Core\Recipe\RecipeRunner::installModules() instead.',
      'Call to deprecated method invalidateAll() of interface Drupal\Core\Cache\CacheBackendInterface. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use CacheBackendInterface::deleteAll() or cache tag invalidation instead.',
      'Call to deprecated method isConfigurable() of class Drupal\Component\Plugin\PluginBase. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use instanceof to check if the plugin implements \Drupal\Component\Plugin\ConfigurableInterface instead.',
      'Call to deprecated method loadAllIncludes() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method movePointerTo() of class Drupal\Tests\layout_builder\FunctionalJavascript\LayoutBuilderDisableInteractionsTest. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use $this->getSession()->getDriver()->mouseOver() instead.',
      'Call to deprecated method pathAliasWhitelistRebuild() of class Drupal\path_alias\AliasManager. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use Drupal\path_alias\AliasManager::pathAliasPrefixListRebuild instead.',
      'Call to deprecated method pluginManager() of class Drupal\views\Views. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'plugin.manager.views.{type}\') for specific types or \Drupal::service(\'views.plugin_managers\')->get($type) for dynamic.',
      'Call to deprecated method rebuildThemeData() of class Drupal\Core\Extension\ThemeHandler. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.theme\')->reset()->getList() instead.',
      'Call to deprecated method rebuildThemeData() of interface Drupal\Core\Extension\ThemeHandlerInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.theme\')->reset()->getList() instead.',
      'Call to deprecated method renderPlain() of class Drupal\Core\Render\Renderer. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\Render\RendererInterface::renderInIsolation() instead.',
      'Call to deprecated method renderPlain() of interface Drupal\Core\Render\RendererInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\Render\RendererInterface::renderInIsolation() instead.',
      'Call to deprecated method revisionIds() of interface Drupal\node\NodeStorageInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use an entity query instead.',
      'Call to deprecated method setUriCallback() of interface Drupal\Core\Entity\EntityTypeInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use link templates or a route provider to specify entity URIs.',
      'Call to deprecated method trustData() of class Drupal\Core\Config\Entity\ConfigEntityBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method trustData() of interface Drupal\Core\Config\Entity\ConfigEntityInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method userRevisionIds() of interface Drupal\node\NodeStorageInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use an entity query instead.',
      'Call to deprecated method validateTitleElement() of class Drupal\link\Plugin\Field\FieldWidget\LinkWidget. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Instead, validation is performed by the LinkTitleRequiredConstraint on the LinkItem field type.',
      'Call to deprecated method writeCache() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Not needed any more.',
      'Calling Drupal\Core\Config\Config::save() with the $has_trusted_data argument is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement. See https://www.drupal.org/node/3348180',
      'Fetching deprecated class constant ANONYMOUS_MAYNOT_CONTACT of interface Drupal\comment\CommentInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\AnonymousContact::Forbidden instead.',
      'Fetching deprecated class constant ANONYMOUS_MAY_CONTACT of interface Drupal\comment\CommentInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\AnonymousContact::Allowed instead.',
      'Fetching deprecated class constant ANONYMOUS_MUST_CONTACT of interface Drupal\comment\CommentInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\AnonymousContact::Required instead.',
      'Fetching deprecated class constant CLOSED of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\CommentingStatus::Closed instead.',
      'Fetching deprecated class constant EXISTS_ERROR of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\File\FileExists::Error instead.',
      'Fetching deprecated class constant EXISTS_RENAME of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\File\FileExists::Rename instead.',
      'Fetching deprecated class constant EXISTS_REPLACE of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\File\FileExists::Replace instead.',
      'Fetching deprecated class constant FORM_BELOW of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\FormLocation::Below instead.',
      'Fetching deprecated class constant FORM_SEPARATE_PAGE of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\FormLocation::SeparatePage instead.',
      'Fetching deprecated class constant HIDDEN of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\CommentingStatus::Hidden instead.',
      'Fetching deprecated class constant OPEN of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\CommentingStatus::Open instead.',
      'Fetching deprecated class constant RECURSIVE_RENDER_LIMIT of class Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. EntityViewBuilder #pre_render and #post_render callbacks prevent recursion.',
      'Fetching deprecated class constant REQUIREMENT_ERROR of class Drupal\system\SystemManager. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::Error instead.',
      'Fetching deprecated class constant REQUIREMENT_OK of class Drupal\system\SystemManager. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::OK instead.',
      'Fetching deprecated class constant REQUIREMENT_WARNING of class Drupal\system\SystemManager. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::Warning instead.',
      'Passing a string $root value to Drupal\Core\Database\Database::convertDbUrlToConnectionInfo() is deprecated in drupal:11.3.0 and will be removed in drupal:12.0.0. There is no replacement.',
      'Use Drupal::TRANSLATION_DEFAULT_SERVER_PATTERN instead. in drupal:11.2.0 and is removed from drupal:12.0.0.',
      'Use of deprecated global constant JSONAPI_FILTER_AMONG_ENABLED',
      'Use of deprecated global constant JSONAPI_FILTER_AMONG_OWN',
      'Use of deprecated global constant JSONAPI_FILTER_AMONG_PUBLISHED',
      'Using the variable $values as string is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Provide an array as parameter. See https://www.drupal.org/node/3473739',
      'editor_image_upload_settings_form() deprecated in drupal:11.4.0, removed in drupal:13.0.0. Replaced by \Drupal::service(EditorImageUploadSettings::class)->getForm().',
      'locale_translation_batch_fetch_build() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleFetch::class)->buildFetchBatch($projects, $langcodes, $options) instead. See https://www.drupal.org/node/3572345',
      'locale_translation_build_sources() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleSource::class)->buildSources($projects, $langcodes) instead. See https://www.drupal.org/node/3569330',
      'locale_translation_clear_cache_projects() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service("cache.memory")->delete("locale_get_projects") instead. See https://www.drupal.org/node/3569330',
      'locale_translation_get_projects() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleProjectRepository::class)->getAll() or \Drupal::service(LocaleProjectRepository::class)->getMultiple($project_names) instead. See https://www.drupal.org/node/3569330',
      'locale_translation_load_sources() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleSource::class)->loadSources($projects, $langcodes) instead. See https://www.drupal.org/node/3569330',
      'postInstall() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3461934',
      'Call to deprecated method getDrupalRoot() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Access $this->root directly.',
      'Call to deprecated method getDrupalRoot() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Access $this->root directly.',
      'Call to deprecated method getDrupalRoot() of class Drupal\Tests\UnitTestCase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Access $this->root directly.',
      'Call to deprecated method setCacheKey() of class Drupal\path_alias\AliasManager. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method writeCache() of class Drupal\path_alias\AliasManager. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Usage of deprecated trait Drupal\Component\Utility\ToStringTrait. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Implement the __toString() method directly, exception handling is no longer required.',
      'Calling Drupal\Core\Render\Renderer::addCacheableDependency() with an object that doesn\'t implement Drupal\Core\Cache\CacheableDependencyInterface is deprecated in drupal:11.3.0 and will throw an error in drupal:13.0.0. See https://www.drupal.org/node/3525389',
      'Call to deprecated method expectDeprecation() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use $this->expectUserDeprecationMessage() or $this->expectUserDeprecationMessageMatches() instead.',
      'Call to deprecated function hide(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. To hide form elements, use [\'#access\'] = FALSE. For render elements, use [\'#printed\'] = TRUE.',
      'Call to deprecated function show(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. To show form elements, use [\'#access\'] = TRUE. For render elements, use [\'#printed\'] = FALSE.',

    ];
    return in_array($string, $rector_covered) ||
      strpos($string, 'Call to deprecated method l() of class Drupal') === 0;
  }

}
