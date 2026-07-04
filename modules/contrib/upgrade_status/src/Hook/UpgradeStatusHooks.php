<?php

namespace Drupal\upgrade_status\Hook;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\upgrade_status\ProjectCollector;

/**
 * Hook implementations for upgrade_status.
 */
class UpgradeStatusHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'upgrade_status.report':
        $help = '<p>' . $this->t('Run the report to find out if there are detectable compatibility errors with the modules and themes installed on your site.');
        // @todo Link to a relevant page for the Drupal 9 to 10 process when available.
        if (ProjectCollector::getDrupalCoreMajorVersion() < 9) {
          $help .= ' ' . $this->t('<a href=":prepare">Read more about preparing your site for Drupal 9</a>.', [
            ':prepare' => 'https://www.drupal.org/docs/9/how-to-prepare-your-drupal-7-or-8-site-for-drupal-9/prepare-a-drupal-8-site-for-drupal-9',
          ]);
        }
        $help .= '</p>';
        return $help;

      case 'help.page.upgrade_status':
        $help = '';
        $help .= '<h3>' . $this->t('About') . '</h3>';
        $help .= '<p>' . $this->t('Upgrade Status scans the code of installed contributed and custom projects on the site, and reports any deprecated code that must be replaced before the next major version. Available project updates are also suggested to keep your site up to date as projects will resolve deprecation errors over time.') . '</p>';
        $help .= '<h3>' . $this->t('How to use') . '</h3>';
        $help .= '<p>' . $this->t('There are no configuration options on Upgrade Status, now that is installed go to <a href=":upgrade_status_report">Administration » Reports » Upgrade status</a> to use', [
          ':upgrade_status_report' => '/admin/reports/upgrade-status',
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
          'projects' => [],
        ],
      ],
      'upgrade_status_ascii_export' => [
        'variables' => [
          'projects' => [],
        ],
      ],
      'upgrade_status_summary_counter' => [
        'variables' => [
          'summary' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_system_info_alter().
   */
  #[Hook('system_info_alter')]
  public function systemInfoAlter(array &$info, Extension $extension, $type) {
    // Always mark upgrade_status as a contrib project regardless of how it
    // was obtained. This makes testing the module results reliably consistent.
    if ($extension->getName() == 'upgrade_status') {
      $info['project'] = $extension->getName();
    }
  }

}
