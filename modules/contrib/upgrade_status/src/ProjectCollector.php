<?php

namespace Drupal\upgrade_status;

use Composer\InstalledVersions;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Collects projects and their associated metadata collated for Upgrade Status.
 */
class ProjectCollector {

  use StringTranslationTrait;

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
   * Available updates store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface|mixed
   */
  protected $availableUpdates;

  /**
   * Configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current installation profile.
   *
   * @var string
   */
  private $installProfile;

  /**
   * Update not checked for a project.
   */
  const UPDATE_NOT_CHECKED = 0;

  /**
   * Update not available for a project.
   */
  const UPDATE_NOT_AVAILABLE = 1;

  /**
   * Update available for a project.
   */
  const UPDATE_AVAILABLE = 2;

  /**
   * The latest version is already being used.
   */
  const UPDATE_ALREADY_INSTALLED = 3;

  /**
   * The number of projects to load stored data for with one query.
   */
  protected const LOAD_CHUNK_SIZE = 50;

  /**
   * The minimum PHP version of each Drupal major version.
   *
   * @see https://www.drupal.org/docs/getting-started/system-requirements/php-requirements
   */
  const MINIMUM_PHP = [
    11 => '8.3.0',
    12 => '8.5.0',
  ];

  /**
   * The major Symfony version each major Drupal version requires.
   *
   * Only known versions are listed, so later Drupal versions are not checked.
   */
  const SYMFONY_MAJOR_VERSIONS = [
    11 => 7,
    12 => 8,
  ];

  /**
   * The major Twig version each major Drupal version requires.
   *
   * Only known versions are listed.
   */
  const TWIG_MAJOR_VERSIONS = [
    11 => 3,
    12 => 3,
  ];

  /**
   * Custom project.
   */
  const TYPE_CUSTOM = 'custom';

  /**
   * Contributed project.
   */
  const TYPE_CONTRIB = 'contrib';

  /**
   * Suggest to relax.
   */
  const NEXT_RELAX = 'relax';

  /**
   * Suggest to remove.
   */
  const NEXT_REMOVE = 'remove';

  /**
   * Suggest to update.
   */
  const NEXT_UPDATE = 'update';

  /**
   * Suggest to collaborate with maintainer.
   */
  const NEXT_COLLABORATE = 'collaborate';

  /**
   * Suggest to scan for errors.
   */
  const NEXT_SCAN = 'scan';

  /**
   * Suggest to fix with rector.
   */
  const NEXT_RECTOR = 'rector';

  /**
   * Suggest to fix with manually.
   */
  const NEXT_MANUAL = 'manual';

  /**
   * Suggest to update the requirements, with no known problems in the code.
   */
  const NEXT_REQUIREMENTS = 'requirements';

  /**
   * Constructs a \Drupal\upgrade_status\ProjectCollector.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list
   *   The theme extension handler service.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list
   *   The profile extension handler service.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable
   *   The expirable key/value storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param string $install_profile
   *   The current installation profile.
   * @param \Drupal\upgrade_status\ScanResultStorage $resultStorage
   *   The storage of the analysis results.
   */
  public function __construct(
    ModuleExtensionList $module_extension_list,
    ThemeExtensionList $theme_extension_list,
    ProfileExtensionList $profile_extension_list,
    KeyValueExpirableFactoryInterface $key_value_expirable,
    ConfigFactoryInterface $config_factory,
    $install_profile,
    protected ScanResultStorage $resultStorage,
  ) {
    $this->moduleExtensionList = $module_extension_list;
    $this->themeExtensionList = $theme_extension_list;
    $this->profileExtensionList = $profile_extension_list;
    $this->availableUpdates = $key_value_expirable->get('update_available_releases');
    $this->configFactory = $config_factory;
    $this->installProfile = $install_profile;
  }

  /**
   * Reset all extension lists so their data is regenerated.
   */
  public function resetLists() {
    $this->moduleExtensionList->reset();
    $this->themeExtensionList->reset();
    $this->profileExtensionList->reset();
  }

  /**
   * Collect projects of installed modules grouped by custom and contrib.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   An array keyed by project names. Extensions selected as projects
   *   without a defined project name get one based on their topmost parent
   *   extension and only that topmost extension gets included in the list.
   */
  public function collectProjects() {
    $projects = [];
    $modules = $this->moduleExtensionList->getList();
    $themes = $this->themeExtensionList->getList();
    $profiles = $this->profileExtensionList->getList();
    $extensions = array_merge($modules, $themes, $profiles);
    unset($modules, $themes, $profiles);
    $update_check_for_uninstalled = $this->configFactory->get('update.settings')->get('check.disabled_extensions');

    /** @var \Drupal\Core\Extension\Extension $extension */
    foreach ($extensions as $key => $extension) {

      if ($this->isCoreExtension($extension)) {
        // Ignore core extensions for the sake of upgrade status.
        continue;
      }

      // If the project is already specified in this extension, use that.
      $project = $extension->info['project'] ?? '';
      if (isset($projects[$project])) {
        // If we already have a representative of this project in the list,
        // don't add this extension.
        // @todo Make sure to use the extension with the shortest file path.
        // If the existing project was already Drupal 9 compatible, consider
        // this subcomponent as well. If this component was enabled, it would
        // affect how we consider the Drupal 9 compatibility.
        if (!empty($projects[$project]->info['upgrade_status_next_major_compatible']) && !empty($extension->status)) {
          // Overwrite compatibility. If this is still compatible, it will
          // keep being TRUE, otherwise FALSE.
          $projects[$project]->info['upgrade_status_next_major_compatible'] =
            isset($extension->info['core_version_requirement']) &&
            self::isCompatibleWithNextMajorDrupal($extension->info['core_version_requirement']);
        }
        continue;
      }

      if ((strpos($key, 'upgrade_status') === 0) && !drupal_valid_test_ua()) {
        // Don't add the Upgrade Status modules to the list if not in tests.
        // Upgrade status is a temporary site component and does have
        // intentional deprecated API use for the sake of testing. Avoid
        // distracting site owners with this.
        continue;
      }

      // Attempt to identify if the project was contrib based on the directory
      // structure it is in. Extension placement is not a mandatory requirement
      // and theoretically this could lead to false positives, but if
      // composer_deploy or git_deploy is not available (and/or did not
      // identify the project for us), this is all we can do. Ignore our test
      // modules for this scenario.
      if (empty($project)) {
        $type = self::TYPE_CUSTOM;
        if (strpos($extension->getPath(), '/contrib/') && (strpos($key, 'upgrade_status_test_') !== 0)) {
          $type = self::TYPE_CONTRIB;
        }
      }
      // Extensions that have the 'drupal' project but did not have the 'core'
      // origin assigned are custom extensions that are running in a Drupal
      // core git checkout, so also categorize them as custom.
      elseif ($project === 'drupal') {
        $type = self::TYPE_CUSTOM;
      }
      else {
        $type = self::TYPE_CONTRIB;
      }

      // Add additional information to the extension info for our tracking.
      // Keep this on a cloned extension object so we are not polluting runtime
      // extension information elsewhere.
      $extdata = clone $extension;
      $extdata->info['upgrade_status_type'] = $type;
      $extdata->info['upgrade_status_next_major_compatible'] =
        isset($extdata->info['core_version_requirement']) &&
        self::isCompatibleWithNextMajorDrupal($extdata->info['core_version_requirement']);

      // Save this as a possible project to consider.
      $projects[$key] = $extdata;
    }

    // Collate extensions to projects, removing sub-extensions.
    $projects = $this->collateExtensionsIntoProjects($projects);

    // Add recipes. An extension wins if a recipe has the same name, because
    // projects are identified by their names.
    $projects += $this->collectRecipes();

    // After the collation is done, assign project names based on the topmost
    // extension. While this is not always right for drupal.org projects, this
    // is the best guess we have.
    foreach ($projects as $name => $extension) {
      if (!isset($extension->info['project'])) {
        $projects[$name]->info['project'] = $name;
      }
    }

    // Add available update information to contrib projects found. The data
    // is loaded for some projects at a time: one query per project is slow on
    // sites with many projects, and the data of all projects at once needs a
    // lot of memory.
    $contrib = array_filter($projects, fn ($extension) => $extension->info['upgrade_status_type'] == self::TYPE_CONTRIB);
    foreach (array_chunk($contrib, self::LOAD_CHUNK_SIZE, TRUE) as $chunk) {
      // Look up by drupal.org project info not $name
      // because the two may be different.
      $updates = $this->availableUpdates->getMultiple(array_values(array_unique(array_map(fn ($extension) => $extension->info['project'], $chunk))));
      foreach ($chunk as $name => $extension) {
        $project_update = $updates[$extension->info['project']] ?? NULL;
        $release = self::getRecommendedRelease($project_update['releases'] ?? []);
        if ($release === NULL) {
          // Releases were either not checked or not available. The update
          // module stores an empty list when there are no releases.
          $projects[$name]->info['upgrade_status_update'] = $update_check_for_uninstalled ? self::UPDATE_NOT_AVAILABLE : self::UPDATE_NOT_CHECKED;
        }
        else {
          // Add next major compatibility info from the update's data.
          $projects[$name]->info['upgrade_status_update_compatible'] = self::isCompatibleWithNextMajorDrupalSafe($release['core_compatibility'] ?? '');
          // Denormalize update info into the extension info for our own use.
          if (self::isUpdate($extension->info['version'] ?? '', $release['version'])) {
            $projects[$name]->info['upgrade_status_update'] = self::UPDATE_AVAILABLE;
            $link = $project_update['link'] . '/releases/' . $release['version'];
            $projects[$name]->info['upgrade_status_update_link'] = $link;
            $projects[$name]->info['upgrade_status_update_version'] = $release['version'];
            $projects[$name]->info['upgrade_status_update_security'] = self::hasSecurityUpdate($project_update['releases'] ?? [], $extension->info['version'] ?? '');
          }
          else {
            // If the current version is already the latest, store that.
            $projects[$name]->info['upgrade_status_update'] = self::UPDATE_ALREADY_INSTALLED;
          }
        }
      }
    }

    // The next step of custom projects comes from the results of the analysis.
    $this->resultStorage->loadTotals(array_keys($projects));
    foreach ($projects as $name => $extension) {
      $scan_totals = $this->resultStorage->getTotals($name)['totals'] ?? [];

      // Pick a suggested next step for this project.
      if ($extension->info['upgrade_status_next_major_compatible'] && $extension->info['upgrade_status_type'] == self::TYPE_CONTRIB) {
        // If the project was contrib and already Drupal 9 compatible, relax.
        $extension->info['upgrade_status_next'] = self::NEXT_RELAX;
      }
      elseif (empty($extension->status) && ($name != $this->installProfile) && ($extension->getType() != 'recipe')) {
        // Uninstalled extensions should be removed. Except if this is the
        // profile. See https://www.drupal.org/project/drupal/issues/1170362
        // Recipes are applied, not installed, so this does not apply to them.
        $extension->info['upgrade_status_next'] = self::NEXT_REMOVE;
      }
      elseif (isset($extension->info['upgrade_status_update']) && $extension->info['upgrade_status_update'] == self::UPDATE_AVAILABLE) {
        // If there was a Drupal 9 compatible update or even a yet incompatible
        // update to this project, the best course of action is to update to
        // that, since that should move closer to Drupal 9 compatibility.
        $extension->info['upgrade_status_next'] = self::NEXT_UPDATE;
      }
      elseif ($extension->info['upgrade_status_type'] == self::TYPE_CONTRIB) {
        // For installed contributed modules that do not have
        // compatible updates, collaborate.
        $extension->info['upgrade_status_next'] = self::NEXT_COLLABORATE;
      }
      else {
        // If there was no scanning result yet, next step is to
        // scan this project.
        if (empty($scan_totals['upgrade_status_next'])) {
          $extension->info['upgrade_status_next'] = self::NEXT_SCAN;
        }
        // If there were scanning results, carry over the next step
        // suggestion from there.
        else {
          $extension->info['upgrade_status_next'] = $scan_totals['upgrade_status_next'];
        }
      }
    }
    return $projects;
  }

  /**
   * Collects recipes that are not part of Drupal core.
   *
   * Recipes are not extensions, so they are found in the directories Composer
   * installed them to, in the recipes directories of the usual project layouts
   * and in the directories listed in the upgrade_status_recipe_directories
   * setting.
   *
   * @return \Drupal\upgrade_status\RecipeExtension[]
   *   Recipes keyed by their directory names.
   */
  protected function collectRecipes(): array {
    // Use paths without symbolic links to find recipes found more than once,
    // but keep the paths as found to show them.
    $directories = [];
    $parents = array_merge(
      [DRUPAL_ROOT . '/recipes', dirname(DRUPAL_ROOT) . '/recipes'],
      Settings::get('upgrade_status_recipe_directories', []),
    );
    foreach ($parents as $parent) {
      foreach (glob($parent . '/*/recipe.yml') ?: [] as $file) {
        $directories[realpath(dirname($file))] ??= [self::normalizePath(dirname($file)), ''];
      }
    }
    // Composer 2.1 added the method to list packages by type.
    if (method_exists(InstalledVersions::class, 'getInstalledPackagesByType')) {
      foreach (InstalledVersions::getInstalledPackagesByType('drupal-recipe') as $package) {
        $path = InstalledVersions::getInstallPath($package);
        if ($path !== NULL && file_exists($path . '/recipe.yml')) {
          $directories[realpath($path)] ??= [self::normalizePath($path), ''];
          $directories[realpath($path)][1] = $package;
        }
      }
    }

    $recipes = [];
    $root = self::normalizePath(DRUPAL_ROOT);
    foreach ($directories as [$path, $package]) {
      if (str_starts_with($path, $root . '/core/')) {
        continue;
      }
      $recipe = new RecipeExtension(DRUPAL_ROOT, 'recipe', self::getRelativePath($root, $path) . '/recipe.yml');
      $name = $recipe->getName();
      if (isset($recipes[$name]) || ((strpos($name, 'upgrade_status') === 0) && !drupal_valid_test_ua())) {
        continue;
      }
      try {
        $definition = Yaml::decode(file_get_contents($path . '/recipe.yml'));
      }
      catch (InvalidDataTypeException $e) {
        $definition = [];
      }
      $composer_json = json_decode((string) @file_get_contents($path . '/composer.json'), TRUE);
      $core_requirement = $composer_json['require']['drupal/core'] ?? NULL;
      $recipe->info = [
        'name' => is_array($definition) && !empty($definition['name']) && is_string($definition['name']) ? $definition['name'] : $name,
        'type' => 'recipe',
        // Recipes from drupal.org are Composer packages named drupal/*.
        'upgrade_status_type' => str_starts_with($package, 'drupal/') ? self::TYPE_CONTRIB : self::TYPE_CUSTOM,
        // A recipe without a drupal/core requirement can be used with any
        // version of core. A requirement that is not a version constraint
        // counts as not compatible, so the recipe is listed to fix.
        'upgrade_status_next_major_compatible' => !is_string($core_requirement) || self::isCompatibleWithNextMajorDrupalSafe($core_requirement),
      ];
      if ($package !== '') {
        $recipe->info['version'] = InstalledVersions::getPrettyVersion($package);
        if (str_starts_with($package, 'drupal/')) {
          $recipe->info['project'] = substr($package, 7);
        }
      }
      $recipes[$name] = $recipe;
    }
    return $recipes;
  }

  /**
   * Removes "." and ".." parts from a path without resolving links.
   *
   * @param string $path
   *   An absolute path.
   *
   * @return string
   *   The path without "." and ".." parts.
   */
  private static function normalizePath(string $path): string {
    $parts = [];
    foreach (explode('/', $path) as $part) {
      if ($part === '..') {
        array_pop($parts);
      }
      elseif ($part !== '.' && ($part !== '' || empty($parts))) {
        $parts[] = $part;
      }
    }
    return implode('/', $parts);
  }

  /**
   * Returns a path relative to another path.
   *
   * @param string $from
   *   The absolute path to start from, without "." and ".." parts.
   * @param string $to
   *   The absolute path to reach, without "." and ".." parts.
   *
   * @return string
   *   The relative path, which may start with "../".
   */
  private static function getRelativePath(string $from, string $to): string {
    $from_parts = explode('/', $from);
    $to_parts = explode('/', $to);
    while ($from_parts && $to_parts && $from_parts[0] === $to_parts[0]) {
      array_shift($from_parts);
      array_shift($to_parts);
    }
    return implode('/', array_merge(array_fill(0, count($from_parts), '..'), $to_parts));
  }

  /**
   * Collect core modules that are installed and obsolete or deprecated.
   *
   * @return array
   *   An associated array of extension names keyed by extension machine names.
   */
  public function collectCoreDeprecatedAndObsoleteExtensions() {
    $deprecated_or_obsolete = [];
    $modules = $this->moduleExtensionList->getList();
    $themes = $this->themeExtensionList->getList();
    $profiles = $this->profileExtensionList->getList();
    $extensions = array_merge($modules, $themes, $profiles);
    unset($modules, $themes, $profiles);

    /** @var \Drupal\Core\Extension\Extension $extension */
    foreach ($extensions as $key => $extension) {
      if (!empty($extension->status)
      && $this->isCoreExtension($extension)
      && !empty($extension->info['lifecycle'])
      && in_array(
          $extension->info['lifecycle'],
          [
            'deprecated',
            'obsolete',
          ]
      )
      ) {
        $deprecated_or_obsolete[$key] = Html::escape($extension->info['name']);
        if (isset($extension->info['lifecycle_link'])) {
          $deprecated_or_obsolete[$key] .= ' (<a href="' . Html::escape($extension->info['lifecycle_link']) . '">' . $this->t('read more') . '</a>)';
        }
      }
    }
    return $deprecated_or_obsolete;
  }

  /**
   * Finds topmost extension for each extension and keeps only that.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   List of all enabled extensions.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   List of extensions, with only the topmost extension left for each
   *   extension that has a parent extension.
   */
  protected function collateExtensionsIntoProjects(array $extensions) {
    foreach ($extensions as $name_a => $extension_a) {
      $path_a = $extension_a->getPath() . '/';
      $path_a_length = strlen($path_a);

      foreach ($extensions as $name_b => $extension_b) {
        // Skip collation for test modules except where we test that.
        if ((strpos($name_b, 'upgrade_status_test_') === 0) && ($name_b != 'upgrade_status_test_submodules_a') && ($name_b != 'upgrade_status_test_submodules_with_errors_a')) {
          continue;
        }

        $path_b = $extension_b->getPath();
        // If the extension is not the same but the beginning of paths match,
        // remove this extension from the list as it is part of another one.
        if ($name_b != $name_a && substr($path_b, 0, $path_a_length) === $path_a) {

          // If the existing project was already Drupal 9 compatible, consider
          // this subcomponent as well. If this component was enabled, it would
          // affect how we consider the Drupal 9 compatibility.
          if (!empty($extensions[$name_a]->info['upgrade_status_next_major_compatible']) && !empty($extension_b->status)) {
            // Overwrite compatibility. If this is still compatible, it will
            // keep being TRUE, otherwise FALSE.
            $extensions[$name_a]->info['upgrade_status_next_major_compatible'] =
              isset($extension_b->info['core_version_requirement']) &&
              self::isCompatibleWithNextMajorDrupal($extension_b->info['core_version_requirement']);
          }

          // Remove the subextension.
          unset($extensions[$name_b]);
        }
      }
    }
    return $extensions;
  }

  /**
   * Returns a single extension based on type and machine name.
   *
   * @param string $project_machine_name
   *   Machine name for the extension.
   *
   * @return \Drupal\Core\Extension\Extension
   *   A project if exists.
   *
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionException
   *   If there was no identified project with the given name.
   */
  public function loadProject(string $project_machine_name) {
    $projects = $this->collectProjects();
    if (!empty($projects[$project_machine_name])) {
      return $projects[$project_machine_name];
    }
    throw new UnknownExtensionException("The {$project_machine_name} project does not exist.");
  }

  /**
   * Gets the date and the totals of the analysis results of a project.
   *
   * ::collectProjects() loads these for all projects with a few queries. Use
   * this instead of ::getResults() when the messages are not needed, like in
   * lists of projects.
   *
   * @param string $project_machine_name
   *   Machine name for project.
   *
   * @return array|null
   *   NULL if there was no result. Otherwise an array with these keys:
   *   - date: The time of the analysis.
   *   - totals: The totals of the results.
   *   - versions: The versions of the analysis tools at that time, or NULL.
   *
   * @see \Drupal\upgrade_status\ScanResultStorage::getTotals()
   */
  public function getResultTotals(string $project_machine_name): ?array {
    return $this->resultStorage->getTotals($project_machine_name);
  }

  /**
   * Forgets the loaded totals of analysis results.
   *
   * @param string|null $project_machine_name
   *   Machine name for the project that was analyzed, or NULL for all
   *   projects.
   *
   * @see \Drupal\upgrade_status\ScanResultStorage::resetTotals()
   */
  public function resetResultTotals(?string $project_machine_name = NULL): void {
    $this->resultStorage->resetTotals($project_machine_name);
  }

  /**
   * Get local scanning results for a project.
   *
   * @param string $project_machine_name
   *   Machine name for project.
   *
   * @return mixed
   *   - NULL if there was no result
   *   - Associative array of results otherwise
   *
   * @see \Drupal\upgrade_status\ScanResultStorage::get()
   */
  public function getResults(string $project_machine_name) {
    return $this->resultStorage->get($project_machine_name);
  }

  /**
   * Summarizes the state of a project for the report.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   A project from ::collectProjects().
   *
   * @return array
   *   An array with these keys, all with labels to show:
   *   - type: The type, like "Contributed module".
   *   - status: "Installed", "Uninstalled" or "N/A" for recipes.
   *   - version: The local version, or "N/A".
   *   - ready: Whether the local version is compatible with the next major
   *     Drupal version, "Compatible" or "Incompatible".
   *   - result: The local analysis result, like "N/A", "3 problems" or
   *     "3 problems, +2 after Drupal 12".
   *   - result_problems: The same without the messages for later versions,
   *     like "3 problems".
   *   - result_later: The messages for later versions, like "+2 after Drupal
   *     12", or NULL if there are none.
   *   - result_count: The number of problems for the next major Drupal
   *     version, or NULL without a scan.
   *   - ignore_count: The number of messages for later versions than the
   *     next major Drupal version, or NULL without a scan.
   *   - update_version: The version on Drupal.org, or a label like "Up to
   *     date" if there is no update.
   *   - update_url: The URL of the update on Drupal.org, or NULL.
   *   - update_ready: Whether the version on Drupal.org is compatible, like
   *     "Compatible" or "Unchecked".
   *   - update_ready_class: CSS class for update_ready.
   *   - issues_url: The URL of the Drupal.org issues of a contributed project
   *     about the next major Drupal version, or NULL.
   */
  public function getProjectSummary(Extension $extension): array {
    $info = $extension->info;
    $next_major = static::getDrupalCoreMajorVersion() + 1;
    $custom = !self::isContributed($extension);
    $types = [
      'module' => [$this->t('Custom module'), $this->t('Contributed module')],
      'theme' => [$this->t('Custom theme'), $this->t('Contributed theme')],
      'profile' => [$this->t('Custom profile'), $this->t('Contributed profile')],
      'recipe' => [$this->t('Custom recipe'), $this->t('Contributed recipe')],
    ];

    $summary = [
      'type' => $types[$extension->getType()][$custom ? 0 : 1] ?? '',
      // Recipes are applied, not installed.
      'status' => $extension->getType() == 'recipe' ? $this->t('N/A') : (empty($extension->status) ? $this->t('Uninstalled') : $this->t('Installed')),
      'version' => !empty($info['version']) ? $info['version'] : $this->t('N/A'),
      'ready' => !empty($info['upgrade_status_next_major_compatible']) ? $this->t('Compatible') : $this->t('Incompatible'),
      'result_count' => NULL,
      'ignore_count' => NULL,
      'result_later' => NULL,
      'update_url' => NULL,
      'update_ready_class' => 'status-info-na',
      'issues_url' => NULL,
    ];

    $report = $this->getResultTotals($extension->getName());
    $summary['result'] = !empty($report) ? $this->t('No problems found') : $this->t('N/A');
    if (!empty($report)) {
      // Messages of the 'ignore' category are for later versions than the
      // next major Drupal version, so those are counted apart from the
      // problems. The plus sign shows that the number is on top of the
      // problems, not a part of that number.
      $summary['ignore_count'] = (int) ($report['totals']['upgrade_status_category']['ignore'] ?? 0);
      $summary['result_count'] = max(0, (int) ($report['totals']['file_errors'] ?? 0) - $summary['ignore_count']);
    }
    if (!empty($summary['result_count'])) {
      $summary['result'] = $this->formatPlural($summary['result_count'], '@count problem', '@count problems');
    }
    $summary['result_problems'] = $summary['result'];
    if (!empty($summary['ignore_count'])) {
      $summary['result_later'] = $this->formatPlural($summary['ignore_count'], '+1 after Drupal @version', '+@count after Drupal @version', ['@version' => static::getDrupalCoreMajorVersion() + 1]);
      $summary['result'] = $this->t('@result, @ignored', [
        '@result' => $summary['result_problems'],
        '@ignored' => $summary['result_later'],
      ]);
    }

    $summary['update_version'] = $this->t('Not applicable');
    if (!empty($info['upgrade_status_update_link'])) {
      $summary['update_version'] = $info['upgrade_status_update_version'];
      $summary['update_url'] = $info['upgrade_status_update_link'];
    }
    elseif (!empty($info['upgrade_status_update'])) {
      $summary['update_version'] = $this->t('Unavailable');
      if ($info['upgrade_status_update'] == ProjectCollector::UPDATE_NOT_CHECKED) {
        $summary['update_version'] = $this->t('Unchecked');
      }
      elseif ($info['upgrade_status_update'] == ProjectCollector::UPDATE_ALREADY_INSTALLED) {
        $summary['update_version'] = $this->t('Up to date');
      }
    }

    $summary['update_ready'] = $this->t('Not applicable');
    if (isset($info['upgrade_status_update'])) {
      switch ($info['upgrade_status_update']) {
        case ProjectCollector::UPDATE_NOT_AVAILABLE:
          $summary['update_ready'] = $this->t('Unavailable');
          $summary['update_ready_class'] = 'status-info-na';
          break;

        case ProjectCollector::UPDATE_NOT_CHECKED:
          $summary['update_ready'] = $this->t('Unchecked');
          $summary['update_ready_class'] = 'status-info-unchecked';
          break;

        case ProjectCollector::UPDATE_AVAILABLE:
        case ProjectCollector::UPDATE_ALREADY_INSTALLED:
          if ($info['upgrade_status_update_compatible']) {
            $summary['update_ready'] = $this->t('Compatible');
            $summary['update_ready_class'] = 'status-info-compatible';
          }
          else {
            $summary['update_ready'] = $this->t('Incompatible');
            $summary['update_ready_class'] = 'status-info-incompatible';
          }
          break;
      }
    }

    if (!$custom) {
      // Use the project name from the info array, not the local name. The
      // issue queues of some projects moved to GitLab, and drupal.org
      // redirects to them with the same query. Drupal.org reads "text" and
      // GitLab reads "search", and each ignores the other. Drupal.org has no
      // status filter that GitLab ignores, so it shows open issues, and
      // GitLab shows all of them.
      $summary['issues_url'] = 'https://drupal.org/project/issues/' . rawurlencode($info['project']) . '?text=Drupal+' . $next_major . '&search=Drupal+' . $next_major . '&state=all';
    }

    return $summary;
  }

  /**
   * Sorts contributed projects without a version by how they were installed.
   *
   * Update recommendations need the version. Packages from Drupal.org have
   * it, but development versions and source installs with Composer, Git
   * checkouts and copies of the files don't.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   Projects keyed by name, from ::collectProjects().
   *
   * @return array
   *   Projects keyed by name, under these keys:
   *   - composer: Installed with Composer. Composer Deploy can add the version.
   *   - git: Checked out with Git. Git Deploy can add the version.
   *   - other: Installed some other way, like copying the files.
   */
  public function getContribProjectsWithoutVersion(array $projects): array {
    $result = ['composer' => [], 'git' => [], 'other' => []];
    foreach ($projects as $name => $project) {
      // Recipes get their version from Composer.
      if ($project->info['upgrade_status_type'] != self::TYPE_CONTRIB || !empty($project->info['version']) || $project->getType() == 'recipe') {
        continue;
      }
      if (InstalledVersions::isInstalled('drupal/' . $project->info['project'])) {
        $result['composer'][$name] = $project;
      }
      elseif (file_exists(DRUPAL_ROOT . '/' . $project->getPath() . '/.git')) {
        $result['git'][$name] = $project;
      }
      else {
        $result['other'][$name] = $project;
      }
    }
    return $result;
  }

  /**
   * Return list of possible next steps and their labels and descriptions.
   *
   * The descriptions are shared by the report page and the report command, so
   * they don't refer to the form or to commands.
   *
   * @return array
   *   Associative array keys by next step identifier. Values are arrays
   *   where the first item is a label, the second is a description and the
   *   third has the CSS classes for a row of a project.
   */
  public function getNextStepInfo() {
    return [
      ProjectCollector::NEXT_REMOVE => [
        $this->t('Remove'),
        $this->t('These are not installed on this site. Agree with the people responsible for the site first, as another site or environment may use a project. Then remove these projects instead of making the code compatible.'),
        'color-warning ' . ProjectCollector::NEXT_REMOVE,
      ],
      ProjectCollector::NEXT_UPDATE => [
        $this->t('Update'),
        $this->t('A newer release is available. Update to it first. Even if it is not compatible yet, it is likely closer.'),
        'color-warning ' . ProjectCollector::NEXT_REMOVE,
      ],
      ProjectCollector::NEXT_SCAN => [
        $this->t('Analyze'),
        $this->t('These are not analyzed yet, so the status is unknown.'),
        'color-warning ' . ProjectCollector::NEXT_REMOVE,
      ],
      ProjectCollector::NEXT_COLLABORATE => [
        $this->t('Collaborate with maintainers'),
        $this->t('These contributed projects have no compatible release yet. Work on the fixes in the Drupal.org issues of the projects instead of fixing the code only on this site. The issues may already have fixes, also from the <a href=":drupal-bot">Project Update Bot</a>. Test the fixes and post feedback in the issues, so the maintainers know whether the fixes work.', [':drupal-bot' => 'https://www.drupal.org/u/project-update-bot']),
        'color-warning ' . ProjectCollector::NEXT_REMOVE,
      ],
      ProjectCollector::NEXT_RECTOR => [
        $this->t('Fix with rector'),
        $this->t('<a href=":drupal-rector">drupal-rector</a> can fix some or all of the problems of these custom projects.', [':drupal-rector' => 'https://www.drupal.org/project/rector']),
        'color-error ' . ProjectCollector::NEXT_REMOVE,
      ],
      ProjectCollector::NEXT_MANUAL => [
        $this->t('Fix manually'),
        $this->t('Fix the problems in the code of these custom projects.'),
        'color-error ' . ProjectCollector::NEXT_REMOVE,
      ],
      ProjectCollector::NEXT_REQUIREMENTS => [
        $this->t('Update requirements'),
        $this->t('No problems are known in the code of these custom projects. Update the requirements in the info file and composer.json, so the projects can be installed on Drupal @version.', ['@version' => static::getDrupalCoreMajorVersion() + 1]),
        'color-warning ' . ProjectCollector::NEXT_REMOVE,
      ],
      ProjectCollector::NEXT_RELAX => [
        $this->t('Nothing to do'),
        $this->t('These are compatible with Drupal @version. Contributed projects here declare that in the info file, and the results of the analysis are for information.', ['@version' => static::getDrupalCoreMajorVersion() + 1]),
        'color-success ' . ProjectCollector::NEXT_REMOVE,
      ],
    ];
  }

  /**
   * Checks constraint compatibility with the next major Drupal core version.
   *
   * A customized version of Semver::satisfies(), since that only works for
   * a == condition.
   *
   * @param string $constraints
   *   Composer compatible constraints from core_version_requirement or
   *   drupal/core requirement.
   *
   * @return bool
   *   Returns TRUE if the constraints are compatible with the next major
   *   version of Drupal, otherwise FALSE.
   */
  public static function isCompatibleWithNextMajorDrupal(string $constraints) {
    // Many projects and releases have the same constraint, and parsing one
    // is slow compared to looking it up.
    static $compatible = [];
    if (!isset($compatible[$constraints])) {
      $version_parser = new VersionParser();
      $provider = new Constraint('>=', $version_parser->normalize((self::getDrupalCoreMajorVersion() + 1) . '.0.0'));
      $parsed_constraints = $version_parser->parseConstraints($constraints);
      $compatible[$constraints] = $parsed_constraints->matches($provider);
    }
    return $compatible[$constraints];
  }

  /**
   * Picks the release to recommend from the available releases of a project.
   *
   * The update module lists releases newest first, and newer branches come
   * first even if they only have alpha or beta releases, or need a newer
   * Drupal core version. So the newest release is not always the best one to
   * update to. The recommendation is the first release that works with the
   * installed Drupal core version and the next major version. Without one, it
   * is the first release that works with the installed core version, and
   * then the newest release. Stable releases come before alpha, beta and
   * release candidate releases in each step.
   *
   * @param array $releases
   *   Releases from the update module's data, newest first.
   *
   * @return array|null
   *   The release, or NULL if there is no published release.
   */
  public static function getRecommendedRelease(array $releases): ?array {
    $published = array_filter($releases, static fn ($release): bool => is_array($release) && !empty($release['version']) && ($release['status'] ?? 'published') === 'published' && !str_ends_with($release['version'], '-dev'));
    if (empty($published)) {
      return NULL;
    }
    $installable = array_filter($published, static function (array $release): bool {
      try {
        return !empty($release['core_compatibility']) && Semver::satisfies(\Drupal::VERSION, $release['core_compatibility']);
      }
      catch (\UnexpectedValueException) {
        return FALSE;
      }
    });
    $ready = array_filter($installable, static fn (array $release): bool => self::isCompatibleWithNextMajorDrupalSafe($release['core_compatibility']));
    foreach ([$ready, $installable] as $candidates) {
      if (!empty($candidates)) {
        $stable = array_filter($candidates, static fn (array $release): bool => !preg_match('/-(alpha|beta|rc)\d*$/i', $release['version']));
        return reset($stable) ?: reset($candidates);
      }
    }
    return reset($published);
  }

  /**
   * Checks whether a release is an update of the installed version.
   *
   * @param string $installed
   *   The installed version, like 8.x-1.2 or 2.0.1. Empty if unknown.
   * @param string $release
   *   The version of a release.
   *
   * @return bool
   *   TRUE if the release is not the installed version, and not older than
   *   it. Development versions can't be compared, so any other release is an
   *   update for them.
   */
  public static function isUpdate(string $installed, string $release): bool {
    if ($installed === $release) {
      return FALSE;
    }
    $normalize = static fn (string $version): string => preg_replace('/^\d+\.x-/', '', $version);
    if ($installed !== '' && !str_contains($installed, 'dev') && version_compare($normalize($installed), $normalize($release), '>')) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Checks next major compatibility, treating invalid constraints as not.
   *
   * @param string $constraints
   *   Composer compatible constraints, like core_version_requirement.
   *
   * @return bool
   *   TRUE if the constraints allow the next major version of Drupal.
   */
  protected static function isCompatibleWithNextMajorDrupalSafe(string $constraints): bool {
    try {
      return $constraints !== '' && self::isCompatibleWithNextMajorDrupal($constraints);
    }
    catch (\UnexpectedValueException) {
      return FALSE;
    }
  }

  /**
   * Checks constraint compatibility with a PHP version.
   *
   * A customized version of Semver::satisfies(), since that only works for
   * a == condition.
   *
   * @param string $constraints
   *   Composer compatible constraints from a PHP version requirement.
   * @param string $php
   *   Optional PHP version number. Defaults to 8.0.0.
   *
   * @return bool
   *   Returns TRUE if the PHP version satisfies the given constraints,
   *   otherwise FALSE.
   */
  public static function isCompatibleWithPhp(string $constraints, string $php = '8.0.0') {
    $version_parser = new VersionParser();
    $provider = new Constraint('>=', $version_parser->normalize($php));
    $parsed_constraints = $version_parser->parseConstraints($constraints);
    return $parsed_constraints->matches($provider);
  }

  /**
   * Gets the minimum PHP version of the next major version of Drupal.
   *
   * @return string
   *   The PHP version. If the requirements of the next major version are not
   *   announced yet, the minimum of the latest known major version.
   */
  public static function getNextMajorMinimumPhp(): string {
    return static::MINIMUM_PHP[static::getDrupalCoreMajorVersion() + 1] ?? static::MINIMUM_PHP[max(array_keys(static::MINIMUM_PHP))];
  }

  /**
   * Return the oldest supported minor version for the current core major.
   *
   * @return string
   *   Oldest supported core version number.
   */
  public static function getOldestSupportedMinor(): string {
    $major = (int) \Drupal::VERSION;
    // Last updated on 2026. February 27.
    // https://www.drupal.org/about/core/policies/core-release-cycles/schedule
    // has the latest information.
    switch ($major) {
      case 10:
        return '10.5';

      case 11:
        return '11.2';

      case 12:
        return '12.0';
    }
    return '';
  }

  /**
   * Checks whether an extension is part of a contributed project.
   *
   * The project collector sets a project name for every project, also for
   * custom ones, so the project name doesn't show whether it is contributed.
   * Extensions that the project collector didn't see, like in tests, are
   * contributed if they have a project name, which drupal.org packaging adds.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension.
   *
   * @return bool
   *   TRUE if the extension is part of a contributed project.
   */
  public static function isContributed(Extension $extension): bool {
    if (isset($extension->info['upgrade_status_type'])) {
      return $extension->info['upgrade_status_type'] === self::TYPE_CONTRIB;
    }
    return !empty($extension->info['project']);
  }

  /**
   * Checks whether a newer release than the installed one is a security update.
   *
   * @param array $releases
   *   The releases from the update module.
   * @param string $installed
   *   The installed version.
   *
   * @return bool
   *   Whether a newer release is a security update.
   */
  public static function hasSecurityUpdate(array $releases, string $installed): bool {
    if ($installed === '' || str_ends_with($installed, '-dev')) {
      return FALSE;
    }
    foreach ($releases as $release) {
      if (is_array($release) && !empty($release['version']) && in_array('Security update', (array) ($release['terms']['Release type'] ?? []), TRUE) && self::isUpdate($installed, $release['version'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns current core's major version.
   *
   * @return int
   *   Version converted to int.
   */
  public static function getDrupalCoreMajorVersion(): int {
    return (int) \Drupal::VERSION;
  }

  /**
   * Checks if an extension is from Drupal core.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to check.
   *
   * @return bool
   *   TRUE if the extension is from core, FALSE otherwise.
   */
  private function isCoreExtension($extension): bool {
    // First try the origin property (available on Module/Profile objects).
    if (property_exists($extension, 'origin') && $extension->origin === 'core') {
      return TRUE;
    }
    // Fall back to path-based check for Theme objects. ThemeExtensionList
    // creates new Theme instances via subClassExtension() but doesn't copy the
    // origin property from the original Extension, so themes don't have origin.
    // Extension::getPathname() is relative to DRUPAL_ROOT (e.g.
    // "core/themes/claro/claro.info.yml"), so the prefix must not have a
    // leading slash.
    $pathname = $extension->getPathname();
    if (strpos($pathname, 'core/') === 0) {
      return TRUE;
    }
    return FALSE;
  }

}
