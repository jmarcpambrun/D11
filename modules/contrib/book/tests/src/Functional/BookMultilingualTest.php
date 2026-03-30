<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests multilingual behavior of books.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookMultilingualTest extends BookTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'book',
    'book_content_type',
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

    // Use a custom user admin language.
    $this->adminUser
      ->set('preferred_admin_langcode', 'de')
      ->save();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/' . $nodes[0]->id() . '/child-ordering');
    $this->assertSession()->statusCodeEquals(200);
  }

}
