<?php

namespace Drupal\Tests\book\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the BookManager class.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookManagerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'book',
    'book_content_type',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->installSchema('book', ['book']);
    $this->installConfig(['node', 'book', 'book_content_type', 'field']);
    $this->container->get('current_user')->setAccount($this->createUser(['administer book outlines']));

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('book.settings');
    $settings = [];
    $settings[] = [
      'content_type' => 'book',
      'child_type' => 'book',
    ];
    $config->set('allowed_types', $settings)->save();
  }

  /**
   * Tests the bookTreeAllData() method.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testBookTreeAllData(): void {
    // Create a book with the following structure
    // Book
    // -page 1
    // --page 2
    // ---page 3
    // ----page 4.
    $book = Node::create([
      'type' => 'book',
      'title' => 'Book',
      'book' => ['bid' => 'new'],
    ]);
    $book->save();

    $bid = $book->id();
    $pid = $book->id();

    for ($i = 1; $i <= 4; $i++) {
      $page = Node::create([
        'type' => 'book',
        'title' => 'page-' . $i,
        'book' => ['bid' => $bid, 'pid' => $pid],
      ]);
      $page->save();
      $pid = $page->id();
    }

    /** @var \Drupal\book\BookManagerInterface $bookManager */
    $bookManager = $this->container->get('book.manager');

    // Test bookTreeAllData() without giving max_depth and min_depth values.
    $bookTreeAllData = $bookManager->bookTreeAllData($bid);
    // This will contain all tree data eg:
    // Book
    // -page 1
    // --page 2
    // ---page 3
    // ----page 4.
    $this->assertNotEmpty($bookTreeAllData);
    $this->checkBookTreeAllDataAvailable($bookTreeAllData, 5, 1);
    $firstKey = array_key_first($bookTreeAllData);
    $this->assertEquals(1, $bookTreeAllData[$firstKey]['link']['depth']);
    $this->assertEquals('Book', $bookTreeAllData[$firstKey]['link']['title']);

    // Test bookTreeAllData() with a max_depth value.
    $bookTreeAllData = $bookManager->bookTreeAllData($bid, NULL, 3);
    // This will contain following tree data
    // Book
    // -page 1
    // --page 2.
    $this->checkBookTreeAllDataAvailable($bookTreeAllData, 3, 1);
    $firstKey = array_key_first($bookTreeAllData);
    $this->assertEquals(1, $bookTreeAllData[$firstKey]['link']['depth']);
    $this->assertEquals('Book', $bookTreeAllData[$firstKey]['link']['title']);

    // Test bookTreeAllData() with a min_depth value.
    $bookTreeAllData = $bookManager->bookTreeAllData($bid, NULL, NULL, 3);
    // This will contain following tree data
    // --page 2
    // ---page 3
    // ----page 4.
    $this->checkBookTreeAllDataAvailable($bookTreeAllData, 5, 3);
    $firstKey = array_key_first($bookTreeAllData);
    $this->assertEquals(3, $bookTreeAllData[$firstKey]['link']['depth']);
    $this->assertEquals('page-2', $bookTreeAllData[$firstKey]['link']['title']);

    // Test bookTreeAllData() with max_depth and min_depth.
    $bookTreeAllData = $bookManager->bookTreeAllData($bid, NULL, 3, 2);
    // This will contain following tree data
    // -page 1
    // --page 2.
    $this->checkBookTreeAllDataAvailable($bookTreeAllData, 3, 2);
    $firstKey = array_key_first($bookTreeAllData);
    $this->assertEquals(2, $bookTreeAllData[$firstKey]['link']['depth']);
    $this->assertEquals('page-1', $bookTreeAllData[$firstKey]['link']['title']);

    // Test bookTreeAllData() with min_depth > max_depth.
    $bookTreeAllData = $bookManager->bookTreeAllData($bid, NULL, 2, 3);
    $this->assertEmpty($bookTreeAllData);
  }

  /**
   * Tests the book getAllBooks() function.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testBookGetAllBooks(): void {
    $book1 = Node::create([
      'type' => 'book',
      'title' => '1st book',
      'book' => ['bid' => 'new', 'weight' => 3],
    ]);
    $book1->save();

    $book2 = Node::create([
      'type' => 'book',
      'title' => '2nd book',
      'book' => ['bid' => 'new', 'weight' => 1],
    ]);
    $book2->save();

    $book3 = Node::create([
      'type' => 'book',
      'title' => '3rd book',
      'book' => ['bid' => 'new', 'weight' => 2],
    ]);
    $book3->save();

    $bookManager = $this->container->get('book.manager');
    $books = $bookManager->getAllBooks();

    $book_title = [];
    foreach ($books as $book) {
      $book_title[] = $book['title'];
    }

    $this->assertSame(['2nd book', '3rd book', '1st book'], $book_title);

    $this->config('book.settings')
      ->set('book_sort', 'title')
      ->save();

    $books = $bookManager->getAllBooks();

    $book_title = [];
    foreach ($books as $book) {
      $book_title[] = $book['title'];
    }

    $this->assertSame(['1st book', '2nd book', '3rd book'], $book_title);
  }

  /**
   * Test a book tree data array.
   *
   * @param array $bookTreeAllData
   *   A book tree data array.
   * @param int $maxDepth
   *   Maximum depth of the book tree data array.
   * @param int $currentDepth
   *   Current/Starting depth of the book tree data array.
   *
   * @internal
   */
  protected function checkBookTreeAllDataAvailable(array $bookTreeAllData, int $maxDepth, int $currentDepth): void {
    $firstKey = array_key_first($bookTreeAllData);
    if ($currentDepth != $maxDepth) {
      $this->assertNotEmpty($bookTreeAllData[$firstKey]['below']);
      $this->checkBookTreeAllDataAvailable($bookTreeAllData[$firstKey]['below'], $maxDepth, $currentDepth + 1);
    }
    else {
      $this->assertEmpty($bookTreeAllData[$firstKey]['below']);
    }
  }

}
