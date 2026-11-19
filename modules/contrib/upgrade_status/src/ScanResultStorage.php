<?php

namespace Drupal\upgrade_status;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Stores the analysis results of projects.
 */
class ScanResultStorage {

  /**
   * The key/value collection that holds the results.
   */
  public const COLLECTION = 'upgrade_status_scan_results';

  /**
   * How many results are loaded at once for their totals.
   */
  protected const LOAD_CHUNK_SIZE = 50;

  /**
   * The date and the totals of the results that were loaded, by project.
   *
   * A project without results has NULL.
   *
   * @var array<string, array|null>
   */
  protected array $totals = [];

  /**
   * Constructs a new result storage.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The key/value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\upgrade_status\ToolUpdateChecker $toolUpdateChecker
   *   The tool update checker, for the versions of the analysis tools.
   */
  public function __construct(
    protected KeyValueFactoryInterface $keyValueFactory,
    protected TimeInterface $time,
    protected ToolUpdateChecker $toolUpdateChecker,
  ) {}

  /**
   * Gets the results of a project.
   *
   * @param string $project
   *   The machine name of the project.
   *
   * @return array|null
   *   The results, with the date and data keys. Results stored by ::set()
   *   also have a versions key, with the versions of the analysis tools at
   *   that time. NULL if the project has no results.
   */
  public function get(string $project): ?array {
    return $this->getStore()->get($project) ?: NULL;
  }

  /**
   * Stores the results of a project.
   *
   * @param string $project
   *   The machine name of the project.
   * @param array $result
   *   The results, with the date and data keys. The installed versions of the
   *   analysis tools are added as the versions key, if it is not there.
   */
  public function set(string $project, array $result): void {
    $result += ['versions' => $this->toolUpdateChecker->getInstalledVersions()];
    $this->getStore()->set($project, $result);
    $this->resetTotals($project);
  }

  /**
   * Stores a failed analysis as the results of a project.
   *
   * @param string $project
   *   The machine name of the project.
   * @param string $error
   *   A short name for the failure, which takes the place of a file name in
   *   the results.
   * @param string $message
   *   The message that explains the failure.
   */
  public function setFailure(string $project, string $error, string $message): void {
    $this->set($project, [
      'date' => $this->time->getRequestTime(),
      'data' => [
        'totals' => [
          'errors' => 1,
          'file_errors' => 1,
          'upgrade_status_split' => [
            'warning' => 1,
          ],
        ],
        'files' => [
          $error => [
            'errors' => 1,
            'messages' => [
              [
                'message' => $message,
                'line' => 0,
              ],
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Gets the date and the totals of the results of a project.
   *
   * Use this instead of ::get() when the messages are not needed, like in
   * lists of projects. ::loadTotals() loads these for many projects with a
   * few queries.
   *
   * @param string $project
   *   The machine name of the project.
   *
   * @return array|null
   *   NULL if the project has no results. Otherwise an array with these keys:
   *   - date: The time of the analysis.
   *   - totals: The totals of the results.
   *   - versions: The versions of the analysis tools at that time, keyed by
   *     the Composer package name. NULL if they were not stored.
   */
  public function getTotals(string $project): ?array {
    if (!array_key_exists($project, $this->totals)) {
      $this->loadTotals([$project]);
    }
    return $this->totals[$project];
  }

  /**
   * Loads the date and the totals of the results of projects.
   *
   * The results are loaded for some projects at a time, and only the totals
   * are kept, because the messages of all projects need a lot of memory.
   *
   * @param string[] $projects
   *   The machine names of the projects.
   */
  public function loadTotals(array $projects): void {
    $store = $this->getStore();
    foreach (array_chunk($projects, self::LOAD_CHUNK_SIZE) as $chunk) {
      $results = $store->getMultiple($chunk);
      foreach ($chunk as $project) {
        $this->totals[$project] = empty($results[$project]) ? NULL : [
          'date' => $results[$project]['date'] ?? 0,
          'totals' => $results[$project]['data']['totals'] ?? [],
          'versions' => $results[$project]['versions'] ?? NULL,
        ];
      }
    }
  }

  /**
   * Forgets the loaded totals of results.
   *
   * Needed after another request stored new results.
   *
   * @param string|null $project
   *   The machine name of the project, or NULL for all projects.
   */
  public function resetTotals(?string $project = NULL): void {
    if ($project === NULL) {
      $this->totals = [];
    }
    else {
      unset($this->totals[$project]);
    }
  }

  /**
   * Deletes the results of all projects.
   */
  public function deleteAll(): void {
    $this->getStore()->deleteAll();
    $this->resetTotals();
  }

  /**
   * Gets the key/value store of the results.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   The store.
   */
  protected function getStore() {
    return $this->keyValueFactory->get(self::COLLECTION);
  }

}
