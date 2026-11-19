<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Checks the Drupal core and hosting environment for the next major version.
 *
 * The results are plain data, so the report page and the report command can
 * show them in their own formats.
 */
class EnvironmentChecker {

  use StringTranslationTrait;

  /**
   * Available releases store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $releaseStore;

  /**
   * Constructs a \Drupal\upgrade_status\EnvironmentChecker.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable
   *   The expirable key/value storage.
   * @param \Drupal\upgrade_status\ProjectCollector $projectCollector
   *   The project collector.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Update\UpdateHookRegistry $updateHookRegistry
   *   The registry of hook_update_N() functions.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   *   The key/value store factory.
   * @param string $root
   *   The path of the Drupal root.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly ModuleHandlerInterface $moduleHandler,
    KeyValueExpirableFactoryInterface $key_value_expirable,
    protected readonly ProjectCollector $projectCollector,
    protected readonly RequestStack $requestStack,
    protected readonly UpdateHookRegistry $updateHookRegistry,
    protected readonly ThemeHandlerInterface $themeHandler,
    protected readonly KeyValueFactoryInterface $keyValue,
    protected readonly string $root,
  ) {
    $this->releaseStore = $key_value_expirable->get('update_available_releases');
  }

  /**
   * Checks the environment for the next major Drupal version.
   *
   * @return array
   *   An array with these keys:
   *   - description: Explains the environment requirements on a high level.
   *   - status: TRUE if all checks passed, FALSE if one failed, NULL if there
   *     are no checks yet.
   *   - checks: A list of checks. Each check is an array with these keys:
   *     - requirement: The requirement as a sentence. It may contain HTML.
   *     - name: What the requirement is about, like "PHP".
   *     - qualifier: When the requirement applies or how strict it is, or
   *       NULL.
   *     - expected: The value that the next major version needs. It may
   *       contain HTML.
   *     - help: More about the requirement and how to meet it, or NULL. It
   *       may contain HTML.
   *     - status: The status of this site. It may contain HTML.
   *     - items: The values of the status as a list, to show them as a list
   *       instead of the status, or NULL. They may contain HTML.
   *     - url: A URL that the status links to, or NULL.
   *     - severity: 'success', 'warning', 'error', or NULL if the check could
   *       not be done.
   *   - warnings: A list of messages about the upgrade path.
   */
  public function getChecks(): array {
    $next_major = ProjectCollector::getDrupalCoreMajorVersion() + 1;
    if ($next_major == 11) {
      return $this->getChecksFor11();
    }
    if ($next_major == 12) {
      return $this->getChecksFor12();
    }
    return $this->getChecksWithoutRequirements($next_major);
  }

  /**
   * Gets the checks for a Drupal version without known requirements.
   *
   * @param int $next_major
   *   The next major version of Drupal core.
   *
   * @return array
   *   See ::getChecks().
   */
  protected function getChecksWithoutRequirements(int $next_major): array {
    return [
      'description' => $this->t('Drupal @version environment requirements are not yet defined.', ['@version' => $next_major]),
      // Checks neither passed, nor failed.
      'status' => NULL,
      'checks' => [],
      'warnings' => [],
    ];
  }

  /**
   * Checks the environment for Drupal 12 compatibility.
   *
   * @return array
   *   See ::getChecks().
   */
  protected function getChecksFor12(): array {
    $status = TRUE;
    $checks = [];

    // Check Drupal version. Link to update if available.
    $checks[] = $this->checkCore('11.3.0', $status);

    // Check PHP version.
    $checks[] = $this->checkPhp(ProjectCollector::MINIMUM_PHP[12], $status);

    // Check the web server.
    $checks[] = $this->checkWebServer();

    // Check database version.
    $severity = 'success';
    $database_type = $this->database->databaseType();
    $version = $this->database->version();
    $addendum = '';
    $minimum = [
      'mysql' => ['MySQL or Percona Server', '8.0'],
      'mariadb' => ['MariaDB', '10.11'],
      'pgsql' => ['PostgreSQL', '18'],
      'sqlite' => ['SQLite', '3.45'],
    ];
    $key = ($database_type == 'mysql' && $this->database->isMariaDb()) ? 'mariadb' : $database_type;
    if (isset($minimum[$key])) {
      [$database_type_full_name, $minimum_version] = $minimum[$key];
      $requirement = $this->t('When using @database, the version should be at least @version.', [
        '@database' => $database_type_full_name,
        '@version' => $minimum_version,
      ]);
      $qualifier = $this->t('When using @database', ['@database' => $database_type_full_name]);
      $expected = $this->t('@version or later', ['@version' => $minimum_version]);
      if (version_compare($version, $minimum_version) < 0) {
        $status = FALSE;
        $severity = 'error';
      }
      if ($key == 'pgsql') {
        $requirement = $this->t('When using PostgreSQL, the version should be at least 18, <a href=":trgm">with the pg_trgm extension</a> created.', [':trgm' => 'https://www.postgresql.org/docs/10/pgtrgm.html']);
        $expected = $this->t('@version or later, <a href=":trgm">with the pg_trgm extension</a>', [
          '@version' => '18',
          ':trgm' => 'https://www.postgresql.org/docs/10/pgtrgm.html',
        ]);
        $has_trgm = $this->database->query("SELECT installed_version FROM pg_available_extensions WHERE name = 'pg_trgm'")->fetchField();
        $addendum = $has_trgm ? $this->t('with pg_trgm extension') : $this->t('without pg_trgm extension');
        if (!$has_trgm) {
          $status = FALSE;
          $severity = 'error';
        }
      }
    }
    else {
      // Databases from contributed drivers are not checked.
      $database_type_full_name = ucfirst($database_type);
      $requirement = $this->t('The database should be supported by Drupal 12.');
      $qualifier = NULL;
      $expected = $this->t('Supported by Drupal 12');
      $severity = NULL;
    }
    $checks[] = [
      'requirement' => $requirement,
      'name' => $this->t('Database'),
      'qualifier' => $qualifier,
      'expected' => $expected,
      'help' => NULL,
      'status' => trim($database_type_full_name . ' ' . $version . ' ' . $addendum),
      'items' => NULL,
      'url' => NULL,
      'severity' => $severity,
    ];

    // Check for deprecated database driver modules.
    $severity = 'success';
    $driver_status = $this->t('Not installed');
    if ($this->moduleHandler->moduleExists('mysql57')) {
      $severity = 'error';
      $status = FALSE;
      $driver_status = $this->t('<a href=":driver">MySQL 5.7 / MariaDB 10.3 driver (mysql57)</a> is installed and must be removed', [':driver' => 'https://www.drupal.org/project/mysql57']);
    }
    $checks[] = [
      'requirement' => $this->t('No deprecated database driver modules should be installed.'),
      'name' => $this->t('Database driver modules'),
      'qualifier' => NULL,
      'expected' => $this->t('No deprecated drivers'),
      'help' => NULL,
      'status' => $driver_status,
      'items' => NULL,
      'url' => NULL,
      'severity' => $severity,
    ];

    // Composer is recommended, so it does not change the overall status.
    $checks[] = [
      'requirement' => $this->t('Composer should be at least 2.9.3. This is recommended, not required.'),
      'name' => $this->t('Composer'),
      'qualifier' => $this->t('Recommended, not required'),
      'expected' => $this->t('@version or later', ['@version' => '2.9.3']),
      'help' => $this->t('Check with <code>$ composer --version</code>. Update with <code>$ composer self-update</code>.'),
      'status' => $this->t('Cannot be detected, check manually'),
      'items' => NULL,
      'url' => NULL,
      'severity' => NULL,
    ];

    // Check Drush.
    $checks[] = $this->checkDrush('14', $status);

    // Check for deprecated or obsolete core extensions.
    $checks[] = $this->checkCoreExtensions($status);

    // Check for database updates that did not run yet.
    $checks[] = $this->checkDatabaseUpdates($status);

    return [
      'description' => $this->t("Below are <a href=\":requirements\">Drupal 12's system requirements</a>. If you are working with multiple (dev, stage, live) environments, make sure to check the same requirements there.", [':requirements' => 'https://www.drupal.org/about/core/blog/announcing-drupal-1200-platform-requirements']),
      'status' => $status,
      'checks' => $checks,
      'warnings' => [],
    ];
  }

  /**
   * Checks the environment for Drupal 11 compatibility.
   *
   * @return array
   *   See ::getChecks().
   */
  protected function getChecksFor11(): array {
    $status = TRUE;
    $checks = [];
    $warnings = [];

    // Check Drupal version. Link to update if available.
    $checks[] = $this->checkCore('10.3.0', $status);
    if (version_compare(\Drupal::VERSION, '10.6.0') >= 0) {
      $warnings[] = $this->t('Drupal 11.0, 11.1, and 11.2 are not supported upgrades from Drupal 10.6. Make sure to upgrade to 11.3 at least!');
    }
    elseif (version_compare(\Drupal::VERSION, '10.5.0') >= 0) {
      $warnings[] = $this->t('Drupal 11.0 and 11.1 are not supported upgrades from Drupal 10.5. Make sure to upgrade to 11.2 at least!');
    }
    elseif (version_compare(\Drupal::VERSION, '10.4.0') >= 0) {
      $warnings[] = $this->t('Drupal 11.0 is not a supported upgrade from Drupal 10.4. Make sure to upgrade to 11.1 at least!');
    }

    // Check PHP version.
    $checks[] = $this->checkPhp(ProjectCollector::MINIMUM_PHP[11], $status);

    // Check the web server.
    $checks[] = $this->checkWebServer();

    // Check database version.
    $database_type = $this->database->databaseType();
    $version = $this->database->version();
    $addendum = '';
    // Other database types are not checked.
    $database_type_full_name = ucfirst($database_type);
    $requirement = '';
    $expected = NULL;
    $help = NULL;
    $severity = NULL;
    if ($database_type == 'mysql') {
      if ($this->database->isMariaDb()) {
        $database_type_full_name = 'MariaDB';
        $requirement = $this->t('When using MariaDB, the version should be at least 10.6.');
        $expected = $this->t('@version or later', ['@version' => '10.6']);
        if (version_compare($version, '10.6') >= 0) {
          $severity = 'success';
        }
        elseif (version_compare($version, '10.3.7') >= 0) {
          if ($this->moduleHandler->moduleExists('mysql57')) {
            $severity = 'warning';
            $help = $this->t('Keep using <a href=":driver">the MariaDB 10.3 driver</a> for now, which is already installed.', [':driver' => 'https://www.drupal.org/project/mysql57']);
          }
          else {
            $severity = 'error';
            $help = $this->t('Alternatively, <a href=":driver">install the MariaDB 10.3 driver</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql57']);
          }
        }
        else {
          // Should not happen because Drupal 10 already required 10.3.7,
          // but just to be sure.
          $status = FALSE;
          $severity = 'error';
          $help = $this->t('Once updated to at least 10.3.7, you can also <a href=":driver">install the MariaDB 10.3 driver</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql57']);
        }
      }
      else {
        $database_type_full_name = 'MySQL or Percona Server';
        $requirement = $this->t('When using MySQL or Percona Server, the version should be at least 8.0.');
        $expected = $this->t('@version or later', ['@version' => '8.0']);
        if (version_compare($version, '8.0') >= 0) {
          $severity = 'success';
        }
        elseif (version_compare($version, '5.7.8') >= 0) {
          if ($this->moduleHandler->moduleExists('mysql57')) {
            $severity = 'warning';
            $help = $this->t('Keep using <a href=":driver">the MySQL 5.7 driver</a> for now, which is already installed.', [':driver' => 'https://www.drupal.org/project/mysql57']);
          }
          else {
            $severity = 'error';
            $help = $this->t('Alternatively, <a href=":driver">install the MySQL 5.7 driver</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql57']);
          }
        }
        else {
          // Should not happen because Drupal 10 already required 5.7.8,
          // but just to be sure.
          $status = FALSE;
          $severity = 'error';
          $help = $this->t('Once updated to at least 5.7.8, you can also <a href=":driver">install the MySQL 5.7 driver</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql57']);
        }
      }
    }
    elseif ($database_type == 'pgsql') {
      $database_type_full_name = 'PostgreSQL';
      $requirement = $this->t('When using PostgreSQL, the version should be at least 16, <a href=":trgm">with the pg_trgm extension</a> created.', [':trgm' => 'https://www.postgresql.org/docs/10/pgtrgm.html']);
      $expected = $this->t('@version or later, <a href=":trgm">with the pg_trgm extension</a>', [
        '@version' => '16',
        ':trgm' => 'https://www.postgresql.org/docs/10/pgtrgm.html',
      ]);
      $has_trgm = $this->database->query("SELECT installed_version FROM pg_available_extensions WHERE name = 'pg_trgm'")->fetchField();
      if (version_compare($version, '16') >= 0 && $has_trgm) {
        $severity = 'success';
        $addendum = $this->t('with pg_trgm extension');
      }
      else {
        $status = FALSE;
        $severity = 'error';
        if (!$has_trgm) {
          $addendum = $this->t('without pg_trgm extension');
        }
      }
    }
    elseif ($database_type == 'sqlite') {
      $database_type_full_name = 'SQLite';
      $minimum_sqlite = '3.45';
      $requirement = $this->t('When using SQLite, the version should be at least @minimum_sqlite.', ['@minimum_sqlite' => $minimum_sqlite]);
      $expected = $this->t('@version or later', ['@version' => $minimum_sqlite]);
      if (version_compare($version, $minimum_sqlite) >= 0) {
        $severity = 'success';
      }
      else {
        $status = FALSE;
        $severity = 'error';
      }
    }
    $checks[] = [
      'requirement' => $requirement,
      'name' => $this->t('Database'),
      'qualifier' => $this->t('When using @database', ['@database' => $database_type_full_name]),
      'expected' => $expected,
      'help' => $help,
      'status' => trim($database_type_full_name . ' ' . $version . ' ' . $addendum),
      'items' => NULL,
      'url' => NULL,
      'severity' => $severity,
    ];

    // Check JSON support in database.
    $severity = 'success';
    $json_status = $this->t('Supported');
    if (!method_exists($this->database, 'hasJson') || !$this->database->hasJson()) {
      $severity = 'error';
      $status = FALSE;
      $json_status = $this->t('Not supported');
    }
    $checks[] = [
      'requirement' => $this->t('The database should support JSON.'),
      'name' => $this->t('Database JSON support'),
      'qualifier' => NULL,
      'expected' => $this->t('Supported'),
      'help' => NULL,
      'status' => $json_status,
      'items' => NULL,
      'url' => NULL,
      'severity' => $severity,
    ];

    // Check for deprecated or obsolete core extensions.
    $checks[] = $this->checkCoreExtensions($status);

    // Check Drush.
    $checks[] = $this->checkDrush('13', $status);

    // Check for database updates that did not run yet.
    $checks[] = $this->checkDatabaseUpdates($status);

    return [
      'description' => $this->t("Below are Drupal 11's system requirements. If you are working with multiple (dev, stage, live) environments, make sure to check the same requirements there."),
      'status' => $status,
      'checks' => $checks,
      'warnings' => $warnings,
    ];
  }

  /**
   * Checks the Drupal core version.
   *
   * @param string $minimum
   *   The minimum version of this major version that allows the upgrade, like
   *   11.3.0.
   * @param bool $status
   *   Set to FALSE if the check failed.
   *
   * @return array
   *   A check. See ::getChecks(). A newer release of this major version is a
   *   warning with a link to it.
   */
  protected function checkCore(string $minimum, bool &$status): array {
    $allows_upgrade = version_compare(\Drupal::VERSION, $minimum) >= 0;
    $core_status = $this->t('Version @version', ['@version' => \Drupal::VERSION]);
    $core_url = NULL;
    $core_update_info = $this->releaseStore->get('drupal');
    if (isset($core_update_info['releases']) && is_array($core_update_info['releases'])) {
      // Find the latest release of this major version that is higher than the
      // current one and is not beta/alpha/rc/dev.
      $major = explode('.', $minimum)[0];
      foreach ($core_update_info['releases'] as $version => $release) {
        $major_version = explode('.', $version)[0];
        if ($major_version === $major && !strpos($version, '-') && (version_compare($version, \Drupal::VERSION) > 0)) {
          $core_url = $core_update_info['link'] . '/releases/' . $version;
          $core_status = $allows_upgrade
            ? $this->t('Version @current allows upgrading, but @new is available', [
              '@current' => \Drupal::VERSION,
              '@new' => $version,
            ])
            : $this->t('Version @current does not allow upgrading, @new is available', [
              '@current' => \Drupal::VERSION,
              '@new' => $version,
            ]);
          break;
        }
      }
    }
    if ($allows_upgrade) {
      $severity = $core_url ? 'warning' : 'success';
    }
    else {
      $status = FALSE;
      $severity = 'error';
    }
    return [
      'requirement' => $this->t('Drupal core should be at least @version.', ['@version' => $minimum]),
      'name' => $this->t('Drupal core'),
      'qualifier' => NULL,
      'expected' => $this->t('@version or later', ['@version' => $minimum]),
      'help' => NULL,
      'status' => $core_status,
      'items' => NULL,
      'url' => $core_url,
      'severity' => $severity,
    ];
  }

  /**
   * Checks the PHP version.
   *
   * @param string $minimum_php
   *   The minimum PHP version.
   * @param bool $status
   *   The overall status. Set to FALSE if the check fails.
   *
   * @return array
   *   The check. See ::getChecks().
   */
  protected function checkPhp(string $minimum_php, bool &$status): array {
    $severity = 'success';
    if (version_compare(PHP_VERSION, $minimum_php) < 0) {
      $severity = 'error';
      $status = FALSE;
    }
    return [
      'requirement' => $this->t('PHP version should be at least @minimum_php.', ['@minimum_php' => $minimum_php]),
      'name' => $this->t('PHP'),
      'qualifier' => NULL,
      'expected' => $this->t('@version or later', ['@version' => $minimum_php]),
      'help' => $this->t('Before updating to PHP @minimum_php, use <code>$ composer why-not php @minimum_php</code> to check which projects are not compatible. Also check custom projects manually.', ['@minimum_php' => $minimum_php]),
      'status' => $this->t('Version @version', ['@version' => PHP_VERSION]),
      'items' => NULL,
      'url' => NULL,
      'severity' => $severity,
    ];
  }

  /**
   * Checks the web server of the current request.
   *
   * The minimum versions did not change between Drupal 10 and 12. The web
   * server is not known on the command line.
   *
   * @return array
   *   The check. See ::getChecks().
   */
  protected function checkWebServer(): array {
    $software = (string) $this->requestStack->getCurrentRequest()?->server->get('SERVER_SOFTWARE');
    $severity = NULL;
    if (preg_match('!^(Apache|nginx)/([0-9.]+)!i', $software, $matches)) {
      $minimum = strtolower($matches[1]) == 'nginx' ? '1.1' : '2.4.7';
      $severity = version_compare($matches[2], $minimum) >= 0 ? 'success' : 'error';
    }
    elseif (stripos($software, 'IIS') !== FALSE) {
      $severity = 'error';
    }
    return [
      'requirement' => $this->t('The web server should be Apache 2.4.7 or nginx 1.1 or later. IIS is not supported.'),
      'name' => $this->t('Web server'),
      'qualifier' => $this->t('IIS is not supported'),
      'expected' => $this->t('Apache 2.4.7 or nginx 1.1 or later'),
      'help' => NULL,
      'status' => $software ?: $this->t('Cannot be detected, check manually'),
      'items' => NULL,
      'url' => NULL,
      'severity' => $severity,
    ];
  }

  /**
   * Checks the version of Drush.
   *
   * @param string $minimum_drush
   *   The minimum major version of Drush.
   * @param bool $status
   *   The overall status. Set to FALSE if the check fails.
   *
   * @return array
   *   The check. See ::getChecks().
   */
  protected function checkDrush(string $minimum_drush, bool &$status): array {
    // Only Drush in the vendor directory of the site is detected.
    $severity = NULL;
    $drush_status = $this->t('Cannot be detected, check manually');
    if (class_exists('\\Drush\\Drush')) {
      $version = call_user_func('\\Drush\\Drush::getMajorVersion');
      $severity = version_compare($version, $minimum_drush) >= 0 ? 'success' : 'error';
      if ($severity == 'error') {
        $status = FALSE;
      }
      $drush_status = $this->t('Version @version', ['@version' => $version]);
    }
    return [
      'requirement' => $this->t('When using Drush, the version should be at least @minimum_drush.', ['@minimum_drush' => $minimum_drush]),
      'name' => $this->t('Drush'),
      'qualifier' => $this->t('When using Drush'),
      'expected' => $this->t('@version or later', ['@version' => $minimum_drush]),
      'help' => NULL,
      'status' => $drush_status,
      'items' => NULL,
      'url' => NULL,
      'severity' => $severity,
    ];
  }

  /**
   * Checks for database updates that did not run yet.
   *
   * The next major Drupal version removes the updates of earlier versions, so
   * all updates need to run before the upgrade. Like the database updates
   * check of the status report, this finds hook_update_N() functions newer
   * than the installed schema of each module, and hook_post_update_NAME()
   * functions that did not run.
   *
   * @param bool $status
   *   The overall status. Set to FALSE if the check fails.
   *
   * @return array
   *   The check. See ::getChecks().
   */
  protected function checkDatabaseUpdates(bool &$status): array {
    // The hook_update_N() functions are in the .install files. The registry
    // lists the functions of all modules on its first use, so all files are
    // loaded before that.
    $modules = array_keys($this->moduleHandler->getModuleList());
    foreach ($modules as $module) {
      $this->moduleHandler->loadInclude($module, 'install');
    }
    $count = 0;
    // One query for the versions of all modules, not one for each module.
    $versions = $this->updateHookRegistry->getAllInstalledVersions();
    foreach ($modules as $module) {
      $installed = $versions[$module] ?? UpdateHookRegistry::SCHEMA_UNINSTALLED;
      $count += count(array_filter($this->updateHookRegistry->getAvailableUpdates($module), fn ($version) => $version > $installed));
    }
    $count += count($this->getPendingPostUpdates());

    $severity = 'success';
    $updates_status = $this->t('None pending');
    $url = NULL;
    if ($count) {
      $severity = 'error';
      $status = FALSE;
      $updates_status = $this->formatPlural($count, '1 update pending', '@count updates pending');
      $url = Url::fromRoute('system.db_update')->setAbsolute()->toString();
    }
    return [
      'requirement' => $this->t('All database updates should run before the upgrade.'),
      'name' => $this->t('Database updates'),
      'qualifier' => NULL,
      'expected' => $this->t('None pending'),
      'help' => $this->t('The next major Drupal version removes the updates of earlier versions. Run the updates with <code>$ drush updatedb</code> or update.php.'),
      'status' => $updates_status,
      'items' => NULL,
      'url' => $url,
      'severity' => $severity,
    ];
  }

  /**
   * Lists the post update functions that did not run yet.
   *
   * The post update registry of Drupal core has the same list, but it looks
   * for extensions in the whole codebase on each request. That takes most of
   * the time of the report on a large codebase. The lists of the installed
   * modules and themes already have the paths of the files.
   *
   * @return list<string>
   *   The names of the functions.
   */
  protected function getPendingPostUpdates(): array {
    $extensions = $this->moduleHandler->getModuleList() + $this->themeHandler->listInfo();
    foreach ($extensions as $name => $extension) {
      $file = $this->root . '/' . $extension->getPath() . '/' . $name . '.post_update.php';
      if (is_file($file)) {
        include_once $file;
      }
    }
    $ran = array_flip($this->keyValue->get('post_update')->get('existing_updates', []));
    $pending = [];
    foreach (preg_grep('/_post_update_/', get_defined_functions()['user']) as $function) {
      // The name of the extension is the longest possible one, as in the
      // registry of Drupal core.
      if (preg_match('/^(?<extension>.+)_post_update_.+$/', $function, $matches) && isset($extensions[$matches['extension']]) && !isset($ran[$function])) {
        $pending[] = $function;
      }
    }
    return $pending;
  }

  /**
   * Checks for deprecated or obsolete core extensions.
   *
   * @param bool $status
   *   The overall status. Set to FALSE if the check fails.
   *
   * @return array
   *   The check. See ::getChecks().
   */
  protected function checkCoreExtensions(bool &$status): array {
    $severity = 'success';
    $extensions_status = $this->t('None installed');
    $deprecated_or_obsolete = $this->projectCollector->collectCoreDeprecatedAndObsoleteExtensions();
    if (!empty($deprecated_or_obsolete)) {
      $severity = 'error';
      $status = FALSE;
      $extensions_status = implode(', ', $deprecated_or_obsolete);
    }
    return [
      'requirement' => $this->t('No deprecated or obsolete core extensions should be installed.'),
      'name' => $this->t('Deprecated or obsolete core extensions'),
      'qualifier' => NULL,
      'expected' => $this->t('None installed'),
      'help' => $this->t('These are removed from the next major Drupal version.'),
      'status' => $extensions_status,
      'items' => $deprecated_or_obsolete ? array_values($deprecated_or_obsolete) : NULL,
      'url' => NULL,
      'severity' => $severity,
    ];
  }

}
