<?php

namespace Drupal\upgrade_status\Command;

use Drupal\upgrade_status\ProjectCollector;
use Symfony\Component\Console\Input\InputOption;

/**
 * Options to leave projects out of a command.
 */
trait ProjectFilterTrait {

  /**
   * Adds the options to leave projects out.
   *
   * @return $this
   */
  protected function addProjectFilterOptions(): static {
    return $this
      ->addOption('ignore-uninstalled', NULL, InputOption::VALUE_NONE, 'Ignore uninstalled projects.')
      ->addOption('ignore-contrib', NULL, InputOption::VALUE_NONE, 'Ignore contributed projects.')
      ->addOption('ignore-custom', NULL, InputOption::VALUE_NONE, 'Ignore custom projects.')
      ->addOption('ignore-list', NULL, InputOption::VALUE_REQUIRED, 'Ignore a list of comma-separated projects.', '');
  }

  /**
   * Leaves out the projects that the options ask to ignore.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   Projects keyed by name, from ProjectCollector::collectProjects().
   * @param array $options
   *   The command options.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The projects to keep, keyed by name.
   */
  protected function filterProjects(array $projects, array $options): array {
    $projects_to_ignore = explode(',', $options['ignore-list']);
    foreach ($projects as $name => $project) {
      // Recipes are applied, not installed, so they have no status.
      if (($options['ignore-uninstalled'] && ($project->status ?? NULL) === 0) ||
        ($options['ignore-contrib'] && $project->info['upgrade_status_type'] == ProjectCollector::TYPE_CONTRIB) ||
        ($options['ignore-custom'] && $project->info['upgrade_status_type'] == ProjectCollector::TYPE_CUSTOM) ||
        in_array($name, $projects_to_ignore)) {
        unset($projects[$name]);
      }
    }
    return $projects;
  }

}
