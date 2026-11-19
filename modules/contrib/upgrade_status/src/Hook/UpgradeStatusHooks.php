<?php

namespace Drupal\upgrade_status\Hook;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\upgrade_status\ToolUpdateChecker;

/**
 * Hook implementations for upgrade_status.
 */
class UpgradeStatusHooks {

  use StringTranslationTrait;

  /**
   * Constructs the hook implementations.
   *
   * @param \Drupal\upgrade_status\ToolUpdateChecker $toolUpdateChecker
   *   The checker of updates of Upgrade Status and PHPStan.
   */
  public function __construct(
    protected readonly ToolUpdateChecker $toolUpdateChecker,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'upgrade_status.report':
        $help = '<p>' . $this->t('Analyze projects to find out if there are detectable compatibility errors with the modules and themes installed on your site.');
        $help .= '</p>';
        return $help;

      case 'help.page.upgrade_status':
        $help = '';
        $help .= '<h3>' . $this->t('About') . '</h3>';
        $help .= '<p>' . $this->t('Upgrade Status checks whether the site is ready for the next major version of Drupal. It checks the hosting environment, suggests available updates of contributed projects, and analyzes the code of contributed and custom modules, themes, profiles and recipes for deprecated code that must be replaced. Projects that are not installed are analyzed too.') . '</p>';
        $help .= '<h3>' . $this->t('How to use') . '</h3>';
        $help .= '<p>' . $this->t('There are no configuration options. Go to <a href=":upgrade_status_report">Administration » Reports » Upgrade status</a> to see the report, analyze projects and export the results. The <code>upgrade-status:analyze</code> and <code>upgrade-status:report</code> commands do the same on the command line, with Drush or <code>vendor/bin/dr</code>.', [
          ':upgrade_status_report' => Url::fromRoute('upgrade_status.report')->toString(),
        ]) . '</p>';
        $help .= '<ul>';
        $help .= '<li>' . $this->t('For a full description, visit the <a href="@project_page">project page</a>', [
          '@project_page' => 'https://www.drupal.org/project/upgrade_status',
        ]) . '</li>';
        $help .= '<li>' . $this->t('To submit bug reports and feature suggestions, or to track changes, visit the <a href="@project_issue_queue">project issue queue</a>', [
          '@project_issue_queue' => 'https://www.drupal.org/project/issues/upgrade_status',
        ]) . '</li>';
        $help .= '</ul>';
        return $help;
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) {
    return [
      'upgrade_status_html_export' => [
        'variables' => [
          'meta' => '',
          'styles' => '',
          'script' => [],
          'projects' => [],
        ],
      ],
      'upgrade_status_html_report' => [
        'variables' => [
          'title' => '',
          'meta' => '',
          'styles' => '',
          'script' => [],
          'results' => [],
          'summary' => [],
          'accuracy' => [],
          'environment' => NULL,
          'groups' => [],
        ],
      ],
      'upgrade_status_summary' => [
        'variables' => [
          'heading' => '',
          'sentence' => '',
          'percent' => 0,
          'bar_label' => '',
          'segments' => [],
          'legend' => [],
        ],
      ],
      'upgrade_status_section_title' => [
        'variables' => [
          'step' => '',
          'icon' => NULL,
          'title' => '',
          'pill' => '',
          'pill_variant' => 'neutral',
          'summary' => NULL,
        ],
      ],
      'upgrade_status_project_results' => [
        'variables' => [
          'title' => NULL,
          'meta' => [],
          'actions' => NULL,
          'callout' => NULL,
          'tiles' => [],
          'path_note' => NULL,
          'notes' => [],
          'sections' => [],
          'back' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_extension_info_alter() and hook_system_info_alter().
   */
  #[Hook('extension_info_alter')]
  #[Hook('system_info_alter')]
  public function systemInfoAlter(array &$info, Extension $extension, $type) {
    // Always mark upgrade_status as a contrib project regardless of how it
    // was obtained. This makes testing the module results reliably consistent.
    if ($extension->getName() == 'upgrade_status') {
      $info['project'] = $extension->getName();
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->toolUpdateChecker->refreshIfDue();
  }

  /**
   * Implements hook_batch_alter().
   *
   * Fetches the releases of the PHPStan packages in the same batch as the
   * update module's manual check of available updates.
   */
  #[Hook('batch_alter')]
  public function batchAlter(array &$batch): void {
    foreach ($batch['sets'] ?? [] as $key => $set) {
      foreach ($set['operations'] ?? [] as [$callback]) {
        if ((is_array($callback) && ($callback[1] ?? NULL) === 'fetchDataBatch') || (is_string($callback) && str_ends_with($callback, ':fetchDataBatch'))) {
          $batch['sets'][$key]['operations'][] = [[ToolUpdateChecker::class, 'refreshBatch'], []];
          $batch['sets'][$key]['total']++;
          $batch['sets'][$key]['count']++;
          return;
        }
      }
    }
  }

}
