<?php

namespace Drupal\upgrade_status;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\Extension;

/**
 * Runs all analyzers on a project and stores the result.
 */
final class DeprecationAnalyzer {

  /**
   * The analyzers that run after PHPStan, in the order they run.
   *
   * @var \Drupal\upgrade_status\AnalyzerInterface[]
   */
  private array $analyzers = [];

  /**
   * Constructs a deprecation analyzer.
   *
   * @param \Drupal\upgrade_status\ScanResultStorage $scanResultStorage
   *   The storage of the analysis results.
   * @param \Drupal\upgrade_status\PhpStanAnalyzer $phpStanAnalyzer
   *   The PHPStan analyzer.
   * @param \Drupal\upgrade_status\MessageCategorizer $messageCategorizer
   *   The message categorizer.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly ScanResultStorage $scanResultStorage,
    private readonly PhpStanAnalyzer $phpStanAnalyzer,
    private readonly MessageCategorizer $messageCategorizer,
    private readonly TimeInterface $time,
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * Adds an analyzer that runs after PHPStan.
   *
   * The service container calls this for the services with the
   * upgrade_status.analyzer tag, from the highest priority to the lowest.
   *
   * @param \Drupal\upgrade_status\AnalyzerInterface $analyzer
   *   The analyzer.
   */
  public function addAnalyzer(AnalyzerInterface $analyzer): void {
    $this->analyzers[] = $analyzer;
  }

  /**
   * Initializes the analyzers that need an external environment.
   *
   * @throws \Exception
   *   If initialization failed. The analysis can't run in this case.
   */
  public function initEnvironment(): void {
    $this->phpStanAnalyzer->initEnvironment();
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

    // The analyzers walk the directories of the project many times. Each
    // directory is read once for all analyzers.
    $this->fileFinder->keepListings(TRUE);
    try {
      $result = [
        'date' => $this->time->getRequestTime(),
        'data' => $this->phpStanAnalyzer->analyze($extension, $options),
      ];

      // Run the other analyzers and collect their messages.
      $more_deprecations = [];
      $declared_ready = TRUE;
      foreach ($this->analyzers as $analyzer) {
        if (!$analyzer->applies($extension)) {
          continue;
        }
        $deprecations = $analyzer->analyze($extension);
        if ($analyzer instanceof ExtensionMetadataDeprecationAnalyzer) {
          $declared_ready = empty($deprecations);
        }
        $more_deprecations = array_merge($more_deprecations, $deprecations);
      }
      $result['data']['totals']['upgrade_status_split']['declared_ready'] = $declared_ready;
    }
    finally {
      $this->fileFinder->keepListings(FALSE);
    }

    foreach ($more_deprecations as $one_deprecation) {
      $result['data']['files'][$one_deprecation->getFile()]['messages'][] = [
        'message' => $one_deprecation->getMessage(),
        'line' => $one_deprecation->getLine(),
        'analyzer' => $one_deprecation->getAnalyzer(),
      ];
      $result['data']['totals']['errors']++;
      $result['data']['totals']['file_errors']++;
    }

    $result = $this->messageCategorizer->categorizeResults($result, $extension);
    $this->scanResultStorage->set($extension->getName(), $result);
  }

}
