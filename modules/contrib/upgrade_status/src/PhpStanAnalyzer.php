<?php

namespace Drupal\upgrade_status;

use Composer\InstalledVersions;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use DrupalFinder\DrupalFinderComposerRuntime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs PHPStan on a project.
 *
 * Finds PHP and PHPStan, writes the PHPStan configuration to a temporary
 * directory, and runs PHPStan in a separate process.
 */
final class PhpStanAnalyzer {

  /**
   * Whether PHPUnit is installed, or NULL if it was not checked yet.
   *
   * @var bool|null
   */
  protected $phpUnitInstalled = NULL;

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
   * Path to the PHPStan script.
   *
   * @var string
   */
  protected $phpstanPath;

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
   * Drupal project finder.
   *
   * @var \DrupalFinder\DrupalFinderComposerRuntime
   */
  protected $finder;

  /**
   * Whether the analyzer environment is initialized.
   *
   * @var bool
   */
  protected $environmentInitialized = FALSE;

  /**
   * Constructs a new PHPStan analyzer.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The default cache bin.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly LoggerInterface $logger,
    private readonly FileSystemInterface $fileSystem,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly CacheBackendInterface $cache,
    private readonly FileFinder $fileFinder,
  ) {}

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
    $this->phpstanPath = $this->findPhpStanPath();

    // Each Drupal root and each user gets its own directory. Otherwise the
    // web server and the command line user, or two sites on the same server,
    // can't write each other's files.
    $suffix = substr(hash('sha256', DRUPAL_ROOT), 0, 12);
    if (function_exists('posix_geteuid')) {
      $suffix .= '-' . posix_geteuid();
    }
    $this->temporaryDirectory = $this->fileSystem->getTempDirectory() . '/upgrade_status-' . $suffix;
    $this->prepareTempDirectory();

    $this->phpstanNeonPath = $this->temporaryDirectory . '/deprecation_testing.neon';
    $this->createModifiedNeonFile();

    $this->environmentInitialized = TRUE;
  }

  /**
   * Finds the PHPStan script.
   *
   * Composer knows where the phpstan/phpstan package is installed, whatever
   * the bin-dir and vendor-dir settings are. The phpstan file in the package
   * is a PHP script on every platform, so it can be run with the PHP binary.
   * The proxies in the bin directory are shell scripts on Windows.
   *
   * @return string
   *   The path of the PHPStan script.
   *
   * @throws \Exception
   *   If PHPStan is not installed with Composer.
   */
  protected function findPhpStanPath(): string {
    if (!InstalledVersions::isInstalled('phpstan/phpstan') || !($install_path = InstalledVersions::getInstallPath('phpstan/phpstan'))) {
      throw new \Exception('PHPStan is not installed. Install it with Composer, for example with "composer require --dev drupal/core-dev".');
    }
    $path = $install_path . '/phpstan';
    if (!file_exists($path)) {
      throw new \Exception('The PHPStan script was not found at ' . $path . '. Reinstall the phpstan/phpstan package with Composer.');
    }
    return realpath($path) ?: $path;
  }

  /**
   * Finds the PHP command line binary.
   *
   * PHPStan is run with the same PHP version as the site. On some servers the
   * binary can't be found, like with open_basedir or PHP-FPM. Then it can be
   * set in settings.php with $settings['upgrade_status_php_binary'].
   *
   * @return string
   *   PHP path if found.
   *
   * @throws \Exception
   *   If the binary is not found, or it is not a PHP command line binary of
   *   the same version as the site.
   */
  protected function findPhpPath() {
    $help = 'Set $settings[\'upgrade_status_php_binary\'] in settings.php to the PHP command line binary, like /usr/bin/php.';
    $binary = Settings::get('upgrade_status_php_binary');
    $from_settings = !empty($binary);
    if (!$from_settings) {
      $binary = (new PhpExecutableFinder())->find(FALSE);
      if ($binary === FALSE) {
        throw new \Exception('The PHP binary was not found. ' . $help);
      }
    }

    // On the command line, the binary that runs this code needs no check.
    if (PHP_SAPI === 'cli' && $binary === PHP_BINARY) {
      return $binary;
    }

    // Running the binary takes a while, and the report page prepares the
    // environment on every view. So the version of a binary is kept for a
    // day, until the file of the binary changes.
    $cid = 'upgrade_status:php_binary:' . hash('sha256', implode(':', [$binary, @filemtime($binary), @filesize($binary)]));
    $version = $this->cache->get($cid)->data ?? NULL;
    if (!is_string($version)) {
      // A CGI binary prints HTTP headers first, and other binaries print
      // something else, so this only gives a version for command line PHP.
      $process = new Process([$binary, '-r', 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;']);
      $process->run();
      $version = trim($process->getOutput());
      if (!$process->isSuccessful() || !preg_match('/^\d+\.\d+$/', $version)) {
        throw new \Exception('The PHP binary ' . $binary . ' is not a working PHP command line binary. ' . $help);
      }
      $this->cache->set($cid, $version, time() + 86400);
    }

    // Another PHP version may not run PHPStan or the site's code. A binary
    // from settings.php is used as it is.
    $site_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    if (!$from_settings && $version !== $site_version) {
      throw new \Exception('The PHP binary ' . $binary . ' is PHP ' . $version . ', but the site runs on PHP ' . $site_version . '. ' . $help);
    }
    return $binary;
  }

  /**
   * Runs PHPStan on a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to analyze.
   * @param array $options
   *   Options for the analysis. Only the phpstan-memory-limit key is used
   *   with a default value of 1500M.
   *
   * @return array
   *   PHPStan's JSON result as an array, with the files, their messages and
   *   the totals. Each message has the analyzer key. If PHPStan failed, the
   *   output is a message of the "PHPStan failed" file. So is each error of
   *   PHPStan that is not about a file. The totals have
   *   upgrade_status_test_classes_skipped too.
   */
  public function analyze(Extension $extension, array $options = []): array {
    $project_dir = DRUPAL_ROOT . '/' . $extension->getPath();
    $this->logger->notice('Processing %path.', ['%path' => $project_dir]);

    // PHPStan takes time to start, also when it finds nothing to analyze.
    // Most recipes and some themes have no PHP code.
    if (!$this->hasPhpFiles($project_dir)) {
      return [
        'files' => [],
        'errors' => [],
        'totals' => [
          'errors' => 0,
          'file_errors' => 0,
          'upgrade_status_test_classes_skipped' => FALSE,
        ],
      ];
    }

    // Test classes extend PHPUnit classes. Without PHPUnit, PHPStan reports
    // false errors in them, like undefined assert methods, so they are not
    // checked.
    $neon_path = $this->phpstanNeonPath;
    $skip_test_classes = !$this->isPhpUnitInstalled() && $this->hasTestClasses($project_dir);
    if ($skip_test_classes) {
      $neon_path = $this->createTestClassExcludingNeonFile($extension->getName(), $project_dir);
    }

    $memory_limit = $options['phpstan-memory-limit'] ?? '1500M';
    $command = [
      $this->phpPath,
      $this->phpstanPath,
      'analyse',
      '--memory-limit=' . $memory_limit,
      '--error-format=json',
      '--configuration=' . $neon_path,
      $project_dir,
    ];

    $process = new Process($command, DRUPAL_ROOT, NULL, NULL, NULL);
    $this->restoreResultCache($extension->getName());
    $process->run();
    $this->keepResultCache($extension->getName());

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
      $formatted_error = 'PHPStan command failed: ' . implode(' ', $command) . "\nCommand output: " . $stdout . "\nCommand error: " . $stderr;
      $this->logger->error('%phpstan_fail', ['%phpstan_fail' => $formatted_error]);
      // The log has the full paths. The results are exported and shared, so
      // the message in them does not show where the site is on the server.
      $formatted_error = $this->hidePaths($formatted_error);
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
      else {
        // Any other error means that PHPStan did not finish, for example
        // because of an internal error, or a worker process without enough
        // memory when PHPStan runs with more than one process. PHPStan then
        // leaves out the messages of all files. Without a message for the
        // error, the result is the same as for a project with no problems
        // in its PHP code.
        $this->logger->error('PHPStan error for %path, results may be missing: %error', [
          '%path' => $project_dir,
          '%error' => $error,
        ]);
        $json['totals']['file_errors']++;
        $json['files']['PHPStan failed']['messages'][] = [
          'message' => 'PHPStan error, results may be missing: ' . $this->hidePaths(trim($error)),
          'line' => 0,
        ];
      }
    }

    // Add analyzer info.
    foreach ($json['files'] as &$errors) {
      foreach ($errors['messages'] as &$error) {
        $error['analyzer'] = 'PHPStan';
      }
    }
    $json['totals']['upgrade_status_test_classes_skipped'] = $skip_test_classes;
    return $json;
  }

  /**
   * Replaces the paths of the site on the server in a text.
   *
   * @param string $text
   *   The text, for example the output of PHPStan.
   *
   * @return string
   *   The text with a name in place of the temporary directory, the Drupal
   *   root and the project root.
   */
  protected function hidePaths(string $text): string {
    return strtr($text, [
      $this->temporaryDirectory => '[temporary directory]',
      DRUPAL_ROOT => '[Drupal root]',
      dirname($this->vendorPath) => '[project root]',
    ]);
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
    $this->prepareOwnDirectory($this->temporaryDirectory);
    $this->prepareOwnDirectory($this->temporaryDirectory . '/phpstan');
  }

  /**
   * Creates a directory for this user only, or checks an existing one.
   *
   * The name of the directory can be guessed. On a server with more users,
   * another user could create it first, and then change the PHPStan
   * configuration or cache in it. So the directory has to belong to the
   * current user, and only that user gets access to it.
   *
   * @param string $directory
   *   The path of the directory.
   *
   * @throws \Exception
   *   If the directory cannot be created or is not safe to use.
   */
  protected function prepareOwnDirectory(string $directory): void {
    if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
      throw new \Exception('The temporary directory for Upgrade Status at ' . $directory . ' is not a directory. Remove it.');
    }
    // Without the POSIX functions the user is not known, and the directory
    // has no user in its name. Then it keeps the default permissions, so the
    // web server and the command line user can both write to it.
    $own = function_exists('posix_geteuid');
    if (!is_dir($directory) && !$this->fileSystem->mkdir($directory, $own ? 0700 : NULL, TRUE)) {
      throw new \Exception('Unable to create temporary directory for Upgrade Status at ' . $directory);
    }
    if ($own) {
      if (fileowner($directory) !== posix_geteuid()) {
        throw new \Exception('The temporary directory for Upgrade Status at ' . $directory . ' belongs to another user. Remove it.');
      }
      // Earlier versions created the directory with access for the group.
      if ((fileperms($directory) & 0077) && !$this->fileSystem->chmod($directory, 0700)) {
        throw new \Exception('Unable to limit access to the temporary directory for Upgrade Status at ' . $directory);
      }
    }
    elseif (!is_writable($directory)) {
      throw new \Exception('The temporary directory for Upgrade Status at ' . $directory . ' is not writable.');
    }
  }

  /**
   * Writes a PHPStan configuration file.
   *
   * The file is written under a new name and then renamed. So a link at the
   * path is replaced and not followed, and PHPStan never reads a file that is
   * only partly written.
   *
   * @param string $path
   *   The path of the file.
   * @param string $config
   *   The configuration.
   *
   * @throws \Exception
   *   If the file cannot be written.
   */
  protected function writeNeonFile(string $path, string $config): void {
    // The file is the same on most uses.
    if (is_file($path) && !is_link($path) && file_get_contents($path) === $config) {
      return;
    }
    $temporary = $path . '.' . bin2hex(random_bytes(6));
    if (!file_put_contents($temporary, $config) || !rename($temporary, $path)) {
      @unlink($temporary);
      throw new \Exception('Unable to write configuration for PHPStan to ' . $path . '.');
    }
  }

  /**
   * Quotes a string for a NEON file.
   *
   * @param string $value
   *   The string, like a path.
   *
   * @return string
   *   The string in single quotes, with the single quotes in it doubled.
   */
  protected static function quoteNeon(string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
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
    // phpstan-drupal loads core's include and module files when PHPStan starts,
    // so PHPStan knows their constants from PHP and does not see the deprecated
    // tags on them. With these files in scanFiles, it reports uses of
    // REQUIREMENT_ERROR, REGIONS_ALL and other deprecated constants.
    $scan_files = '';
    foreach (['/core/includes/*.inc', '/core/modules/*/*.module', '/core/modules/*/*.inc'] as $pattern) {
      foreach (glob(DRUPAL_ROOT . $pattern) ?: [] as $file) {
        $scan_files .= "\n\t\t- " . static::quoteNeon($file);
      }
    }
    $config = str_replace(
      'parameters:',
      "parameters:\n\ttmpDir: " . static::quoteNeon($this->temporaryDirectory . '/phpstan') . ($scan_files ? "\n\tscanFiles:" . $scan_files : ''),
      $config
    );

    if (!class_exists('PHPStan\ExtensionInstaller\GeneratedConfig')) {
      $extension_neon = $this->vendorPath . '/mglaman/phpstan-drupal/extension.neon';
      $rules_neon = $this->vendorPath . '/phpstan/phpstan-deprecation-rules/rules.neon';
      if (!file_exists($extension_neon) || !file_exists($rules_neon)) {
        throw new \Exception('Vendor source files were not found. You may need to configure a vendor-dir in composer.json. See https://getcomposer.org/doc/06-config.md#vendor-dir. Missing ' . $extension_neon . ' and ' . $rules_neon . '.');
      }
      $config .= "\nincludes:\n\t- " . static::quoteNeon($extension_neon) . "\n\t- " . static::quoteNeon($rules_neon) . "\n";

      // phpstan-drupal 1.1.16 introduced a new rules.neon file, include it if
      // it exists. phpstan-drupal 1.1.4 and earlier are the only versions that
      // still support PHP 7.3 and earlier, and this file does not exist there.
      $drupal_rules_neon = $this->vendorPath . '/mglaman/phpstan-drupal/rules.neon';
      if (file_exists($drupal_rules_neon)) {
        $config .= "\t- " . static::quoteNeon($drupal_rules_neon) . "\n";
      }
    }

    $this->writeNeonFile($this->phpstanNeonPath, $config);
  }

  /**
   * Checks whether PHPUnit is installed.
   *
   * @return bool
   *   TRUE if the PHPUnit classes can be loaded.
   */
  protected function isPhpUnitInstalled(): bool {
    return $this->phpUnitInstalled ??= class_exists(TestCase::class);
  }

  /**
   * Checks whether a project has test classes.
   *
   * @param string $project_dir
   *   The directory of the project.
   *
   * @return bool
   *   TRUE if there is a PHP file in a tests/src directory of the project or
   *   its submodules.
   */
  protected function hasTestClasses(string $project_dir): bool {
    return $this->hasFile(
      $project_dir,
      ['node_modules', 'vendor'],
      static fn (string $path): bool => str_ends_with($path, '.php') && str_contains($path, '/tests/src/'),
    );
  }

  /**
   * Checks whether a project has files that PHPStan analyzes.
   *
   * @param string $project_dir
   *   The directory of the project.
   *
   * @return bool
   *   TRUE if there is a file with one of the extensions that PHPStan
   *   analyzes, outside of the directories it is set to leave out.
   */
  protected function hasPhpFiles(string $project_dir): bool {
    // PHP files and the extensions that phpstan-drupal adds.
    $extensions = ['php', 'module', 'theme', 'inc', 'install', 'profile', 'engine'];
    return $this->hasFile(
      $project_dir,
      // The same as excludePaths in the configuration template.
      ['node_modules', 'bower_components'],
      static fn (string $path): bool => in_array(pathinfo($path, PATHINFO_EXTENSION), $extensions, TRUE),
    );
  }

  /**
   * Checks whether a directory has a file that matches.
   *
   * This looks at the files that PHPStan finds: hidden files and directories
   * are left out, and links to directories are followed.
   *
   * @param string $directory
   *   The directory to look in.
   * @param list<string> $skip
   *   The names of directories to leave out.
   * @param callable(string): bool $matches
   *   Gets the path of a file, and returns whether the file matches.
   *
   * @return bool
   *   TRUE if a file in the directory or in the directories below it matches.
   */
  protected function hasFile(string $directory, array $skip, callable $matches): bool {
    foreach ($this->fileFinder->getNames($directory, FALSE) as $name) {
      if ($name[0] === '.') {
        continue;
      }
      $path = "$directory/$name";
      if ($this->fileFinder->isDirectory($path)) {
        if (!in_array($name, $skip, TRUE) && $this->hasFile($path, $skip, $matches)) {
          return TRUE;
        }
      }
      elseif ($matches($path)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the paths of the result cache of PHPStan and of the copy of a project.
   *
   * PHPStan keeps one result cache, and only uses it when it analyzes the
   * same paths as in the run before. Each project is analyzed on its own, so
   * the cache would only help when the same project is analyzed twice in a
   * row. With a copy for each project, analyzing all projects again only
   * takes long for the projects that changed. The cache of a large project
   * has many megabytes, so the copies are compressed.
   *
   * @param string $name
   *   The name of the project.
   *
   * @return string[]
   *   The path of the result cache of PHPStan, and the path of the copy for
   *   the project.
   */
  protected function getResultCachePaths(string $name): array {
    $directory = $this->temporaryDirectory . '/phpstan';
    // The name of a recipe is the name of its directory, with any characters.
    return [
      $directory . '/resultCache.php',
      $directory . '/resultCache-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name) . '.php.gz',
    ];
  }

  /**
   * Puts the result cache of a project in place before PHPStan runs.
   *
   * @param string $name
   *   The name of the project.
   */
  protected function restoreResultCache(string $name): void {
    [$cache, $project_cache] = $this->getResultCachePaths($name);
    // The copy gets its final name when it is complete, so PHPStan never
    // reads a part of it. PHPStan checks the cache before using it, so the
    // cache of another project in its place only makes this run slower.
    $temporary = $cache . '.' . bin2hex(random_bytes(6));
    if (!extension_loaded('zlib') || !file_exists($project_cache) || !@copy('compress.zlib://' . $project_cache, $temporary) || !@rename($temporary, $cache)) {
      @unlink($temporary);
      @unlink($cache);
    }
  }

  /**
   * Keeps the result cache of a project after PHPStan ran.
   *
   * @param string $name
   *   The name of the project.
   */
  protected function keepResultCache(string $name): void {
    [$cache, $project_cache] = $this->getResultCachePaths($name);
    if (!extension_loaded('zlib') || !file_exists($cache)) {
      return;
    }
    $temporary = $project_cache . '.' . bin2hex(random_bytes(6));
    if (!@copy($cache, 'compress.zlib://' . $temporary) || !@rename($temporary, $project_cache)) {
      @unlink($temporary);
    }
  }

  /**
   * Creates a PHPStan configuration file that skips the test classes.
   *
   * @param string $name
   *   The name of the project.
   * @param string $project_dir
   *   The directory of the project.
   *
   * @return string
   *   The path of the configuration file.
   *
   * @throws \Exception
   *   If the PHPStan configuration file cannot be written.
   */
  protected function createTestClassExcludingNeonFile(string $name, string $project_dir): string {
    // The name of a recipe is the name of its directory, with any characters.
    $path = $this->temporaryDirectory . '/deprecation_testing-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name) . '.neon';
    $config = "includes:\n\t- " . static::quoteNeon($this->phpstanNeonPath) . "\nparameters:\n\texcludePaths:\n\t\t- " . static::quoteNeon($project_dir . '/tests/src/*') . "\n\t\t- " . static::quoteNeon($project_dir . '/*/tests/src/*') . "\n";
    $this->writeNeonFile($path, $config);
    return $path;
  }

}
