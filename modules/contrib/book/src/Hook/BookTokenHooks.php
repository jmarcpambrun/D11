<?php

declare(strict_types=1);

namespace Drupal\book\Hook;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Token hook implementations for Book.
 */
class BookTokenHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'book.manager')]
    protected BookManagerInterface $bookManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    return [
      'types' => [
        'node' => [
          'name' => $this->t('Node'),
          'description' => $this->t('Node tokens extended for Book 3.x.'),
        ],
      ],
      'tokens' => [
        'node' => [
          'book:title' => [
            'name' => $this->t('Book title'),
            'description' => $this->t('The title of the book the node belongs to.'),
          ],
          'book:bid' => [
            'name' => $this->t('Book ID'),
            'description' => $this->t('The node ID of the top-level book page.'),
          ],
          'book:depth' => [
            'name' => $this->t('Book depth'),
            'description' => $this->t('The depth of the node in the book hierarchy.'),
          ],
          'book:weight' => [
            'name' => $this->t('Book weight'),
            'description' => $this->t('The weight of the node in the book hierarchy.'),
          ],
          'book:parent' => [
            'name' => $this->t('Book parent title'),
            'description' => $this->t('The title of the parent book page.'),
          ],
          'book:parents:join-path' => [
            'name' => $this->t('Book parents joined as path'),
            'description' => $this->t('Titles of ancestor book pages, joined as a path.'),
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];

    if ($type !== 'node' || empty($data['node']) || !$data['node'] instanceof NodeInterface) {
      return $replacements;
    }

    $node = $data['node'];
    $book_manager = $this->bookManager;
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Load the book link for this node.
    $nid = (int) $node->id();
    $book_link = $book_manager->loadBookLink($nid);

    if (!$book_link) {
      // Not part of a book.
      return $replacements;
    }

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'book:title':
          if (!empty($book_link['bid']) && ($book_node = $node_storage->load($book_link['bid']))) {
            $replacements[$original] = $book_node->label();
            $bubbleable_metadata->addCacheableDependency($book_node);
          }
          break;

        case 'book:bid':
          $replacements[$original] = $book_link['bid'] ?? '';
          break;

        case 'book:depth':
          $replacements[$original] = $book_link['depth'] ?? '';
          break;

        case 'book:weight':
          $replacements[$original] = $book_link['weight'] ?? '';
          break;

        case 'book:parent':
          if (!empty($book_link['pid']) && ($parent_node = $node_storage->load($book_link['pid']))) {
            $replacements[$original] = $parent_node->label();
            $bubbleable_metadata->addCacheableDependency($parent_node);
          }
          break;

        case 'book:parents:join-path':
          $replacements[$original] = $this->buildJoinedParentPath($book_link, $book_manager, $node_storage, $node, $options);
          break;

        default:
          // The token system may pass 'book' as the name with the full chain,
          // for example [node:book:*].
          if ($name === 'book') {
            if (str_contains($original, ':title]')) {
              if (!empty($book_link['bid']) && ($book_node = $node_storage->load($book_link['bid']))) {
                $replacements[$original] = $book_node->label();
                $bubbleable_metadata->addCacheableDependency($book_node);
              }
            }
            elseif (str_contains($original, ':bid]')) {
              $replacements[$original] = $book_link['bid'] ?? '';
            }
            elseif (str_contains($original, ':depth]')) {
              $replacements[$original] = $book_link['depth'] ?? '';
            }
            elseif (str_contains($original, ':weight]')) {
              $replacements[$original] = $book_link['weight'] ?? '';
            }
            elseif (str_contains($original, ':parent]')) {
              if (!empty($book_link['pid']) && ($parent_node = $node_storage->load($book_link['pid']))) {
                $replacements[$original] = $parent_node->label();
                $bubbleable_metadata->addCacheableDependency($parent_node);
              }
            }
            elseif (str_contains($original, ':parents:join-path]')) {
              $replacements[$original] = $this->buildJoinedParentPath($book_link, $book_manager, $node_storage, $node, $options);
            }
          }
          break;
      }
    }

    return $replacements;
  }

  /**
   * Builds the joined parent path for a book link.
   *
   * @param array $book_link
   *   The book link data.
   * @param \Drupal\book\BookManagerInterface $book_manager
   *   The book manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\node\NodeInterface $node
   *   The current node.
   * @param array $options
   *   Token options.
   *
   * @return string
   *   The joined parent path.
   */
  protected function buildJoinedParentPath(array $book_link, BookManagerInterface $book_manager, EntityStorageInterface $node_storage, NodeInterface $node, array $options): string {
    $parent_link = [];
    if (!empty($book_link['pid'])) {
      $parent_link = $book_manager->loadBookLink((int) $book_link['pid']) ?: [];
    }

    $parents = $book_manager->getBookParents($book_link, $parent_link);
    $depth = $parents['depth'] ?? 0;
    $titles = [];

    if ($depth > 0) {
      $current_nid = $node->id();

      for ($i = 1; $i <= $depth; $i++) {
        $key = 'p' . $i;

        if (!empty($parents[$key]) && $parents[$key] != $current_nid) {
          if ($parent = $node_storage->load($parents[$key])) {
            if ($this->moduleHandler->moduleExists('pathauto')) {
              // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
              $titles[] = \Drupal::service('pathauto.alias_cleaner')->cleanString($parent->label(), $options);
            }
            else {
              $titles[] = str_replace(' ', '-', $parent->label());
            }
          }
        }
      }
    }

    return implode('/', $titles);
  }

}
