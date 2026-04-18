<?php

namespace Drupal\book\Cache;

use Drupal\book\BookInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\book\BookManagerInterface;

/**
 * Defines the book navigation cache context service.
 *
 * Cache context ID: 'route.book_navigation'.
 *
 * This allows for book navigation location-aware caching. It depends on:
 * - whether the current route represents a book node at all
 * - and if so, where in the book hierarchy we are
 */
class BookNavigationCacheContext implements CacheContextInterface {

  /**
   * Constructs a new BookNavigationCacheContext service.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\book\BookManagerInterface $bookManagerService
   *   The book manager service.
   */
  public function __construct(protected RouteMatchInterface $routeMatch, protected BookManagerInterface $bookManagerService) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string|TranslatableMarkup {
    return t("Book navigation");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    // Find the current book's ID.
    $current_bid = 0;
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof BookInterface) {
      $book = $node->getBook();
      if (!empty($book['bid'])) {
        $current_bid = $book['bid'];
      }
    }

    // If we're not looking at a book node, then we're not navigating a book.
    if ($current_bid === 0) {
      return 'book.none';
    }

    // If we're looking at a book node, get the trail for that node.
    $active_trail = $this->bookManagerService->getActiveTrailIds($book['bid'], $book);
    return implode('|', $active_trail);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    // The book active trail depends on the node and data attached to it.
    // That information is however not stored as part of the node.
    $cacheable_metadata = new CacheableMetadata();
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof BookInterface) {
      $book = $node->getBook();
      // If the node is part of a book then we can use the cache tag for that
      // book. If not, then it can't be optimized away.
      if (!empty($book['bid'])) {
        $cacheable_metadata->addCacheTags(['bid:' . $book['bid']]);
      }
      else {
        $cacheable_metadata->setCacheMaxAge(0);
      }
    }
    return $cacheable_metadata;
  }

}
