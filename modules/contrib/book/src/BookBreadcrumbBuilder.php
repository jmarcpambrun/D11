<?php

namespace Drupal\book;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a breadcrumb builder for nodes in a book.
 */
class BookBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $account,
    protected EntityRepositoryInterface $entityRepository,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL): bool {
    // @todo Remove null safe operator after Drupal 12.0.0 becomes the minimum
    //   requirement, see https://www.drupal.org/project/drupal/issues/3459277.
    $cacheable_metadata?->addCacheContexts(['route.book_navigation']);
    $node = $route_match->getParameter('node');
    return $node instanceof BookInterface && !empty($node->getBook());
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $book_nids = [];
    $breadcrumb = new Breadcrumb();

    $links = [Link::createFromRoute($this->t('Home'), '<front>', [], [
      'language' => $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT),
    ]),
    ];
    $breadcrumb->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);
    $book = $route_match->getParameter('node')->getBook();
    $depth = 1;
    // We skip the current node.
    while (!empty($book['p' . ($depth + 1)])) {
      $book_nids[] = $book['p' . $depth];
      $depth++;
    }
    /** @var \Drupal\node\NodeInterface[] $parent_books */
    $parent_books = $this->entityTypeManager->getStorage('node')->loadMultiple($book_nids);
    $parent_books = array_map([$this->entityRepository, 'getTranslationFromContext'], $parent_books);
    if (count($parent_books) > 0) {
      $depth = 1;
      while (!empty($book['p' . ($depth + 1)])) {
        if (!empty($parent_books[$book['p' . $depth]]) && ($parent_book = $parent_books[$book['p' . $depth]])) {
          $access = $parent_book->access('view', $this->account, TRUE);
          $breadcrumb->addCacheableDependency($access);
          if ($access->isAllowed()) {
            $breadcrumb->addCacheableDependency($parent_book);
            $links[] = $parent_book->toLink();
          }
        }
        $depth++;
      }
    }
    $breadcrumb->setLinks($links);
    // @todo Remove after Drupal 12.0.0 becomes the minimum requirement,
    //   see https://www.drupal.org/project/drupal/issues/3459277.
    $breadcrumb->addCacheContexts(['route.book_navigation']);
    return $breadcrumb;
  }

}
