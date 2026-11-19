<?php

namespace Drupal\Tests\upgrade_status\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Url;

/**
 * Tests the UI before and after running scans.
 *
 * @group upgrade_status
 */
#[RunTestsInSeparateProcesses]
#[Group('upgrade_status')]
class UpgradeStatusUiTest extends UpgradeStatusTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer software updates']));
  }

  /**
   * Test the user interface before running a scan.
   */
  public function testUiBeforeScan() {
    $this->drupalGet(Url::fromRoute('upgrade_status.report'));
    $assert_session = $this->assertSession();

    $assert_session->buttonExists('Analyze selected');
    $assert_session->buttonExists('Export selected as HTML');

    // No project is analyzed yet. Custom projects are in the Not analyzed
    // group, each with a button to analyze it.
    $rows = $this->getSession()->getPage()->findAll('css', '#edit-scan tr[class*="project-"]');
    $this->assertNotEmpty($rows);
    foreach ($rows as $row) {
      $this->assertNotNull($row->findButton('Analyze'));
    }
    $assert_session->elementExists('css', '#edit-scan input[name="scan[data][list][upgrade_status_test_error]"]');
    $assert_session->pageTextContains('Select Upgrade status test error');
    $assert_session->pageTextNotContains('problems');

    // The report accuracy card is above the summary card. It shows when
    // available updates were checked, with a link to check them.
    $assert_session->elementExists('css', '#us-accuracy + .us-summary');
    $assert_session->elementTextContains('css', '#us-accuracy', 'Available updates for all projects were never checked');
    $assert_session->elementExists('css', '#us-accuracy a[href*="admin/reports/updates/check"]');

    // A newer release of PHPStan from the update check is noted there too,
    // with the command to update it.
    $assert_session->pageTextNotContains('Newer analysis tools are available');
    if ($release = $this->addPhpStanRelease()) {
      $this->drupalGet(Url::fromRoute('upgrade_status.report'));
      $assert_session->elementTextContains('css', '#us-accuracy', 'Newer analysis tools are available');
      $assert_session->elementTextContains('css', '#us-accuracy .us-tool-table', $release);
      $assert_session->responseContains('<code data-us-command>composer update phpstan/phpstan --with-dependencies</code>');
      $assert_session->pageTextNotContains('Newer versions of Upgrade Status');
    }

    // A post update function that did not run fails the database updates
    // check, which links to update.php.
    $post_updates = \Drupal::keyValue('post_update');
    $existing = $post_updates->get('existing_updates', []);
    if ($existing && $this->getSession()->getPage()->hasContent('Database updates')) {
      $assert_session->elementTextContains('css', '#edit-environment', 'None pending');
      $post_updates->set('existing_updates', array_slice($existing, 1));
      $this->drupalGet(Url::fromRoute('upgrade_status.report'));
      $assert_session->linkExists('1 update pending');
      $assert_session->linkByHrefExists('/update.php');
      $post_updates->set('existing_updates', $existing);
    }
  }

  /**
   * Test the user interface after running a scan.
   *
   * The messages are tested in the kernel tests of the analyzers. This test
   * checks how the results are shown and exported.
   */
  public function testUiAfterScan() {
    $this->runFullScan();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $assert_session->buttonExists('Analyze selected');
    $assert_session->buttonExists('Export selected as HTML');
    // The group has the same description as in the report command.
    $assert_session->pageTextContains('Fix the problems in the code of these custom projects.');

    // Projects with problems show how many, compatible projects show none.
    $scan_result = fn (string $name): string => $page->find('css', ".project-$name")->getText();
    $this->assertMatchesRegularExpression('/\d+ problems?/', $scan_result('upgrade_status_test_error'));
    $this->assertMatchesRegularExpression('/\d+ problems?/', $scan_result($this->getModuleWithoutNextMajorSupport()));
    $this->assertMatchesRegularExpression('/\d+ problems?/', $scan_result('upgrade_status_test_contrib_error'));
    $this->assertStringContainsString('No problems found', $scan_result('upgrade_status_test_13_compatible'));

    // The parent module is analyzed, and the submodule is not listed.
    $this->assertStringNotContainsString('Not analyzed', $scan_result('upgrade_status_test_submodules'));
    $this->assertEmpty($page->find('css', '.project-upgrade_status_test_submodules_a'));

    // The summary counts the groups and links to them.
    $assert_session->elementExists('css', '.us-summary a[href="#edit-manual"]');
    $assert_session->elementExists('css', '#edit-manual');

    $next_major = $this->getDrupalCoreMajorVersion() + 1;

    // Contributed projects without a compatible release point to their
    // Drupal.org issues.
    $this->drupalGet('/admin/reports/upgrade-status/project/upgrade_status_test_contrib_error');
    $assert_session->pageTextContains('Work on the fixes with the community');
    $assert_session->pageTextContains('Look for an issue or merge request with a fix first. Try the fix on this site, adjust it if needed, and post your feedback in the issue');
    $assert_session->linkByHrefExists('https://drupal.org/project/issues/upgrade_status_test_contrib_error?text=Drupal+' . $next_major . '&search=Drupal+' . $next_major . '&state=all');

    // The result page of a project lists the files with problems.
    $this->drupalGet('/admin/reports/upgrade-status/project/upgrade_status_test_error');
    $this->assertSession()->pageTextContains('Upgrade status test error');
    $this->assertSession()->pageTextMatches('/\d+ errors?, \d+ warnings? in \d+ files?/');
    $this->assertSession()->pageTextContains('UpgradeStatusTestErrorController.php');
    $this->assertSession()->buttonExists('Analyze again');
    $this->assertSession()->linkExists('Back to Drupal ' . $next_major . ' upgrade status');

    // Go forward to the export page and assert that still contains the results
    // as well as an export specific title.
    $this->clickLink('Export as HTML');
    $this->assertSession()->pageTextContains('Upgrade Status report');
    $this->assertSession()->pageTextContains('Upgrade status test error');
    $this->assertSession()->pageTextContains('Custom projects');
    $this->assertSession()->pageTextNotContains('Contributed projects');
    $this->assertSession()->pageTextMatches('/\d+ errors?, \d+ warnings? in \d+ files?/');
    $this->assertSession()->pageTextContains('UpgradeStatusTestErrorController.php');
    // The export has no actions of the result page.
    $this->assertSession()->buttonNotExists('Analyze again');

    // Go back to the results page and click over to exporting in Markdown.
    $this->drupalGet('/admin/reports/upgrade-status/project/upgrade_status_test_error');
    $this->clickLink('Export as Markdown');
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/markdown');
    // The file name and the line under the title have the site and the date.
    $this->assertSession()->responseHeaderMatches('Content-Disposition', '/filename="upgrade-status-drupal-results-upgrade_status_test_error-\d{4}-\d{2}-\d{2}-\d{4}\.md"/');
    $this->assertSession()->responseContains('# Upgrade Status results for Drupal ');
    $this->assertSession()->responseContains("\n\nFor Drupal, made on ");
    $this->assertSession()->responseMatches('/^## Upgrade status test error.* \\(upgrade_status_test_error\\)$/m');
    $this->assertSession()->responseContains('Custom project. Analyzed ');
    $this->assertSession()->responseNotContains('Contributed project.');
    $this->assertSession()->responseMatches('/\d+ errors? found\./');
    $this->assertSession()->responseContains('UpgradeStatusTestErrorController.php');

    // Run partial export of multiple projects.
    $edit = [
      'manual[data][list][upgrade_status_test_error]' => TRUE,
      // This module only has the info file to update.
      'requirements[data][list][' . $this->getModuleWithoutNextMajorSupport() . ']' => TRUE,
      'collaborate[data][list][upgrade_status_test_contrib_error]' => TRUE,
    ];
    // The HTML export is checked as page text. The Markdown and CSV exports
    // are not HTML, so they are checked as they are.
    $expected = [
      'Export selected as HTML' => [
        'Contributed projects',
        'Custom projects',
        'pageTextContains',
        'pageTextNotContains',
      ],
      'Export selected as Markdown' => [
        'Contributed project.',
        'Custom project.',
        'responseContains',
        'responseNotContains',
      ],
      'Export selected as CSV' => [
        'Contributed module',
        'Custom module',
        'responseContains',
        'responseNotContains',
      ],
    ];
    foreach ($expected as $button => [$contrib, $custom, $contains, $not_contains]) {
      $this->drupalGet('admin/reports/upgrade-status');
      $this->submitForm($edit, $button);
      $this->assertSession()->$contains($contrib);
      $this->assertSession()->$contains($custom);
      $this->assertSession()->$contains('Upgrade status test contrib error');
      $this->assertSession()->$contains('Upgrade status test ' . $this->getDrupalCoreMajorVersion() . ' compatible');
      $this->assertSession()->$contains('Upgrade status test error');
      $this->assertSession()->$not_contains('Upgrade status test root module');
      $this->assertSession()->$not_contains('Upgrade status test 13 compatible');
      $this->assertSession()->$contains('UpgradeStatusTestErrorController.php');
    }
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/csv');
    $this->assertSession()->responseHeaderContains('Content-Disposition', '.csv"');
    // The full report and the results of all projects can be exported.
    $formats = [
      'report-html' => 'text/html',
      'report-markdown' => 'text/markdown',
      'codeclimate' => 'application/json',
      'checkstyle' => 'application/xml',
      'csv' => 'text/csv',
    ];
    foreach ($formats as $format => $type) {
      $this->drupalGet('admin/reports/upgrade-status/export-all/' . $format);
      $this->assertSession()->responseHeaderContains('Content-Type', $type);
      $this->assertSession()->responseHeaderContains('Content-Disposition', 'filename="upgrade-status-drupal-' . (str_starts_with($format, 'report-') ? 'report-' : 'results-'));
      $this->assertSession()->responseContains('UpgradeStatusTestErrorController.php');
    }
    // This contrib module has a different project name. The link to its
    // Drupal.org issues in the report uses that.
    $this->drupalGet('admin/reports/upgrade-status/export-all/report-html');
    $this->assertSession()->elementTextContains('css', '.us-document-meta', 'For Drupal, made on ');
    $this->assertSession()->linkByHrefExists('https://drupal.org/project/issues/upgrade_status_test_contributed_12_compatible?text=Drupal+' . $next_major . '&search=Drupal+' . $next_major . '&state=all');
  }

}
