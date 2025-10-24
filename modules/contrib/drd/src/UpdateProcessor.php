<?php

namespace Drupal\drd;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\drd\Entity\MajorInterface;
use Drupal\drd\Entity\ProjectInterface;
use Drupal\drd\Entity\Release;
use Drupal\drd\Entity\ReleaseInterface;
use Drupal\update\UpdateFetcherInterface;
use Drupal\update\UpdateManagerInterface;
use Drupal\update\UpdateProcessor as CoreUpdateProcessor;

/**
 * Process project update information.
 */
class UpdateProcessor extends CoreUpdateProcessor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, QueueFactory $queue_factory, UpdateFetcherInterface $update_fetcher, StateInterface $state_store, PrivateKey $private_key, KeyValueFactoryInterface $key_value_factory, KeyValueExpirableFactoryInterface $key_value_expirable_factory, TimeInterface $time, EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($config_factory, $queue_factory, $update_fetcher, $state_store, $private_key, $key_value_factory, $key_value_expirable_factory, $time);
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Mapping of stati for DRD processing.
   */
  public static function getStatuses(): array {
    return [
      'type' => [
        'unknown' => [
          'weight' => -3,
          'title' => t('Unknown'),
        ],
        'security' => [
          'weight' => -2,
          'title' => t('Security update'),
        ],
        'unsupported' => [
          'weight' => -1,
          'title' => t('Unsupported'),
        ],
        'recommended' => [
          'weight' => 0,
          'title' => t('Recommended'),
        ],
        'ok' => [
          'weight' => 1,
          'title' => t('OK'),
        ],
      ],
      'status' => [
        UpdateManagerInterface::NOT_SECURE => [
          'type' => 'security',
          'label' => t('Not secure'),
        ],
        UpdateManagerInterface::REVOKED => [
          'type' => 'security',
          'label' => t('Revoked'),
        ],
        UpdateManagerInterface::NOT_SUPPORTED => [
          'type' => 'unsupported',
          'label' => t('Not supported'),
        ],
        UpdateManagerInterface::NOT_CURRENT => [
          'type' => 'recommended',
          'label' => t('Not current'),
        ],
        UpdateManagerInterface::CURRENT => [
          'type' => 'ok',
          'label' => t('Current'),
        ],
      ],
    ];
  }

  /**
   * Build a normalized array of project data.
   *
   * @param string $name
   *   The project name.
   * @param string $type
   *   The project type.
   * @param string $core
   *   The core version of the project.
   *
   * @return array
   *   Normalized array.
   */
  private function buildProjectData(string $name, string $type, string $core): array {
    return [
      'name' => $name,
      'core' => $core . '.x',
      'includes' => [],
      'project_type' => $type,
    ];
  }

  /**
   * Copied from Drupal 7 core.
   *
   * @param array $project_data
   *   The project data array.
   * @param array $available
   *   The array of available attributes.
   */
  private function d7CoreIpdateCalculateProjectUpdateStatus(array &$project_data, array $available): void {
    foreach (['title', 'link'] as $attribute) {
      if (!isset($project_data[$attribute]) && isset($available[$attribute])) {
        $project_data[$attribute] = $available[$attribute];
      }
    }

    // If the project status is marked as something bad, there's nothing else
    // to consider.
    if (isset($available['project_status'])) {
      switch ($available['project_status']) {
        case 'insecure':
          $project_data['status'] = UpdateManagerInterface::NOT_SECURE;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['project-not-secure'],
            'label' => t('Project not secure'),
            'data' => t('This project has been labeled insecure by the Drupal security team, and is no longer available for download. Immediately disabling everything included by this project is strongly recommended!'),
          ];
          break;

        case 'unpublished':
        case 'revoked':
          $project_data['status'] = UpdateManagerInterface::REVOKED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['project-revoked'],
            'label' => t('Project revoked'),
            'data' => t('This project has been revoked, and is no longer available for download. Disabling everything included by this project is strongly recommended!'),
          ];
          break;

        case 'unsupported':
          $project_data['status'] = UpdateManagerInterface::NOT_SUPPORTED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['project-not-supported'],
            'label' => t('Project not supported'),
            'data' => t('This project is no longer supported, and is no longer available for download. Disabling everything included by this project is strongly recommended!'),
          ];
          break;

        case 'not-fetched':
          $project_data['status'] = UpdateFetcherInterface::NOT_FETCHED;
          $project_data['reason'] = t('Failed to get available update data.');
          break;

        default:
          // Assume anything else (e.g. 'published') is valid and we should
          // perform the rest of the logic in this function.
          break;
      }
    }

    if (!empty($project_data['status'])) {
      // We already know the status for this project, so there's nothing else to
      // compute. Record the project status into $project_data and we're done.
      $project_data['project_status'] = $available['project_status'];
      return;
    }

    // Figure out the target major version.
    $existing_major = $project_data['existing_major'];
    $supported_majors = [];
    if (isset($available['supported_majors'])) {
      $supported_majors = explode(',', $available['supported_majors']);
    }
    elseif (isset($available['default_major'])) {
      // Older release history XML file without supported or recommended.
      $supported_majors[] = $available['default_major'];
    }

    if (in_array($existing_major, $supported_majors, TRUE)) {
      // Still supported, stay at the current major version.
      $target_major = $existing_major;
    }
    elseif (isset($available['recommended_major'])) {
      // Since 'recommended_major' is defined, we know this is the new XML
      // format. Therefore, we know the current release is unsupported since
      // its major version was not in the 'supported_majors' list. We should
      // find the best release from the recommended major version.
      $target_major = $available['recommended_major'];
      $project_data['status'] = UpdateManagerInterface::NOT_SUPPORTED;
    }
    elseif (isset($available['default_major'])) {
      // Older release history XML file without recommended, so recommend
      // the currently defined "default_major" version.
      $target_major = $available['default_major'];
    }
    else {
      // Malformed XML file? Stick with the current version.
      $target_major = $existing_major;
    }

    // Make sure we never tell the admin to downgrade. If we recommended an
    // earlier version than the one they're running, they'd face an
    // impossible data migration problem, since Drupal never supports a DB
    // downgrade path. In the unfortunate case that what they're running is
    // unsupported, and there's nothing newer for them to upgrade to, we
    // can't print out a "Recommended version", but just have to tell them
    // what they have is unsupported and let them figure it out.
    $target_major = max($existing_major, $target_major);

    $release_patch_changed = '';
    $patch = '';

    // If the project is marked as UpdateFetcherInterface::FETCH_PENDING, it
    // means that the data we currently have (if any) is stale, and we've got
    // a task queued up to (re)fetch the data. In that case, we mark it as such,
    // merge in whatever data we have (e.g. project title and link), and move
    // on.
    if (!empty($available['fetch_status']) && $available['fetch_status'] === UpdateFetcherInterface::FETCH_PENDING) {
      $project_data['status'] = UpdateFetcherInterface::FETCH_PENDING;
      $project_data['reason'] = t('No available update data');
      $project_data['fetch_status'] = $available['fetch_status'];
      return;
    }

    // Defend ourselves from XML history files that contain no releases.
    if (empty($available['releases'])) {
      $project_data['status'] = UpdateFetcherInterface::UNKNOWN;
      $project_data['reason'] = t('No available releases found');
      return;
    }
    foreach ($available['releases'] as $version => $release) {
      // First, if this is the existing release, check a few conditions.
      if ($project_data['existing_version'] === $version) {
        if (isset($release['terms']['Release type']) &&
          in_array('Insecure', $release['terms']['Release type'], TRUE)) {
          $project_data['status'] = UpdateManagerInterface::NOT_SECURE;
        }
        elseif ($release['status'] === 'unpublished') {
          $project_data['status'] = UpdateManagerInterface::REVOKED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['release-revoked'],
            'label' => t('Release revoked'),
            'data' => t('Your currently installed release has been revoked, and is no longer available for download. Disabling everything included in this release or upgrading is strongly recommended!'),
          ];
        }
        elseif (isset($release['terms']['Release type']) &&
          in_array('Unsupported', $release['terms']['Release type'], TRUE)) {
          $project_data['status'] = UpdateManagerInterface::NOT_SUPPORTED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['release-not-supported'],
            'label' => t('Release not supported'),
            'data' => t('Your currently installed release is now unsupported, and is no longer available for download. Disabling everything included in this release or upgrading is strongly recommended!'),
          ];
        }
      }

      // Otherwise, ignore unpublished, insecure, or unsupported releases.
      if ($release['status'] === 'unpublished' ||
        (isset($release['terms']['Release type']) &&
          (in_array('Insecure', $release['terms']['Release type'], TRUE) ||
            in_array('Unsupported', $release['terms']['Release type'], TRUE)))) {
        continue;
      }

      // See if this is a higher major version than our target and yet still
      // supported. If so, record it as an "Also available" release.
      // Note: some projects have a HEAD release from CVS days, which could
      // be one of those being compared. They would not have version_major
      // set, so we must call isset first.
      if (isset($release['version_major']) && $release['version_major'] > $target_major) {
        if (in_array($release['version_major'], $supported_majors, TRUE)) {
          if (!isset($project_data['also'])) {
            $project_data['also'] = [];
          }
          if (!isset($project_data['also'][$release['version_major']])) {
            $project_data['also'][$release['version_major']] = $version;
            $project_data['releases'][$version] = $release;
          }
        }
        // Otherwise, this release can't matter to us, since it's neither
        // from the release series we're currently using nor the recommended
        // release. We don't even care about security updates for this
        // branch, since if a project maintainer puts out a security release
        // at a higher major version and not at the lower major version,
        // they must remove the lower version from the supported major
        // versions at the same time, in which case we won't hit this code.
        continue;
      }

      // Look for the 'latest version' if we haven't found it yet. Latest is
      // defined as the most recent version for the target major version.
      if (!isset($project_data['latest_version'])
        && $release['version_major'] === $target_major) {
        $project_data['latest_version'] = $version;
        $project_data['releases'][$version] = $release;
      }

      // Look for the development snapshot release for this branch.
      if (!isset($project_data['dev_version'])
        && $release['version_major'] === $target_major
        && isset($release['version_extra'])
        && $release['version_extra'] === 'dev') {
        $project_data['dev_version'] = $version;
        $project_data['releases'][$version] = $release;
      }

      // Look for the 'recommended' version if we haven't found it yet (see
      // phpdoc at the top of this function for the definition).
      if (!isset($project_data['recommended'])
        && $release['version_major'] === $target_major
        && isset($release['version_patch'])) {
        if ($patch !== $release['version_patch']) {
          $patch = $release['version_patch'];
          $release_patch_changed = $release;
        }
        if (empty($release['version_extra']) && $patch === $release['version_patch']) {
          $project_data['recommended'] = $release_patch_changed['version'];
          $project_data['releases'][$release_patch_changed['version']] = $release_patch_changed;
        }
      }

      // Stop searching once we hit the currently installed version.
      if ($project_data['existing_version'] === $version) {
        break;
      }

      // If we're running a dev snapshot and have a timestamp, stop
      // searching for security updates once we hit an official release
      // older than what we've got. Allow 100 seconds of leeway to handle
      // differences between the datestamp in the .info file and the
      // timestamp of the tarball itself (which are usually off by 1 or 2
      // seconds) so that we don't flag that as a new release.
      if ($project_data['install_type'] === 'dev') {
        if (empty($project_data['datestamp'])) {
          // We don't have current timestamp info, so we can't know.
          continue;
        }

        if (isset($release['date']) && ($project_data['datestamp'] + 100 > $release['date'])) {
          // We're newer than this, so we can skip it.
          continue;
        }
      }

      // See if this release is a security update.
      if (isset($release['terms']['Release type'])
        && in_array('Security update', $release['terms']['Release type'], TRUE)) {
        $project_data['security updates'][] = $release;
      }
    }

    // If we were unable to find a recommended version, then make the latest
    // version the recommended version if possible.
    if (!isset($project_data['recommended']) && isset($project_data['latest_version'])) {
      $project_data['recommended'] = $project_data['latest_version'];
    }

    //
    // Check to see if we need an update or not.
    //
    if (!empty($project_data['security updates'])) {
      // If we found security updates, that always trumps any other status.
      $project_data['status'] = UpdateManagerInterface::NOT_SECURE;
    }

    if (isset($project_data['status'])) {
      // If we already know the status, we're done.
      return;
    }

    // If we don't know what to recommend, there's nothing we can report.
    // Bail out early.
    if (!isset($project_data['recommended'])) {
      $project_data['status'] = UpdateFetcherInterface::UNKNOWN;
      $project_data['reason'] = t('No available releases found');
      return;
    }

    // If we're running a dev snapshot, compare the date of the dev snapshot
    // with the latest official version, and record the absolute latest in
    // 'latest_dev' so we can correctly decide if there's a newer release
    // than our current snapshot.
    if ($project_data['install_type'] === 'dev') {
      if (isset($project_data['dev_version']) && $available['releases'][$project_data['dev_version']]['date'] > $available['releases'][$project_data['latest_version']]['date']) {
        $project_data['latest_dev'] = $project_data['dev_version'];
      }
      else {
        $project_data['latest_dev'] = $project_data['latest_version'];
      }
    }

    // Figure out the status, based on what we've seen and the install type.
    switch ($project_data['install_type']) {
      case 'official':
        if ($project_data['existing_version'] === $project_data['recommended'] || $project_data['existing_version'] === $project_data['latest_version']) {
          $project_data['status'] = UpdateManagerInterface::CURRENT;
        }
        else {
          $project_data['status'] = UpdateManagerInterface::NOT_CURRENT;
        }
        break;

      case 'dev':
        $latest = $available['releases'][$project_data['latest_dev']];
        if (empty($project_data['datestamp'])) {
          $project_data['status'] = UpdateFetcherInterface::NOT_CHECKED;
          $project_data['reason'] = t('Unknown release date');
        }
        elseif (($project_data['datestamp'] + 100 > $latest['date'])) {
          $project_data['status'] = UpdateManagerInterface::CURRENT;
        }
        else {
          $project_data['status'] = UpdateManagerInterface::NOT_CURRENT;
        }
        break;

      default:
        $project_data['status'] = UpdateFetcherInterface::UNKNOWN;
        $project_data['reason'] = t('Invalid info');
    }
  }

  /**
   * Calculate the project update status.
   *
   * @param \Drupal\drd\Entity\ProjectInterface $project
   *   The project entity.
   * @param \Drupal\drd\Entity\MajorInterface $major
   *   The major entity.
   * @param \Drupal\drd\Entity\ReleaseInterface $release
   *   The release entity.
   * @param array $available
   *   Available data from drupal.org.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function calculate(ProjectInterface $project, MajorInterface $major, ReleaseInterface $release, array $available): void {
    $project_data = [
      'existing_major' => $major->getMajorVersion(),
      'existing_version' => $release->getVersion(),
      'install_type' => (str_contains($release->getVersion(), 'dev')) ? 'dev' : 'official',
    ];
    if ($project_data['install_type'] === 'dev') {
      $first = $release->getInformation();
      $firstDef = [];
      if ($first !== NULL && (is_object($first) || is_string($first)) && method_exists($first, 'toArray')) {
        $firstDef = $first->toArray();
      }
      if (empty($firstDef['info']['datestamp'])) {
        $project_data['status'] = UpdateFetcherInterface::NOT_CHECKED;
      }
      else {
        $project_data['datestamp'] = $firstDef['info']['datestamp'];
      }
    }
    if ($major->getCoreVersion() < 8) {
      $this->d7CoreIpdateCalculateProjectUpdateStatus($project_data, $available);
    }
    elseif (function_exists('update_calculate_project_update_status')) {
      update_calculate_project_update_status($project_data, $available);
    }
    $release->set('updatestatus', $project_data['status']);
    $release->set('updateinfo', $project_data);
    $release->save();
    if (!empty($project_data['recommended'])) {
      $recommended = Release::findOrCreate($project->getType(), $project->getName(), $project_data['recommended']);
      if ($release->getVersion() !== $recommended->getVersion()) {
        $this->calculate($project, $major, $recommended, $available);
      }
      $major->setRecommendedRelease($recommended);
    }
    if (!empty($project_data['title'])) {
      $project->setLabel($project_data['title']);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function fetchData(): void {
    $this->moduleHandler->loadInclude('update', 'inc', 'update.compare');
    foreach ([9, 8, 7, 6] as $version) {
      $processed = [];
      $ids = $this->entityTypeManager->getStorage('drd_major')->getQuery()
        ->condition('coreversion', $version)
        ->condition('hidden', 0)
        ->notExists('parentproject')
        ->accessCheck(FALSE)
        ->execute();
      if (empty($ids)) {
        continue;
      }
      /** @var int[] $ids */
      array_walk($ids, static function (&$item) {
        $item = (int) $item;
      });
      /** @var \Drupal\drd\Entity\MajorInterface $major */
      foreach ($this->entityTypeManager->getStorage('drd_major')->loadMultiple($ids) as $major) {
        if ($project = $major->getProject()) {
          if (!isset($processed[$project->getName()])) {
            $p = $this->buildProjectData($project->getName(), (string) $project->get('type')->value, (string) $version);
            if ($this->processFetchTask($p)) {
              $processed[$project->getName()] = $this->availableReleasesTempStore->get($project->getName());
              if ($version === 6) {
                $this->adjustD6Data($processed[$project->getName()]);
              }
            }
            else {
              $processed[$project->getName()] = FALSE;
            }
          }
          $available = $processed[$project->getName()];
          if ($available) {
            $rids = $this->entityTypeManager->getStorage('drd_release')
              ->getQuery()
              ->condition('major', $major->id())
              ->accessCheck(FALSE)
              ->execute();
            /** @var int[] $rids */
            array_walk($rids, static function (&$item) {
              $item = (int) $item;
            });
            /** @var \Drupal\drd\Entity\ReleaseInterface $release */
            foreach ($this->entityTypeManager->getStorage('drd_release')
              ->loadMultiple($rids) as $release) {
              $this->calculate($project, $major, $release, $available);
            }
            $major->set('information', $available);
            $major
              ->updateStatus()
              ->save();
            $project->save();
          }
        }
      }
    }

    // Delete stored information about available releases.
    $this->availableReleasesTempStore->deleteAll();
  }

  /**
   * Adjust project data for old D6 structure.
   *
   * @param array $available
   *   The project data.
   */
  private function adjustD6Data(array &$available): void {
    if (!$available || empty($available['releases'])) {
      return;
    }
    $latest_major = 0;
    foreach ($available['releases'] as $release) {
      if ($release['version_major'] > $latest_major) {
        $latest_major = $release['version_major'];
        $available['project_status'] = $release['status'];
      }
    }
    if (!$latest_major) {
      return;
    }

    $available['supported_majors'] = $latest_major;
    $available['recommended_major'] = $latest_major;
    $available['default_major'] = $latest_major;
  }

}
