<?php

namespace Drupal\Tests\book\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\block\Functional\AssertBlockAppearsTrait;
use Drupal\user\UserInterface;

/**
 * Base class for testing Book functionality.
 */
abstract class BookTestBase extends BrowserTestBase {

  use AssertBlockAppearsTrait;
  use BookTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'content_moderation',
    'book',
    'book_content_type',
    'block',
    'node_access_test',
    'book_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to view a book and access printer-friendly version.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $webUser;

  /**
   * A user with permission to create and edit books and to administer blocks.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * A user without the 'node test view' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $webUserWithoutNodeAccess;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setBookSettings();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('page_title_block');
    $this->drupalPlaceBlock('local_tasks_block');

    // node_access_test requires a node_access_rebuild().
    node_access_rebuild();

    // Create users.
    $this->bookAuthor = $this->drupalCreateUser([
      'create new books',
      'create book content',
      'edit own book content',
      'add content to books',
      'view own unpublished content',
      'access book list',
    ]);
    $this->webUser = $this->drupalCreateUser([
      'access printer-friendly version',
      'node test view',
    ]);
    $this->webUserWithoutNodeAccess = $this->drupalCreateUser([
      'access printer-friendly version',
    ]);
    $this->adminUser = $this->drupalCreateUser([
      'access printer-friendly version',
      'access book list',
      'delete book',
      'create new books',
      'create book content',
      'edit any book content',
      'delete any book content',
      'add content to books',
      'reorder book pages',
      'administer blocks',
      'administer permissions',
      'administer book outlines',
      'node test view',
      'administer content types',
      'administer book settings',
      'view any unpublished content',
      'view book revisions',
    ]);
  }

}
