<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Kernel;

use Drupal\book\Hook\BookTokenHooks;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for Book token hooks.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
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
   * @throws \Exception
   */
  public function testJoinedParentPathToken(): void {
    $root = Node::create([
      'type' => 'page',
      'title' => 'Book',
      'book' => ['bid' => 'new'],
    ]);
    $root->save();

    $bid = $root->id();
    $pid = $bid;

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

    $hooks = new BookTokenHooks(
      $this->container->get('book.manager'),
      $this->container->get('entity_type.manager')
    );

    $tokens = [
      'book' => '[node:book:parents:join-path]',
    ];

    $bubbleable_metadata = new BubbleableMetadata();
    $target = end($pages);
    $replacements = $hooks->tokens('node', $tokens, ['node' => $target], [], $bubbleable_metadata);

    $this->assertArrayHasKey('[node:book:parents:join-path]', $replacements);
    $this->assertSame('Book/page-1/page-2/page-3', $replacements['[node:book:parents:join-path]']);
  }

}
