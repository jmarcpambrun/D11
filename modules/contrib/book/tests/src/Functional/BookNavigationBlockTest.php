<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\Entity\Node;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Create a book, add pages, and test book interface.
 */
#[Group('book')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
class BookNavigationBlockTest extends BookTestBase {

  /**
   * Tests the functionality of the book navigation block.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBookNavigationBlock(): void {
    $this->drupalLogin($this->adminUser);

    // Enable the block.
    $block = $this->drupalPlaceBlock('book_navigation');

    // Give anonymous users the permission 'node test view'.
    $edit = [];
    $edit[RoleInterface::ANONYMOUS_ID . '[node test view]'] = TRUE;
    $this->drupalGet('admin/people/permissions/' . RoleInterface::ANONYMOUS_ID);
    $this->submitForm($edit, 'Save permissions');
    $this->assertSession()->pageTextContains('The changes have been saved.');

    // Test correct display of the block.
    $nodes = $this->createBook();
    $this->drupalGet('<front>');
    // Book navigation block.
    $this->assertSession()->pageTextContains($block->label());
    // Link to book root.
    $this->assertSession()->pageTextContains($this->book->label());
    // No links to individual book pages.
    $this->assertSession()->pageTextNotContains($nodes[0]->label());

    // Ensure that an unpublished node does not appear in the navigation for a
    // user without access. By unpublishing a parent page, child pages should
    // not appear in the navigation. The node_access_test module is disabled
    // since it interferes with this logic.
    $nodes[0]->setUnPublished();
    $nodes[0]->save();

    // Verify block still appears on unpublished page. Doing this before
    // uninstalling node_access_test.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($block->label());

    /** @var \Drupal\Core\Extension\ModuleInstaller $installer */
    $installer = $this->container->get('module_installer');
    $installer->uninstall(['node_access_test']);
    // @phpstan-ignore-next-line class.notFound
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => \Drupal::service(NodeAccessRebuild::class)->rebuild(), fn() => node_access_rebuild());

    // Verify the user does not have access to the unpublished node.
    $this->assertFalse($nodes[0]->access('view', $this->webUser));

    // Verify the unpublished book page does not appear in the navigation.
    $this->drupalLogin($this->webUser);
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($this->book->toUrl());

    $args = [
      ':label' => 'Book traversal links for ' . $this->book->label(),
    ];
    $xpath = $this->assertSession()->buildXPathQuery('//nav[@aria-label = :label]', $args);
    $this->assertSession()->elementExists('xpath', $xpath);
    $this->assertSession()->responseNotContains($nodes[0]->getTitle());
    $this->assertSession()->responseNotContains($nodes[1]->getTitle());
    $this->assertSession()->responseNotContains($nodes[2]->getTitle());

    // Test that non-book content types are unaffected.
    $this->createContentType(['type' => 'article']);
    $article = Node::create([
      'type' => 'article',
      'title' => 'Article',
    ]);
    $article->save();
    $this->drupalGet($article->toUrl());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the top-level page title setting of the book navigation block.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testBookNavigationBlockWithTopLevelPageTitle(): void {
    // Enable the block.
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'use_top_level_title' => TRUE,
    ]);

    // Create a book.
    $nodes = $this->createBook();

    // Give anonymous users the permission 'node test view'.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['node test view']);

    $book = $this->book;
    // Change the book top-level title.
    $book->setTitle('Top-level node title');
    $book->save();

    $block_xpath = $this->assertSession()->buildXPathQuery('//div[@id = :id]/h2', [
      ':id' => 'block-' . $block->id(),
    ]);

    // Check that the block title is the top-level page title on the book
    // summary.
    $this->drupalGet($book->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->elementTextEquals('xpath', $block_xpath, 'Top-level node title');

    // Check that the block title is the top-level page title on a deep book
    // page.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->elementTextEquals('xpath', $block_xpath, 'Top-level node title');

    // Check for presence of is-active class.
    $this->drupalGet($nodes[2]->toUrl());
    $link = $this->assertSession()->elementExists('xpath', '//a[contains(@href, "' . $nodes[2]->toUrl()->toString() . '")]');
    $this->assertTrue($link->hasAttribute('class'));
    $this->assertEquals('is-active', $link->getAttribute('class'));
  }

  /**
   * Tests the "Show top level item" setting of the book navigation block.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testBookNavigationBlockWithTopLevelPageInHierarchy(): void {
    // Enable the block.
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'show_top_item' => TRUE,
    ]);

    // Create a book.
    $nodes = $this->createBook();

    // Give anonymous users the permission 'node test view'.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['node test view']);

    $book = $this->book;
    // Change the book top-level title.
    $book->setTitle('Top-level node title');
    $book->save();

    $block_xpath = $this->assertSession()->buildXPathQuery('//div[@id = :id]/ul/li/a', [
      ':id' => 'block-' . $block->id(),
    ]);

    // Check that the block title is the top-level page title on the book
    // summary.
    $this->drupalGet($book->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->elementTextEquals('xpath', $block_xpath, 'Top-level node title');

    // Check that the top-level page is considered active.
    $link = $this->assertSession()->elementExists('xpath', $block_xpath);
    $this->assertTrue($link->hasAttribute('class'));
    $this->assertEquals('is-active', $link->getAttribute('class'));

    // Check that the block title is the top-level page title on a deep book
    // page.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->elementTextEquals('xpath', $block_xpath, 'Top-level node title');

    // Check that the top-level page is not considered active.
    $link = $this->assertSession()->elementExists('xpath', $block_xpath);
    $this->assertFalse($link->hasAttribute('class'));
  }

  /**
   * Tests book navigation block access options.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNavigationBlockAccessOptions(): void {
    $this->drupalLogin($this->adminUser);

    // Give anonymous users the permission 'node test view'.
    $edit = [];
    $edit[RoleInterface::ANONYMOUS_ID . '[node test view]'] = TRUE;
    $this->drupalGet('admin/people/permissions/' . RoleInterface::ANONYMOUS_ID);
    $this->submitForm($edit, 'Save permissions');
    $this->assertSession()->pageTextContains('The changes have been saved.');

    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'primary book page',
    ]);

    // Create a book.
    $nodes = $this->createBook();

    $this->drupalLogin($this->webUser);
    // Verify block appears on book page.
    $this->drupalGet($this->book->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Verify block does not appear on child page.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());

    $block->delete();

    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'child book pages',
    ]);

    // Verify block does not appear on book page.
    $this->drupalGet($this->book->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());

    // Verify block does appear on child page.
    $this->drupalGet($nodes[0]->toUrl());
    $this->assertSession()->pageTextContains($block->label());
  }

  /**
   * Tests the book navigation block when an access module is installed.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testNavigationBlockOnAccessModuleInstalled(): void {
    $this->drupalLogin($this->adminUser);
    $this->container->get('theme_installer')->install(['olivero']);
    $this->config('system.theme')->set('default', 'olivero')->save();
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'region' => 'sidebar',
    ]);

    // Give anonymous users the permission 'node test view'.
    $edit = [];
    $edit[RoleInterface::ANONYMOUS_ID . '[node test view]'] = TRUE;
    $this->drupalGet('admin/people/permissions/' . RoleInterface::ANONYMOUS_ID);
    $this->submitForm($edit, 'Save permissions');
    $this->assertSession()->pageTextContains('The changes have been saved.');

    // Create a book.
    $this->createBook();

    // Test correct display of the block to registered users.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->book->id());
    $this->assertSession()->pageTextContains($block->label());
    $this->assertSession()->elementExists('css', '.region--sidebar');
    $this->drupalLogout();

    // Test correct display of the block to anonymous users.
    $this->drupalGet('node/' . $this->book->id());
    $this->assertSession()->pageTextContains($block->label());

    // Test the 'book pages' block_mode setting.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextNotContains($block->label());
    $this->assertSession()->elementNotExists('css', '.region--sidebar');
  }

  /**
   * Tests the book navigation block when book is unpublished.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBookNavigationBlockOnUnpublishedBook(): void {
    // Create a new book.
    $this->createBook();

    // Create administrator user.
    $administratorUser = $this->drupalCreateUser([
      'administer blocks',
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($administratorUser);

    // Enable the block with "Show block only on book pages" mode.
    $this->drupalPlaceBlock('book_navigation', ['block_mode' => 'book pages']);

    // Unpublish book node.
    $edit = ['status[value]' => FALSE];
    $this->drupalGet('node/' . $this->book->id() . '/edit');
    $this->submitForm($edit, 'Save');

    // Test node page.
    $this->drupalGet('node/' . $this->book->id());
    // Unpublished book with "Show block only on book pages" book navigation
    // settings.
    $this->assertSession()->pageTextContains($this->book->label());
  }

  /**
   * Tests books in Book Navigation Block are correctly ordered by weight.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBookBlockOrderByWeight(): void {
    $this->drupalLogin($this->adminUser);

    // Create two books.
    $book1 = $this->createBookNode('new');
    $book2 = $this->createBookNode('new');

    // Change weight of second book, so it should appear above book 1.
    $this->drupalGet('node/' . $book2->id() . '/outline');
    $this->submitForm(['book[weight]' => -5], 'Update book outline');
    $this->assertSession()->statusMessageContains('The book outline has been updated');

    // Place a Book navigation block.
    $this->drupalPlaceBlock('book_navigation');
    $this->drupalGet('<front>');
    $this->assertSession()->responseMatches(sprintf('/%s.*%s/s', $book2->getTitle(), $book1->getTitle()));
  }

  /**
   * Tests the starting_level setting of the book navigation block.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBookNavigationBlockStartingLevel(): void {
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'starting_level' => 2,
      'show_top_item' => TRUE,
    ]);

    // Create a book.
    $nodes = $this->createBook();

    // Give anonymous users the permission 'node test view'.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['node test view']);

    $this->drupalGet($this->book->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->pageTextContains($nodes[0]->label());
    $this->assertSession()->pageTextNotContains($nodes[1]->label());
    $this->assertSession()->pageTextNotContains($nodes[2]->label());
    $this->assertSession()->pageTextContains($nodes[3]->label());
    $this->assertSession()->pageTextContains($nodes[4]->label());

    $this->drupalGet($nodes[0]->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->pageTextContains($nodes[1]->label());
    $this->assertSession()->pageTextContains($nodes[2]->label());
    $this->assertSession()->pageTextNotContains($nodes[3]->label());
    $this->assertSession()->pageTextNotContains($nodes[4]->label());
  }

  /**
   * Tests the max_depth setting of the book navigation block.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBookNavigationBlockMaxDepth(): void {
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'max_depth' => 2,
    ]);

    // Create a book.
    $nodes = $this->createBook();

    // Give anonymous users the permission 'node test view'.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['node test view']);

    $this->drupalGet($this->book->toUrl());
    $html = $this->getSession()->getPage()->getContent();
    $this->assertBlockAppears($block);

    $selector = '#block-' . $block->id();
    $block_element = $this->assertSession()->elementExists('css', $selector);
    $block_text = $block_element->getText();

    $this->assertStringContainsString($nodes[0]->label(), $block_text);
    $this->assertStringNotContainsString($nodes[1]->label(), $block_text);
    $this->assertStringNotContainsString($nodes[2]->label(), $block_text);
    $this->assertStringContainsString($nodes[3]->label(), $block_text);
    $this->assertStringContainsString($nodes[4]->label(), $block_text);

    $this->drupalGet($nodes[0]->toUrl());
    $this->assertBlockAppears($block);

    $selector = '#block-' . $block->id();
    $block_element = $this->assertSession()->elementExists('css', $selector);
    $block_text = $block_element->getText();

    $this->assertStringNotContainsString($nodes[1]->label(), $block_text);
    $this->assertStringNotContainsString($nodes[2]->label(), $block_text);
    $this->assertStringContainsString($nodes[3]->label(), $block_text);
    $this->assertStringContainsString($nodes[4]->label(), $block_text);
  }

  /**
   * Tests the expanded setting of the book navigation block.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBookNavigationBlockExpandedSetting(): void {
    $block = $this->drupalPlaceBlock('book_navigation', [
      'block_mode' => 'book pages',
      'starting_level' => 2,
      'show_top_item' => TRUE,
      'expanded' => TRUE,
    ]);

    // Create a book.
    $nodes = $this->createBook();

    // Give anonymous users the permission 'node test view'.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['node test view']);

    $this->drupalGet($this->book->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->pageTextContains($nodes[0]->label());
    $this->assertSession()->pageTextContains($nodes[1]->label());
    $this->assertSession()->pageTextContains($nodes[2]->label());
    $this->assertSession()->pageTextContains($nodes[3]->label());
    $this->assertSession()->pageTextContains($nodes[4]->label());

    $this->drupalGet($nodes[0]->toUrl());
    $this->assertBlockAppears($block);
    $this->assertSession()->pageTextContains($nodes[1]->label());
    $this->assertSession()->pageTextContains($nodes[2]->label());
    $this->assertSession()->pageTextNotContains($nodes[3]->label());
    $this->assertSession()->pageTextNotContains($nodes[4]->label());
  }

  /**
   * Tests the book_select option.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBookNavigationBlockSelectedBook(): void {
    $book_one_nodes = $this->createBook();
    $book_one = $this->book;

    $book_two_nodes = $this->createBook();
    $book_two = $this->book;

    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content',
      'node test view',
    ]);

    $block = $this->drupalPlaceBlock('book_navigation', [
      'book_select' => $book_one->id(),
      'show_top_item' => TRUE,
      'expanded' => TRUE,
    ]);

    $selector = '#block-' . $block->id();

    // Visit a page in book two.
    $this->drupalGet($book_two_nodes[0]->toUrl());
    $this->assertBlockAppears($block);

    $block_element = $this->assertSession()->elementExists('css', $selector);
    $block_text = $block_element->getText();

    // The selected book's nodes should appear.
    $this->assertStringContainsString($book_one_nodes[0]->label(), $block_text);
    $this->assertStringContainsString($book_one_nodes[3]->label(), $block_text);
    $this->assertStringContainsString($book_one_nodes[4]->label(), $block_text);

    // The current page's book should not be shown instead.
    $this->assertStringNotContainsString($book_two_nodes[0]->label(), $block_text);
    $this->assertStringNotContainsString($book_two_nodes[3]->label(), $block_text);
    $this->assertStringNotContainsString($book_two_nodes[4]->label(), $block_text);
  }

}
