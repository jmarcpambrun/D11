<?php

namespace Drupal\Tests\book\Kernel\Migrate\d6;

use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Upgrade book structure.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class MigrateBookTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['book', 'book_content_type', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('book', ['book']);
    $this->installSchema('node', ['node_access']);
    $this->migrateUsers(FALSE);
    $this->installConfig('book');
    $this->installConfig(['node']);
    $this->installConfig(['book_content_type']);
    $book_config = $this->config('book.settings');
    $allowed_types = $book_config->get('allowed_types') ?? [];
    $allowed_types[] = [
      'content_type' => 'book',
      'child_type' => 'book',
    ];
    $book_config->set('allowed_types', $allowed_types)->save();

    $this->executeMigrations([
      'd6_node_settings',
      'd6_node_type',
      'd6_node',
      'd6_book',
    ]);
  }

  /**
   * Gets the path to the fixture file.
   */
  protected function getFixtureFilePath(): string {
    return __DIR__ . '/../../../../fixtures/drupal6.php';
  }

  /**
   * Tests the Drupal 6 book structure to Drupal 8 migration.
   */
  public function testBook(): void {
    $nodes = Node::loadMultiple([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $this->assertSame('1', $nodes[1]->getBook()['bid']);
    $this->assertSame('0', $nodes[1]->getBook()['pid']);

    $this->assertSame('1', $nodes[2]->getBook()['bid']);
    $this->assertSame('1', $nodes[2]->getBook()['pid']);

    $this->assertSame('1', $nodes[3]->getBook()['bid']);
    $this->assertSame('1', $nodes[3]->getBook()['pid']);

    $this->assertSame('1', $nodes[4]->getBook()['bid']);
    $this->assertSame('3', $nodes[4]->getBook()['pid']);

    $this->assertSame('1', $nodes[5]->getBook()['bid']);
    $this->assertSame('3', $nodes[5]->getBook()['pid']);

    $this->assertSame('6', $nodes[6]->getBook()['bid']);
    $this->assertSame('0', $nodes[6]->getBook()['pid']);

    $this->assertSame('6', $nodes[7]->getBook()['bid']);
    $this->assertSame('6', $nodes[7]->getBook()['pid']);

    $this->assertSame('6', $nodes[8]->getBook()['bid']);
    $this->assertSame('6', $nodes[8]->getBook()['pid']);

    $this->assertSame('6', $nodes[9]->getBook()['bid']);
    $this->assertSame('8', $nodes[9]->getBook()['pid']);

    $this->assertSame('6', $nodes[10]->getBook()['bid']);
    $this->assertSame('8', $nodes[10]->getBook()['pid']);

    $tree = \Drupal::service('book.manager')->bookTreeAllData(1);
    $this->assertSame('1', $tree['50000 Birds 1']['link']['nid']);
    $this->assertSame('2', $tree['50000 Birds 1']['below']['50000 Emu 2']['link']['nid']);
    $this->assertSame([], $tree['50000 Birds 1']['below']['50000 Emu 2']['below']);
    $this->assertSame('3', $tree['50000 Birds 1']['below']['50000 Parrots 3']['link']['nid']);
    $this->assertSame('4', $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kea 4']['link']['nid']);
    $this->assertSame([], $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kea 4']['below']);
    $this->assertSame('5', $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kakapo 5']['link']['nid']);
    $this->assertSame([], $tree['50000 Birds 1']['below']['50000 Parrots 3']['below']['50000 Kakapo 5']['below']);

    $tree = \Drupal::service('book.manager')->bookTreeAllData(6);
    $this->assertSame('6', $tree['50000 Tree 6']['link']['nid']);
    $this->assertSame('7', $tree['50000 Tree 6']['below']['50000 Rimu 7']['link']['nid']);
    $this->assertSame([], $tree['50000 Tree 6']['below']['50000 Rimu 7']['below']);
    $this->assertSame('8', $tree['50000 Tree 6']['below']['50000 Oaks 8']['link']['nid']);
    $this->assertSame('9', $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 Cork oak 9']['link']['nid']);
    $this->assertSame([], $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 Cork oak 9']['below']);
    $this->assertSame('10', $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 White oak 10']['link']['nid']);
    $this->assertSame([], $tree['50000 Tree 6']['below']['50000 Oaks 8']['below']['50000 White oak 10']['below']);

    // Set the d6_book migration to update and re-run the migration.
    $id_map = $this->migration->getIdMap();
    $id_map->prepareUpdate();
    $this->executeMigration('d6_book');
  }

}
