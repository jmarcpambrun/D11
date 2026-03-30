<?php

declare(strict_types=1);

namespace Drupal\Tests\book\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Base class for javascript tests.
 */
#[Group('book')]
abstract class BookJavascriptBaseTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['book', 'book_content_type'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('book.settings');
    $settings = [];
    $settings[] = [
      'content_type' => 'book',
      'child_type' => 'book',
    ];
    $config->set('allowed_types', $settings)->save();
  }

}
