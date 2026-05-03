<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the fields from entity_extra_field_info().
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookEntityExtraTest extends BookTestBase {

  use BookTestTrait;

  /**
   * Tests the book extra fields.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testBookExtraFields(): void {
    $nodes = $this->createBook();

    $this->drupalLogin($this->webUser);
    $display_repository = \Drupal::service('entity_display.repository');
    $form_display = $display_repository->getViewDisplay('node', 'book');
    $form_display->removeComponent('book_navigation')->save();

    $this->drupalGet($nodes[0]->toUrl()->toString());
    $session = $this->assertSession();

    $session->linkNotExists('Up');
    $session->linkNotExists($nodes[1]->label());

    $form_display->setComponent('book_navigation')->save();

    $this->drupalGet($nodes[0]->toUrl()->toString());
    $session->linkExists('Up');
    $session->linkExists($nodes[1]->label());
    $session->elementExists('css', "ul.menu-tree");
    // Book traversal links are after the "Printer-friendly version" link.
    $session->elementExists('xpath', '//nav[contains(@aria-label, "Book traversal links")]/preceding-sibling::ul[@class = "links inline"]');

    $form_display->removeComponent('book_navigation')->save();
    $form_display->setComponent('book_navigation_without_tree')->save();

    $this->drupalGet($nodes[0]->toUrl()->toString());
    $session->linkExists('Up');
    $session->linkExists($nodes[1]->label());
    $session->elementNotExists('css', "ul.menu-tree");
    // Book traversal links are after the "Printer-friendly version" link.
    $session->elementExists('xpath', '//nav[contains(@aria-label, "Book traversal links")]/preceding-sibling::ul[@class = "links inline"]');
  }

}
