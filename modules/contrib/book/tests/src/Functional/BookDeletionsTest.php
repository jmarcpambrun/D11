<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests various deletions around the book module.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookDeletionsTest extends BookTestBase {

  use BookTestTrait;

  /**
   * Tests the access for deleting top-level book nodes.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testBookDelete() {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);
    $edit = [];

    // Ensure that the top-level book node cannot be deleted.
    $this->drupalGet('node/' . $this->book->id() . '/outline/remove');
    $this->assertSession()->statusCodeEquals(403);

    // Ensure that a child book node can be deleted.
    $this->drupalGet('node/' . $nodes[4]->id() . '/outline/remove');
    $this->submitForm($edit, 'Remove');
    $node_storage->resetCache([$nodes[4]->id()]);
    $node4 = $node_storage->load($nodes[4]->id());
    $this->assertEmpty($node4->getBook(), 'Deleting child book node properly allowed.');

    // $nodes[4] is stale, trying to delete it directly will cause an error.
    $node4->delete();
    unset($nodes[4]);

    // Delete all child book nodes and retest top-level node deletion.
    $node_storage->delete($nodes);

    $this->drupalGet('node/' . $this->book->id() . '/outline/remove');
    $this->submitForm($edit, 'Remove');
    $node_storage->resetCache([$this->book->id()]);
    $node = $node_storage->load($this->book->id());
    $this->assertEmpty($node->getBook(), 'Deleting childless top-level book node properly allowed.');

    // Tests directly deleting a book parent.
    $nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->book->toUrl('delete-form'));
    $this->assertSession()->pageTextContains($this->book->label() . ' is part of a book outline, and has associated child pages. If you proceed with deletion, the child pages will be relocated automatically.');
    // Delete parent, and visit a child page.
    $this->drupalGet($this->book->toUrl('delete-form'));
    $this->submitForm([], 'Delete');
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($nodes[0]->label());
    // The book parents should be updated.
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_storage->resetCache();
    $child = $node_storage->load($nodes[0]->id());
    $this->assertEquals($child->id(), $child->getBook()['bid'], 'Child node book ID updated when parent is deleted.');
    // 3rd-level children should now be 2nd-level.
    $second = $node_storage->load($nodes[1]->id());
    $this->assertEquals($child->id(), $second->getBook()['bid'], '3rd-level child node is now second level when top-level node is deleted.');

    // Set the child page book id to deleted book id, and visit the child page.
    $node = $node_storage->load($nodes[0]->id());
    $node->setBookKey('bid', $nodes[0]->getBook()['bid']);
    $node->save();
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->pageTextContains($nodes[0]->label());
  }

  /**
   * Testing updated hierarchy after we remove one node from outline.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testBookParentDelete(): void {
    $nodes = $this->createBook();
    $this->drupalLogin($this->adminUser);

    /*
     * Add Node 5 under Node 1.
     * Book
     *  |- Node 0
     *    |- Node 1
     *      | - Node 5
     *    |- Node 2
     *  |- Node 3
     *  |- Node 4
     */
    $book = $this->book;
    $child = 5;
    $nodes[$child] = $this->createBookNode($book->id(), $nodes[1]->id());

    // Remove Node 1 from outline which should move Node 5 up 1 level.
    $this->drupalGet($nodes[1]->toUrl()->toString() . '/outline/remove');
    $this->submitForm([], 'Remove');
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_storage->resetCache();

    $this->drupalLogout();
    $this->drupalLogin($this->webUser);

    $this->checkBookNode($nodes[0], [$nodes[2], $nodes[5]], FALSE, $book, FALSE, [$book]);
  }

  /**
   * Delete book delete operation.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBookDeleteOperation(): void {
    $this->createBook();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/book');
    $this->assertSession()->pageTextContains($this->book->label());
    $this->assertSession()->linkExists('Delete entire book');
    $this->drupalGet('admin/structure/book/delete/1');
    $this->submitForm([], 'Delete');
    $this->assertSession()->addressEquals('admin/structure/book');
    $this->assertSession()->pageTextContains('The ' . $this->book->label() . ' has been deleted.');
    $this->drupalGet('admin/structure/book');
    $this->assertSession()->pageTextNotContains($this->book->label());
  }

}
