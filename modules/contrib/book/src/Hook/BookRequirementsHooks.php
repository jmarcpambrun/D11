<?php

namespace Drupal\book\Hook;

use Drupal\book\Entity\Node\Book;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\BundleClassInheritanceException;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for book.
 */
class BookRequirementsHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_requirements().
   *
   * Check that node extends Book's entity class.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\Exception\BundleClassInheritanceException
   */
  #[Hook('runtime_requirements')]
  public function runTimeRequirements(): void {
    $node_entity_class = $this->entityTypeManager
      ->getStorage('node')
      ->getEntityClass('book');
    $book_class = Book::class;
    if (!is_subclass_of($node_entity_class, $book_class) && $book_class !== $node_entity_class) {
      throw new BundleClassInheritanceException($node_entity_class, $book_class);
    }
  }

}
