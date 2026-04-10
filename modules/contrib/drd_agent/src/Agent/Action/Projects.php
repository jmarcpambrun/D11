<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\Core\Extension\Extension;
use Drupal\hacked\HackedProject;

/**
 * Provides a 'Projects' code.
 */
class Projects extends Base {

  /**
   * {@inheritdoc}
   */
  public function execute(): array {
    $projects = [];

    // Core.
    $projects[] = [
      'name' => 'drupal',
      'type' => 'core',
      'status' => 1,
      'info' => [
        'core' => '8.x',
        'version' => \Drupal::VERSION,
        'project' => 'drupal',
        'hidden' => FALSE,
      ],
    ];

    // Modules.
    foreach ($this->container->get('extension.list.module')->reset()->getList() as $name => $extension) {
      $this->buildProjectInfo($projects, 'module', $name, $extension);
    }

    // Themes.
    foreach ($this->container->get('extension.list.theme')->reset()->getList() as $name => $extension) {
      $this->buildProjectInfo($projects, 'theme', $name, $extension);
    }

    // Integration with the Hacked module.
    if ($this->moduleHandler->moduleExists('hacked')) {
      $this->checkHacked($projects);
    }

    return $projects;
  }

  /**
   * Build project info array which is common across Drupal core versions.
   *
   * @param array $projects
   *   List of projects to which a new project get appended.
   * @param string $type
   *   Type of the project (core, module, theme, etc.).
   * @param string $name
   *   Name of the project.
   * @param \Drupal\Core\Extension\Extension $extension
   *   Object with further details about the project.
   */
  private function buildProjectInfo(array &$projects, string $type, string $name, Extension $extension): void {
    $projects[] = [
      'name' => $name,
      'type' => $type,
      'status' => $this->moduleHandler->moduleExists($name),
      'info' => $extension->info,
    ];
  }

  /**
   * Verify each project if it got hacked.
   *
   * @param array $projects
   *   The list of projects.
   */
  private function checkHacked(array &$projects): void {
    foreach ($projects as &$project) {
      $hacked = new HackedProject($project['name']);
      $project['hacked'] = [
        'report' => $hacked->computeReport(),
      ];
      $project['hacked']['status'] = ($project['hacked']['report']['status'] === HACKED_STATUS_HACKED);
    }
  }

}

if (!defined('HACKED_STATUS_HACKED')) {
  define('HACKED_STATUS_HACKED', 3);
}
