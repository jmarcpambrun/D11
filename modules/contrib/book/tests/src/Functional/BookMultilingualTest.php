<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests multilingual behavior of books.
 *
 * @group book
 */
class BookMultilingualTest extends BookTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'book',
    'block',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test languages.
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Test child ordering with user with custom "Administration pages language".
   */
  public function testChildOrderingAccess(): void {
    // Create a book.
    $nodes = $this->createBook();

    // Set the interface language to use the preferred administration language
    // and then the URL.
    /** @var \Drupal\language\LanguageNegotiatorInterface $language_negotiator */
    $language_negotiator = $this->container->get('language_negotiator');
    $language_negotiator->saveConfiguration('language_interface', [
      'language-user-admin' => 1,
      'language-url' => 2,
      'language-selected' => 3,
    ]);

    $user = $this->drupalCreateUser([
      'access printer-friendly version',
      'create new books',
      'create book content',
      'edit any book content',
      'delete any book content',
      'add content to books',
      'reorder book pages',
      'add any content to books',
      'administer blocks',
      'administer permissions',
      'administer book outlines',
      'node test view',
      'administer content types',
      'administer site configuration',
      'view any unpublished content',
      'view book revisions',

      'view the administration theme',
      'access administration pages',
    ], NULL, FALSE, [
      // Use a custom user admin language.
      'preferred_admin_langcode' => 'de',
    ]);

    $this->drupalLogin($user);
    $this->drupalGet('node/' . $nodes[0]->id() . '/child-ordering');
    $this->assertSession()->statusCodeEquals(200);
  }

}
