<?php

declare(strict_types=1);

namespace Drupal\Tests\book\FunctionalJavascript;

use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Book JavaScript functionality.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookOutlineFormTest extends BookJavascriptBaseTest {

  /**
   * Test book parent selector JavaScript on an outline form.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBookOutlineForm(): void {
    $book = Node::create([
      'type' => 'book',
      'title' => 'Book',
      'book' => ['bid' => 'new'],
    ]);
    $book->save();
    $page1 = Node::create([
      'type' => 'book',
      'title' => '1st page',
      'book' => ['bid' => $book->id(), 'pid' => $book->id(), 'weight' => 0],
    ]);
    $page1->save();
    $page2 = Node::create([
      'type' => 'book',
      'title' => '2nd page',
      'book' => ['bid' => $book->id(), 'pid' => $book->id(), 'weight' => 1],
    ]);
    $page2->save();
    $page3 = Node::create([
      'type' => 'book',
      'title' => '3rd level page',
      'book' => ['bid' => $book->id(), 'pid' => $page2->id(), 'weight' => 1],
    ]);
    $page3->save();
    $page4 = Node::create([
      'type' => 'book',
      'title' => '4th level page',
      'book' => ['bid' => $book->id(), 'pid' => $page3->id(), 'weight' => 1],
    ]);
    $page4->save();

    $this->drupalLogin($this->drupalCreateUser([
      'bypass node access',
      'create new books',
      'create book content',
      'edit any book content',
      'delete any book content',
      'add content to books',
      'reorder book pages',
      'administer book outlines',
    ]));

    $this->drupalGet($page3->toUrl()->toString() . '/edit');
    $this->assertSession()->pageTextContains('Currently Selected Parent: 2nd page');

    $this->drupalGet('node/add/book');
    $session = $this->getSession();
    $assert = $this->assertSession();
    $session->getPage()->selectFieldOption('edit-book-bid', $book->id());
    $assert->assertWaitOnAjaxRequest();
    $assert->selectExists('seb_0_1')->selectOption($page2->id());
    $assert->selectExists('seb_0_2')->selectOption($page3->id());
    $this->submitForm(['title[0][value]' => 'Testing Selector'], 'Save');
    // These links should exist if placed correctly.
    $this->assertSession()->linkExists('3rd level page');
    $this->assertSession()->linkExists('4th level page');
  }

  /**
   * Tests book outline AJAX request.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testBookAddOutline(): void {
    $this->drupalLogin($this->drupalCreateUser(['create book content', 'create new books', 'add content to books']));
    $this->drupalGet('node/add/book');
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    $page->find('css', '#edit-book')->click();
    $book_select = $page->findField("book[bid]");
    $book_select->setValue('new');
    $assert_session->waitForText('This will be the top-level page in this book.');
    $assert_session->pageTextContains('This will be the top-level page in this book.');
    $assert_session->pageTextNotContains('No book selected.');
    $book_select->setValue(0);
    $assert_session->waitForText('No book selected.');
    $assert_session->pageTextContains('No book selected.');
    $assert_session->pageTextNotContains('This will be the top-level page in this book.');
  }

}
