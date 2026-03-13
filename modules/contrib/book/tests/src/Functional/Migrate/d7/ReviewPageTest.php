<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional\Migrate\d7;

use Drupal\Tests\migrate_drupal_ui\Functional\NoMultilingualReviewPageTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Review page.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class ReviewPageTest extends NoMultilingualReviewPageTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['book', 'book_content_type'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture($this->getModulePath('book') . '/tests/fixtures/drupal7.php');
  }

  /**
   * Tests the review page.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMigrateUpgradeReviewPage(): void {
    $this->prepare();
    // Start the upgrade process.
    $this->submitCredentialForm();

    $session = $this->assertSession();
    $this->submitForm([], 'I acknowledge I may lose data. Continue anyway.');
    $session->statusCodeEquals(200);

    // Confirm that Book will be upgraded.
    $session->elementExists('xpath', "//td[contains(@class, 'checked') and text() = 'Book']");
    $session->elementNotExists('xpath', "//td[contains(@class, 'error') and text() = 'Book']");
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceBasePath(): string {
    return __DIR__;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAvailablePaths(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getMissingPaths(): array {
    return [];
  }

}
