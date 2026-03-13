<?php

namespace Drupal\Tests\book\Kernel\Migrate\d7;

use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the migration of Book settings.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class MigrateBookConfigsTest extends MigrateDrupal7TestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['book', 'book_content_type', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->executeMigration('book_settings');
  }

  /**
   * Gets the path to the fixture file.
   */
  protected function getFixtureFilePath(): string {
    return __DIR__ . '/../../../../fixtures/drupal7.php';
  }

  /**
   * Tests migration of book variables to book.settings.yml.
   *
   * @throws \Exception
   */
  public function testBookSettings(): void {
    $config = $this->config('book.settings');
    $allowed_types = $config->get('allowed_types');

    // Find the 'book' content_type item:
    $book_type = NULL;
    foreach ($allowed_types as $item) {
      if (isset($item['content_type']) && $item['content_type'] === 'book') {
        $book_type = $item;
        break;
      }
    }
    $this->assertSame('book', $book_type['child_type']);
    $this->assertConfigSchema($this->container->get('config.typed'), 'book.settings', $config->get());
  }

}
