<?php

declare(strict_types=1);

namespace Drupal\Tests\book\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests key book functionality using the experimental alternate form.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookAlternativeFormTest extends BookJavascriptBaseTest {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $config = \Drupal::configFactory()->getEditable('book.settings');
    $config->set('use_alternative_form', TRUE)->save();

    $this->drupalLogin($this->drupalCreateUser([
      'create book content',
      'create new books',
      'edit any book content',
      'add content to books',
      'administer book outlines',
    ]));
  }

  /**
   * Test creating, editing, and deleting a book.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   * @throws \Behat\Mink\Exception\DriverException
   */
  public function testBookFunctionalityAlternativeForm(): void {
    $session = $this->getSession();
    $assert = $this->assertSession();

    // First, test creating a book.
    $this->drupalGet('node/add/book');
    $assert->pageTextContains('Not in book');
    $assert->pageTextContains('Create or add to existing book');
    $session->getPage()->checkField('book[create_new_book]');
    $assert->waitForText('No book selected');
    $this->submitForm(['title[0][value]' => 'Test Book'], 'Save');
    $assert->pageTextContains('Test Book has been created.');
    $this->drupalGet('/admin/structure/book');
    $assert->pageTextContains('Test Book');

    // Second, test adding a page to the book.
    $this->drupalGet('node/add/book');
    $session->getPage()->checkField('book[create_new_book]');
    $assert->waitForText('No book selected');
    $autocomplete_field = $session->getPage()->find('css', '#edit-book-bid');
    $autocomplete_field->focus();
    $autocomplete_field->setValue('Test Book');
    $assert->waitOnAutocomplete();
    $first_item = $assert->waitForElement('css', '.ui-autocomplete li.ui-menu-item:first-child .ui-menu-item-wrapper');
    $first_item->click();
    $assert->assertWaitOnAjaxRequest();
    $assert->elementExists('css', 'div.select-box-book-extracted__wrapper');
    $this->submitForm(['title[0][value]' => 'Chapter 1'], 'Save');
    $assert->pageTextContains('Chapter 1 has been created.');
    $this->drupalGet('/admin/structure/book/1');
    $assert->elementExists('css', 'input[value="Chapter 1"]');

    // Next, create a second book and move Chapter 1 to it.
    $this->drupalGet('node/add/book');
    $session->getPage()->checkField('book[create_new_book]');
    $assert->waitForText('No book selected');
    $this->submitForm(['title[0][value]' => 'Test Book 2'], 'Save');
    $assert->pageTextContains('Test Book 2 has been created.');
    $this->drupalGet('/admin/structure/book');
    $assert->pageTextContains('Test Book 2');
    $this->drupalGet('/node/2/edit');
    $this->submitForm(['book[bid]' => 'Test Book 2'], 'Save');
    $this->drupalGet('/admin/structure/book/3');
    $assert->elementExists('css', 'input[value="Chapter 1"]');

    // Add a Chapter 1.1 using the "add child link" on the Chapter 1 page.
    $this->drupalGet('/node/3');
    $this->clickLink('Add child page');
    $this->submitForm(['title[0][value]' => 'Sub Chapter 1'], 'Save');
    $assert->pageTextContains('Sub Chapter 1 has been created.');

    // Finally, verify the node form text.
    $this->drupalGet('/node/1/edit');
    $assert->pageTextContains('Currently in a book');
    $assert->pageTextContains('To remove use normal outline workflow');
    $checkbox = $session->getPage()->findField('book[create_new_book]');
    $this->assertTrue($checkbox->hasAttribute('disabled'));
  }

}
