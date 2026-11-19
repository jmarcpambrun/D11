<?php

namespace Drupal\upgrade_status;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\ClientInterface;

/**
 * Finds updates of Upgrade Status and the PHPStan packages it runs.
 *
 * An older PHPStan, phpstan-drupal or deprecation rules release can miss
 * deprecations that a newer one finds. Updates of Upgrade Status come from
 * the update module's data. Releases of the PHPStan packages are fetched from
 * Packagist when the update module checks for updates: on cron, at the same
 * interval, and when available updates are checked manually. Set
 * $settings['upgrade_status_check_tool_updates'] to FALSE in settings.php to
 * turn the check off.
 */
final class ToolUpdateChecker {

  use StringTranslationTrait;

  /**
   * The Composer packages that Upgrade Status runs to find deprecations.
   */
  private const PACKAGES = [
    'phpstan/phpstan',
    'mglaman/phpstan-drupal',
    'phpstan/phpstan-deprecation-rules',
  ];

  /**
   * The names and release notes of the analysis tools, by package.
   */
  public const TOOLS = [
    'drupal/upgrade_status' => ['Upgrade Status', 'https://www.drupal.org/project/upgrade_status/releases'],
    'phpstan/phpstan' => ['PHPStan', 'https://github.com/phpstan/phpstan/releases'],
    'mglaman/phpstan-drupal' => ['phpstan-drupal', 'https://github.com/mglaman/phpstan-drupal/releases'],
    'phpstan/phpstan-deprecation-rules' => [
      'phpstan-deprecation-rules',
      'https://github.com/phpstan/phpstan-deprecation-rules/releases',
    ],
  ];

  /**
   * Constructs a new tool update checker.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueExpirable
   *   The expirable key/value factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   *   The key/value factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
    private readonly KeyValueFactoryInterface $keyValue,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Gets the available updates of Upgrade Status and the PHPStan packages.
   *
   * This only reads release data that was fetched before, so it does not
   * slow down pages and commands.
   *
   * @return array<string, array{string, string}>
   *   The installed and the available version, keyed by the Composer package
   *   name.
   */
  public function getUpdates(): array {
    if (!Settings::get('upgrade_status_check_tool_updates', TRUE)) {
      return [];
    }
    $updates = [];

    // Upgrade Status itself, from the releases that the update module
    // fetched from drupal.org.
    $installed = $this->getModuleVersion();
    $releases = $this->keyValueExpirable->get('update_available_releases')->get('upgrade_status')['releases'] ?? [];
    $versions = [];
    foreach (is_array($releases) ? $releases : [] as $release) {
      if (is_array($release) && !empty($release['version']) && ($release['status'] ?? 'published') === 'published') {
        $versions[] = ['version' => $release['version'], 'core' => $release['core_compatibility'] ?? NULL];
      }
    }
    if ($available = self::findUpdate($versions, $installed)) {
      $updates['drupal/upgrade_status'] = [$installed, $available];
    }

    // The PHPStan packages, from the releases fetched from Packagist.
    $package_releases = $this->getStore()->get('releases', []);
    foreach (self::PACKAGES as $package) {
      if (!InstalledVersions::isInstalled($package)) {
        continue;
      }
      $installed = (string) InstalledVersions::getPrettyVersion($package);
      if ($available = self::findUpdate($package_releases[$package] ?? [], $installed)) {
        $updates[$package] = [$installed, $available];
      }
    }
    return $updates;
  }

  /**
   * Gets the installed versions of Upgrade Status and the PHPStan packages.
   *
   * Results are stored with these versions, and reports show them, because
   * other versions can find other problems.
   *
   * @return array<string, string>
   *   The versions keyed by the Composer package name, Upgrade Status first.
   *   The version of Upgrade Status is an empty string if it is not known,
   *   like in a Git checkout. PHPStan packages that are not installed are
   *   left out.
   */
  public function getInstalledVersions(): array {
    $versions = ['drupal/upgrade_status' => $this->getModuleVersion()];
    foreach (self::PACKAGES as $package) {
      if (InstalledVersions::isInstalled($package)) {
        $versions[$package] = (string) InstalledVersions::getPrettyVersion($package);
      }
    }
    return $versions;
  }

  /**
   * Tells if results were made with other versions of the analysis tools.
   *
   * @param array|null $versions
   *   The versions key of stored results or of their totals.
   *
   * @return bool
   *   TRUE if the versions are known and are not the installed ones. FALSE
   *   also for results from before the versions were stored.
   */
  public function isOtherThanInstalled(?array $versions): bool {
    return is_array($versions) && $versions != $this->getInstalledVersions();
  }

  /**
   * Formats versions of the analysis tools as a list in a sentence.
   *
   * @param array<string, string> $versions
   *   The versions from ::getInstalledVersions(), or from stored results.
   *
   * @return string
   *   The names and versions, like "Upgrade Status 5.0.0, PHPStan 2.1.0". Not
   *   escaped.
   */
  public function formatVersions(array $versions): string {
    $list = [];
    foreach ($versions as $package => $version) {
      $label = self::TOOLS[$package][0] ?? $package;
      $list[] = $version === '' ?
        (string) $this->t('@tool (version not known)', ['@tool' => $label]) :
        $label . ' ' . $version;
    }
    return implode(', ', $list);
  }

  /**
   * Gets the installed version of Upgrade Status.
   *
   * @return string
   *   The version from the info file, or else from Composer. An empty string
   *   if neither has one.
   */
  private function getModuleVersion(): string {
    $version = (string) ($this->moduleExtensionList->getExtensionInfo('upgrade_status')['version'] ?? '');
    if ($version === '' && InstalledVersions::isInstalled('drupal/upgrade_status')) {
      $version = (string) InstalledVersions::getPrettyVersion('drupal/upgrade_status');
    }
    return $version;
  }

  /**
   * Formats the available updates as a message.
   *
   * @param array $updates
   *   The updates from ::getUpdates().
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message, with the Composer command in a code element.
   */
  public function formatUpdates(array $updates): TranslatableMarkup {
    $list = [];
    foreach ($updates as $package => [$installed, $available]) {
      $list[] = $this->t('@package @available (@installed installed)', [
        '@package' => $package,
        '@available' => $available,
        '@installed' => $installed,
      ]);
    }
    return $this->t('Newer versions of Upgrade Status or the analysis tools are available: @list. Some deprecations are not reported with older versions. Update with <code>@command</code>.', [
      '@list' => implode(', ', $list),
      '@command' => $this->getUpdateCommand($updates),
    ]);
  }

  /**
   * Gets the Composer command to install the available updates.
   *
   * @param array $updates
   *   The updates from ::getUpdates().
   *
   * @return string
   *   The command.
   */
  public function getUpdateCommand(array $updates): string {
    return 'composer update ' . implode(' ', array_keys($updates)) . ' --with-dependencies';
  }

  /**
   * Finds the newest version to update to in the same major version.
   *
   * @param array $versions
   *   Versions, each with a 'version' key, and optional 'php' and 'core' keys
   *   with the PHP and Drupal core versions they need.
   * @param string $installed
   *   The installed version. Development versions are not compared.
   *
   * @return string|null
   *   The newest version in the same major version as the installed one, if
   *   it is newer and works with this site's PHP and Drupal core versions.
   *   Pre-releases are only suggested if the installed version is one.
   */
  public static function findUpdate(array $versions, string $installed): ?string {
    $normalize = static fn (string $version): string => preg_replace('/^(v|\d+\.x-)/', '', $version);
    $pre_release = static fn (string $version): bool => (bool) preg_match('/-(alpha|beta|rc)/i', $version);
    $installed = $normalize($installed);
    if ($installed === '' || str_contains($installed, 'dev') || !preg_match('/^(\d+)\./', $installed, $major)) {
      return NULL;
    }
    $newest = NULL;
    foreach ($versions as $candidate) {
      $version = $normalize((string) ($candidate['version'] ?? ''));
      if (!str_starts_with($version, $major[1] . '.') || str_contains($version, 'dev') || ($pre_release($version) && !$pre_release($installed))) {
        continue;
      }
      if (version_compare($version, $installed, '<=') || ($newest !== NULL && version_compare($version, $newest, '<='))) {
        continue;
      }
      try {
        if ((!empty($candidate['php']) && !Semver::satisfies(PHP_VERSION, $candidate['php'])) || (!empty($candidate['core']) && !Semver::satisfies(\Drupal::VERSION, $candidate['core']))) {
          continue;
        }
      }
      catch (\UnexpectedValueException) {
        continue;
      }
      $newest = $version;
    }
    return $newest;
  }

  /**
   * Fetches the releases of the PHPStan packages if the update module did.
   *
   * Runs on cron, at the interval that the update module checks for updates.
   */
  public function refreshIfDue(): void {
    $interval = 60 * 60 * 24 * $this->configFactory->get('update.settings')->get('check.interval_days');
    if ($this->time->getRequestTime() - $this->getStore()->get('last_check', 0) > $interval) {
      $this->refresh();
    }
  }

  /**
   * Fetches the releases of the installed PHPStan packages from Packagist.
   *
   * Releases fetched before are kept for a package that Packagist does not
   * return.
   */
  public function refresh(): void {
    if (!Settings::get('upgrade_status_check_tool_updates', TRUE)) {
      return;
    }
    $store = $this->getStore();
    $releases = $store->get('releases', []);
    foreach (self::PACKAGES as $package) {
      if (InstalledVersions::isInstalled($package) && ($versions = $this->fetchPackagistVersions($package)) !== NULL) {
        $releases[$package] = $versions;
      }
    }
    $store->setMultiple([
      'releases' => $releases,
      'last_check' => $this->time->getRequestTime(),
    ]);
  }

  /**
   * Batch operation to fetch the releases of the PHPStan packages.
   *
   * Added to the batch of the update module's manual check.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see \Drupal\upgrade_status\Hook\UpgradeStatusHooks::batchAlter()
   */
  public static function refreshBatch(&$context): void {
    \Drupal::service('upgrade_status.tool_update_checker')->refresh();
    $context['finished'] = 1;
  }

  /**
   * Gets the key/value store of the fetched releases.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   The store, with the releases of each package under 'releases' and the
   *   time of the last check under 'last_check'.
   */
  private function getStore(): KeyValueStoreInterface {
    return $this->keyValue->get('upgrade_status_tool_updates');
  }

  /**
   * Fetches the versions of a package from Packagist.
   *
   * @param string $package
   *   The Composer package name.
   *
   * @return array|null
   *   Versions with 'version' and 'php' keys, or NULL if Packagist could not
   *   be reached.
   */
  private function fetchPackagistVersions(string $package): ?array {
    try {
      $response = $this->httpClient->request('GET', 'https://repo.packagist.org/p2/' . $package . '.json', ['timeout' => 5]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Exception) {
      return NULL;
    }
    if (!is_array($data['packages'][$package] ?? NULL)) {
      return NULL;
    }
    $versions = [];
    // The metadata is minified: each version only lists the keys that differ
    // from the version before it, and "__unset" removes a key.
    $expanded = [];
    foreach ($data['packages'][$package] as $version) {
      foreach (is_array($version) ? $version : [] as $key => $value) {
        if ($value === '__unset') {
          unset($expanded[$key]);
        }
        else {
          $expanded[$key] = $value;
        }
      }
      if (!empty($expanded['version'])) {
        $versions[] = ['version' => $expanded['version'], 'php' => $expanded['require']['php'] ?? NULL];
      }
    }
    return $versions;
  }

}
