<?php

namespace Drupal\upgrade_status\Command;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\upgrade_status\DeprecationAnalyzer;
use Drupal\upgrade_status\ProjectCollector;
use Drupal\upgrade_status\ScanResultFormatter;
use Drupal\upgrade_status\ScanResultStorage;
use Drupal\upgrade_status\ToolUpdateChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Analyzes projects and outputs the results.
 *
 * Drupal 11.4 and later runs this command with vendor/bin/dr. Drush runs it
 * through \Drupal\upgrade_status\Drush\Commands\AnalyzeCommand, with the same
 * name and alias.
 */
#[AsCommand(
  name: 'upgrade-status:analyze',
  description: 'Analyze projects and output results in selected format.',
  aliases: ['us-a'],
)]
class AnalyzeCommand extends Command {

  use ProjectFilterTrait;
  use StringTranslationTrait;

  /**
   * Exit code when the analysis found issues.
   *
   * This is the same as Drush's EXIT_FAILURE_WITH_CLARITY, so scripts that
   * check the exit code keep working.
   */
  const EXIT_ISSUES_FOUND = 3;

  public function __construct(
    #[Autowire(service: 'upgrade_status.result_formatter')]
    protected readonly ScanResultFormatter $resultFormatter,
    #[Autowire(service: 'upgrade_status.project_collector')]
    protected readonly ProjectCollector $projectCollector,
    #[Autowire(service: 'upgrade_status.tool_update_checker')]
    protected readonly ToolUpdateChecker $toolUpdateChecker,
    #[Autowire(service: 'upgrade_status.deprecation_analyzer')]
    protected readonly DeprecationAnalyzer $deprecationAnalyzer,
    #[Autowire(service: 'upgrade_status.result_storage')]
    protected readonly ScanResultStorage $resultStorage,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('projects', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'List of projects to analyze.')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Analyze all projects.')
      ->addOption('skip-existing', NULL, InputOption::VALUE_NONE, 'Return results from a previous scan of a project if available, otherwise start a new one.')
      ->addProjectFilterOptions()
      ->addOption('phpstan-memory-limit', NULL, InputOption::VALUE_REQUIRED, 'Set memory limit for PHPStan.', '1500M')
      ->addOption('format', NULL, InputOption::VALUE_REQUIRED, 'Set the format: plain (Markdown), html, csv (one row per message), checkstyle or codeclimate.', 'plain', [
        'plain',
        'html',
        'csv',
        'checkstyle',
        'codeclimate',
      ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Older versions of Upgrade Status and PHPStan can miss deprecations. The
    // message goes to the error output, so the report stays clean.
    if ($updates = $this->toolUpdateChecker->getUpdates()) {
      $message = strip_tags(str_replace(['<code>', '</code>'], '`', (string) $this->toolUpdateChecker->formatUpdates($updates)));
      ($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)->writeln('<comment>' . OutputFormatter::escape($message) . '</comment>');
    }
    $extensions = $this->doAnalyze($input->getArgument('projects'), $input->getOptions(), $output);

    $found_issue = match ($input->getOption('format')) {
      'checkstyle' => $this->formatAllAsCheckStyle($extensions, $output),
      'codeclimate' => $this->formatAllAsCodeClimate($extensions, $output),
      'csv' => $this->formatAllAsCsv($extensions, $output),
      'html' => $this->formatAllAsHtml($extensions, $output),
      default => $this->formatAllAsPlain($extensions, $output),
    };

    return $found_issue ? self::EXIT_ISSUES_FOUND : self::SUCCESS;
  }

  /**
   * Gets the output for messages that are not part of the results.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @return \Symfony\Component\Console\Output\OutputInterface
   *   The error output if there is one, so the results can be redirected to a
   *   file on their own. Otherwise, the command output.
   */
  protected function getErrorOutput(OutputInterface $output): OutputInterface {
    return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
  }

  /**
   * Formats command output as checkstyle XML.
   *
   * @param array $extensions
   *   Result data by extension.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @return bool
   *   Whether issues were found.
   */
  protected function formatAllAsCheckStyle(array $extensions, OutputInterface $output) {
    [$list, $found_issue] = $this->checkResults($extensions, $output);
    $output->writeln($this->resultFormatter->formatCheckstyle($list), OutputInterface::OUTPUT_RAW);
    return $found_issue;
  }

  /**
   * Formats command output as Markdown.
   *
   * @param array $extensions
   *   Result data by extension.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @return bool
   *   Whether issues were found.
   */
  protected function formatAllAsPlain(array $extensions, OutputInterface $output) {
    [$list, $found_issue] = $this->checkResults($extensions, $output);
    $output->write($this->resultFormatter->formatMarkdownReport($list), FALSE, OutputInterface::OUTPUT_RAW);
    return $found_issue;
  }

  /**
   * Formats command output as an HTML document.
   *
   * This is the same document as the HTML export on the report page.
   *
   * @param array $extensions
   *   Result data by extension.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @return bool
   *   Whether issues were found.
   */
  protected function formatAllAsHtml(array $extensions, OutputInterface $output): bool {
    [$list, $found_issue] = $this->checkResults($extensions, $output);
    $grouped = ['custom' => [], 'contrib' => []];
    foreach ($list as $extension) {
      $grouped[ProjectCollector::isContributed($extension) ? 'contrib' : 'custom'][$extension->getName()] = $extension;
    }
    $output->write($this->resultFormatter->formatHtmlReport($grouped), FALSE, OutputInterface::OUTPUT_RAW);
    return $found_issue;
  }

  /**
   * Formats command output as CSV, with one row per message.
   *
   * @param array $extensions
   *   Result data by extension.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @return bool
   *   Whether issues were found.
   */
  protected function formatAllAsCsv(array $extensions, OutputInterface $output): bool {
    [$list, $found_issue] = $this->checkResults($extensions, $output);
    // The next step of a project depends on the scan results, so the projects
    // are collected again after the scan.
    $projects = $this->projectCollector->collectProjects();
    foreach ($list as $key => $extension) {
      $list[$key] = $projects[$extension->getName()] ?? $extension;
    }
    $output->write($this->resultFormatter->formatCsvResults($list), FALSE, OutputInterface::OUTPUT_RAW);
    return $found_issue;
  }

  /**
   * Checks the results of the analyzed projects.
   *
   * @param array $extensions
   *   Result data by extension.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output, to report projects without results.
   *
   * @return array
   *   The list of extensions, and whether issues were found.
   */
  protected function checkResults(array $extensions, OutputInterface $output): array {
    $found_issue = FALSE;
    $list = [];
    foreach ($extensions as $type_list) {
      foreach ($type_list as $name => $extension) {
        $result = $this->resultFormatter->getRawResult($extension);
        if (is_null($result)) {
          $found_issue = TRUE;
          $this->getErrorOutput($output)->writeln('<error>' . $this->t('Analysis of @name failed.', ['@name' => $name]) . '</error>');
        }
        elseif (static::hasMessagesForNextMajor($result)) {
          $found_issue = TRUE;
        }
        $list[] = $extension;
      }
    }
    return [$list, $found_issue];
  }

  /**
   * Checks whether a result has messages for the next major Drupal version.
   *
   * Messages in the 'ignore' category are for a later major version. A result
   * with only those is not a reason to fail.
   *
   * @param array $result
   *   The stored result of a project.
   *
   * @return bool
   *   TRUE if there is a message in a category other than 'ignore', or the
   *   analysis failed.
   */
  protected static function hasMessagesForNextMajor(array $result): bool {
    $categories = $result['data']['totals']['upgrade_status_category'] ?? NULL;
    // The result of a failed analysis has a message without categories.
    if (!is_array($categories)) {
      return !empty($result['data']['totals']['file_errors']);
    }
    unset($categories['ignore']);
    return array_sum($categories) > 0;
  }

  /**
   * Analyzes projects and returns results for processed extensions.
   *
   * @param array $projects
   *   List of projects to analyze.
   * @param array $options
   *   Additional options for the command.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   *   Thrown when one of the passed arguments is invalid
   *   or no arguments were provided.
   * @throws \Symfony\Component\Console\Exception\RuntimeException
   *   Thrown when the environment is not ready to run the analysis.
   */
  protected function doAnalyze(array $projects, array $options, OutputInterface $output) {
    try {
      $this->deprecationAnalyzer->initEnvironment();
    }
    catch (\Exception $e) {
      throw new RuntimeException($e->getMessage() . ' Analysis is not possible until this is resolved.');
    }

    $extensions = [];
    $invalid_names = [];

    if (empty($projects) && !$options['all']) {
      throw new InvalidArgumentException("You need to provide at least one installed project's machine_name.");
    }

    // Gather project list grouped by custom and contrib projects.
    $available_projects = $this->projectCollector->collectProjects();

    if ($options['all']) {
      foreach ($this->filterProjects($available_projects, $options) as $name => $project) {
        $extensions[$project->getType()][$name] = $project;
      }
    }
    else {
      foreach ($projects as $name) {
        if (!isset($available_projects[$name])) {
          $invalid_names[] = $name;
          continue;
        }
        if ($options['ignore-uninstalled'] && ($available_projects[$name]->status ?? NULL) === 0) {
          $invalid_names[] = $name;
          continue;
        }
        if ($options['ignore-contrib'] && $available_projects[$name]->info['upgrade_status_type'] == ProjectCollector::TYPE_CONTRIB) {
          $invalid_names[] = $name;
          continue;
        }
        if ($options['ignore-custom'] && $available_projects[$name]->info['upgrade_status_type'] == ProjectCollector::TYPE_CUSTOM) {
          $invalid_names[] = $name;
          continue;
        }
        $extensions[$available_projects[$name]->getType()][$name] = $available_projects[$name];
      }
    }

    if (!empty($invalid_names)) {
      if (count($invalid_names) == 1) {
        $message = sprintf('The project machine name %s is invalid. Is this a project on this site? (For community projects, use the machine name of the drupal.org project).', $invalid_names[0]);
      }
      else {
        $message = sprintf('The project machine names %s are invalid. Are these projects on this site? (For community projects, use the machine name of the drupal.org project).', implode(', ', $invalid_names));
      }
      throw new InvalidArgumentException($message);
    }

    $error_output = $this->getErrorOutput($output);
    $error_output->writeln((string) $this->t('Starting the analysis. This may take a while.'), OutputInterface::VERBOSITY_VERBOSE);

    foreach ($extensions as $list) {
      foreach ($list as $name => $extension) {
        if ($options['skip-existing']) {
          $scan_result = $this->resultStorage->get($name);
          if (!empty($scan_result)) {
            $error_output->writeln((string) $this->t('Using previous results for @name.', ['@name' => $name]), OutputInterface::VERBOSITY_VERBOSE);
            continue;
          }
        }
        $error_output->writeln((string) $this->t('Processing @name.', ['@name' => $name]), OutputInterface::VERBOSITY_VERBOSE);
        $this->deprecationAnalyzer->analyze($extension, $options);
      }
    }

    return $extensions;
  }

  /**
   * Formats command output as Code Climate issues JSON.
   *
   * @param array $extensions
   *   Result data by extension.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @return bool
   *   Whether issues were found.
   */
  protected function formatAllAsCodeClimate(array $extensions, OutputInterface $output): bool {
    [$list, $found_issue] = $this->checkResults($extensions, $output);
    $output->writeln($this->resultFormatter->formatCodeClimate($list), OutputInterface::OUTPUT_RAW);
    return $found_issue;
  }

}
