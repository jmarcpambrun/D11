<?php

namespace Drupal\Tests\book\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the Book module handles pending revisions correctly.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookPendingRevisionTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'book',
    'book_content_type',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('book', ['book']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'book', 'book_content_type', 'field']);
  }

  /**
   * Tests pending revision handling for books.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testBookWithPendingRevisions(): void {
    $content_type = NodeType::create([
      'type' => $this->randomMachineName(),
      'name' => $this->randomString(),
    ]);
    $content_type->save();
    $book_config = $this->config('book.settings');
    $allowed_types = $book_config->get('allowed_types') ?: [];

    $allowed_types[] = [
      'content_type' => $content_type->id(),
      'child_type' => $content_type->id(),
    ];

    $book_config->set('allowed_types', $allowed_types)->save();

    // Create two top-level books a child.
    $book_1 = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $book_1->setBookKey('bid', 'new');
    $book_1->save();

    $book_1_child = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $book_1_child->setBookKey('bid', $book_1->id());
    $book_1_child->setBookKey('pid', $book_1->id());
    $book_1_child->save();

    $book_2 = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $book_2->setBookKey('bid', 'new');
    $book_2->save();

    $child = Node::create(['title' => $this->randomString(), 'type' => $content_type->id()]);
    $child->setBookKey('bid', $book_1->id());
    $child->setBookKey('pid', $book_1->id());
    $child->save();

    // Try to move the child to a different book while saving it as a pending
    // revision.
    /** @var \Drupal\book\BookManagerInterface $book_manager */
    $book_manager = $this->container->get('book.manager');

    // Check that the API doesn't allow us to change the book outline for
    // pending revisions.
    $child->setBookKey('bid', $book_2->id());
    $child->setNewRevision();
    $child->isDefaultRevision(FALSE);

    $this->assertFalse($book_manager->updateOutline($child), 'A pending revision can not change the book outline.');

    // Check that the API doesn't allow us to change the book parent for
    // pending revisions.
    $child = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($child->id());
    $child->setBookKey('pid', $book_1_child->id());
    $child->setNewRevision(TRUE);
    $child->isDefaultRevision(FALSE);

    $this->assertFalse($book_manager->updateOutline($child), 'A pending revision can not change the book outline.');

    // Check that the API doesn't allow us to change the book weight for
    // pending revisions.
    $child = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($child->id());
    $child->setBookKey('weight', 2);
    $child->setNewRevision(TRUE);
    $child->isDefaultRevision(FALSE);

    $this->assertFalse($book_manager->updateOutline($child), 'A pending revision can not change the book outline.');
  }

}
