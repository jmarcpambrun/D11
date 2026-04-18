<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Kernel;

use Drupal\book\Hook\BookTokenHooks;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for Book token hooks.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookTokenTest extends KernelTestBase {

  use ContentTypeCreationTrait;

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
    $this->installConfig(['node', 'book']);

    $this->createContentType(['type' => 'page']);
    $book_config = $this->config('book.settings');
    $allowed_types = $book_config->get('allowed_types') ?? [];
    $allowed_types[] = [
      'content_type' => 'page',
      'child_type' => 'page',
    ];
    $book_config->set('allowed_types', $allowed_types)->save();
  }

  /**
   * Tests book:parents:join-path token generation with pathauto.
   *
   * @throws \Exception
   */
  public function testJoinedParentPathTokenWithPathauto(): void {
    $this->enableModules(['pathauto', 'path_alias', 'token']);
    $root = Node::create([
      'type' => 'page',
      'title' => 'Book Page',
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
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
    );

    $tokens = [
      'book' => '[node:book:parents:join-path]',
    ];

    $bubbleable_metadata = new BubbleableMetadata();
    $target = end($pages);
    $replacements = $hooks->tokens('node', $tokens, ['node' => $target], [], $bubbleable_metadata);

    $this->assertArrayHasKey('[node:book:parents:join-path]', $replacements);
    $this->assertSame('BookPage/page1/page2/page3', $replacements['[node:book:parents:join-path]']);
  }

  /**
   * Tests book:parents:join-path token generation.
   *
   * @throws \Exception
   */
  public function testJoinedParentPathToken(): void {
    $root = Node::create([
      'type' => 'page',
      'title' => 'Book Page',
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
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
    );

    $tokens = [
      'book' => '[node:book:parents:join-path]',
    ];

    $bubbleable_metadata = new BubbleableMetadata();
    $target = end($pages);
    $replacements = $hooks->tokens('node', $tokens, ['node' => $target], [], $bubbleable_metadata);

    $this->assertSame('Book-Page/page-1/page-2/page-3', $replacements['[node:book:parents:join-path]']);
  }

  /**
   * Tests book token replacements for title, bid, depth, weight, and parent.
   *
   * @throws \Exception
   */
  public function testBookTokens(): void {
    $root = Node::create([
      'type' => 'page',
      'title' => 'My Book',
      'book' => ['bid' => 'new'],
    ]);
    $root->save();

    $child = Node::create([
      'type' => 'page',
      'title' => 'Chapter One',
      'book' => [
        'bid' => $root->id(),
        'pid' => $root->id(),
        'weight' => 5,
      ],
    ]);
    $child->save();

    $hooks = new BookTokenHooks(
      $this->container->get('book.manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
    );

    $bubbleable_metadata = new BubbleableMetadata();

    // Test tokens using the full token name format.
    $tokens = [
      'book:title' => '[node:book:title]',
      'book:bid' => '[node:book:bid]',
      'book:depth' => '[node:book:depth]',
      'book:weight' => '[node:book:weight]',
      'book:parent' => '[node:book:parent]',
    ];

    $replacements = $hooks->tokens('node', $tokens, ['node' => $child], [], $bubbleable_metadata);

    $this->assertSame('My Book', $replacements['[node:book:title]']);
    $this->assertEquals($root->id(), $replacements['[node:book:bid]']);
    $this->assertEquals(2, $replacements['[node:book:depth]']);
    $this->assertEquals(5, $replacements['[node:book:weight]']);
    $this->assertSame('My Book', $replacements['[node:book:parent]']);

    // Test the 'book' short name format (how the token system may pass them).
    $chained_tokens = [
      'book' => '[node:book:title]',
    ];
    $chained_replacements = $hooks->tokens('node', $chained_tokens, ['node' => $child], [], $bubbleable_metadata);
    $this->assertSame('My Book', $chained_replacements['[node:book:title]']);

    // Test root node has no parent token.
    $root_tokens = [
      'book:parent' => '[node:book:parent]',
    ];
    $root_replacements = $hooks->tokens('node', $root_tokens, ['node' => $root], [], $bubbleable_metadata);
    $this->assertArrayNotHasKey('[node:book:parent]', $root_replacements);
  }

}
