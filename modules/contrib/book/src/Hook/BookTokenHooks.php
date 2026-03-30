<?php

declare(strict_types=1);

namespace Drupal\book\Hook;

use Drupal\book\BookManagerInterface;
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

    // 1. Load the book link for THIS node.
    $nid = (int) $node->id();
    $book_link = $book_manager->loadBookLink($nid);

    if (!$book_link) {
      // Not part of a book.
      return $replacements;
    }

    // 2. Load the parent link if pid > 0.
    $parent_link = [];
    if (!empty($book_link['pid'])) {
      $parent_link = $book_manager->loadBookLink((int) $book_link['pid']) ?: [];
    }

    // 3. Get parent chain array.
    $parents = $book_manager->getBookParents($book_link, $parent_link);
    // Array looks like: ['depth'=>X, 'p1'=>nid, 'p2'=>nid, ...].
    $depth = $parents['depth'] ?? 0;

    // 4. Build clean path segments from ancestor titles.
    $titles = [];
    if ($depth > 0) {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $current_nid = $node->id();

      for ($i = 1; $i <= $depth; $i++) {
        $key = 'p' . $i;

        if (!empty($parents[$key]) && $parents[$key] != $current_nid) {
          if ($parent = $node_storage->load($parents[$key])) {
            // Make an array of node labels. If pathauto is installed, clean it.
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

    // The final joined path.
    $joined_path = implode('/', $titles);

    foreach ($tokens as $name => $original) {
      // Token splits [node:book:parents:join-path] and passes only the first
      // segment ('book') as the token name for type 'node'. The full token is
      // still available in $original, so detect the desired chain there.
      if ($name === 'book' && str_contains($original, ':parents:join-path]')) {
        $replacements[$original] = $joined_path;
        continue;
      }

      // Defensive fallback in case the token system ever passes the full name.
      if ($name === 'book:parents:join-path') {
        $replacements[$original] = $joined_path;
      }
    }

    return $replacements;
  }

}
