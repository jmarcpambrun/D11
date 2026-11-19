<?php

namespace Drupal\upgrade_status\Command;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\upgrade_status\EnvironmentChecker;
use Drupal\upgrade_status\ProjectCollector;
use Drupal\upgrade_status\ReportBuilder;
use Drupal\upgrade_status\ScanResultFormatter;
use Drupal\upgrade_status\ToolUpdateChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reports how ready the site is for the next major Drupal version.
 *
 * This is the information of the Upgrade Status page, as Markdown or HTML,
 * or the projects as CSV. Drupal
 * 11.4 and later runs this command with vendor/bin/dr. Drush runs it through
 * \Drupal\upgrade_status\Drush\Commands\ReportCommand, with the same name and
 * alias.
 */
#[AsCommand(
  name: 'upgrade-status:report',
  description: 'Report how ready the site is for the next major Drupal version.',
  aliases: ['us-r'],
)]
class ReportCommand extends Command {

  use ProjectFilterTrait;
  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'upgrade_status.project_collector')]
    protected readonly ProjectCollector $projectCollector,
    #[Autowire(service: 'upgrade_status.tool_update_checker')]
    protected readonly ToolUpdateChecker $toolUpdateChecker,
    #[Autowire(service: 'upgrade_status.environment_checker')]
    protected readonly EnvironmentChecker $environmentChecker,
    #[Autowire(service: 'upgrade_status.result_formatter')]
    protected readonly ScanResultFormatter $resultFormatter,
    #[Autowire(service: 'upgrade_status.report_builder')]
    protected readonly ReportBuilder $reportBuilder,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addOption('groups', NULL, InputOption::VALUE_REQUIRED, 'Show only these comma-separated sections: environment, ' . implode(', ', array_keys(static::GROUPS)) . '. The Markdown summary is always shown.', '')
      ->addProjectFilterOptions()
      ->addOption('format', NULL, InputOption::VALUE_REQUIRED, 'Set the format: markdown, html, or csv for the projects only, one per row.', 'markdown', [
        'markdown',
        'html',
        'csv',
      ]);
  }

  /**
   * The groups of projects in the --groups option, keyed by name.
   */
  protected const GROUPS = ReportBuilder::MARKDOWN_GROUPS;

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $groups = $this->reportBuilder->getMarkdownGroups();
    $sections = ['environment', ...array_keys($groups)];
    $selected = array_filter(array_map('trim', explode(',', $input->getOption('groups'))));
    if ($unknown = array_diff($selected, $sections)) {
      throw new InvalidArgumentException(sprintf('Unknown groups: %s. Use one or more of these: %s.', implode(', ', $unknown), implode(', ', $sections)));
    }
    $selected = $selected ?: $sections;

    $all_projects = $this->projectCollector->collectProjects();
    $projects = $this->filterProjects($all_projects, $input->getOptions());

    if ($input->getOption('format') === 'csv') {
      // Older versions of Upgrade Status and PHPStan can miss deprecations.
      // The Markdown and HTML reports note it with the other notes about the
      // accuracy. CSV has no place for it, so it goes to the error output.
      if ($updates = $this->toolUpdateChecker->getUpdates()) {
        $message = strip_tags(str_replace(['<code>', '</code>'], '`', (string) $this->toolUpdateChecker->formatUpdates($updates)));
        ($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)->writeln('<comment>' . OutputFormatter::escape($message) . '</comment>');
      }
      $list = [];
      foreach ($groups as $name => [$next_step]) {
        if (in_array($name, $selected)) {
          foreach ($projects as $project) {
            if ($project->info['upgrade_status_next'] == $next_step) {
              $list[] = $project;
            }
          }
        }
      }
      $output->write($this->resultFormatter->formatCsvProjects($list), FALSE, OutputInterface::OUTPUT_RAW);
      return self::SUCCESS;
    }

    $environment = $this->environmentChecker->getChecks();

    if ($input->getOption('format') === 'html') {
      $next_steps = [];
      foreach ($groups as $name => [$next_step]) {
        if (in_array($name, $selected)) {
          $next_steps[] = $next_step;
        }
      }
      $output->write($this->reportBuilder->renderReport($projects, $next_steps, in_array('environment', $selected) ? $environment : NULL, $environment['status'], [], $all_projects), FALSE, OutputInterface::OUTPUT_RAW);
      return self::SUCCESS;
    }

    $output->writeln($this->reportBuilder->formatMarkdownReport($projects, $all_projects, $environment, $selected), OutputInterface::OUTPUT_RAW);
    return self::SUCCESS;
  }

}
