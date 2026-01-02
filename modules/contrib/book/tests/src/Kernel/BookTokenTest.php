<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests for Book token hooks.
 *
 * @group book
 */
class BookTokenTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'book',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('book', ['book']);
  }

  /**
   * Tests book:parents:join-path token generation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testJoinedParentPathToken(): void {
    // Create the book root.
    $root = Node::create([
      'type' => 'page',
      'title' => 'Book',
      'book' => ['bid' => 'new'],
    ]);
    $root->save();

    $bid = $root->id();
    $pid = $bid;

    // Create a 4-level deep hierarchy.
    $pages = [];
    for ($i = 1; $i <= 4; $i++) {
      $page = Node::create([
        'type' => 'page',
        'title' => 'page-' . $i,
        'book' => [
          'bid' => $bid,
          'pid' => $pid,
        ],
      ]);
      $page->save();
      $pages[] = $page;
      $pid = $page->id();
    }

    $tokens = [
      'book' => '[node:book:parents:join-path]',
    ];

    $bubbleable_metadata = new BubbleableMetadata();
    $target = end($pages);

    $replacements = \Drupal::moduleHandler()->invokeAll(
      'tokens',
      [
        'node',
        $tokens,
        ['node' => $target],
        [],
        $bubbleable_metadata,
      ]
    );

    $this->assertArrayHasKey('[node:book:parents:join-path]', $replacements);
    $this->assertSame('Book/page-1/page-2/page-3', $replacements['[node:book:parents:join-path]']);
  }

}
