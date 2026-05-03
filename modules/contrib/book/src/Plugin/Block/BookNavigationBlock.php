<?php

namespace Drupal\book\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\book\BookManagerInterface;
use Drupal\book\BookInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Book navigation' block.
 */
#[Block(
  id: "book_navigation",
  admin_label: new TranslatableMarkup("Book navigation"),
  category: new TranslatableMarkup("Menus"),
)]
class BookNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new BookNavigationBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The book manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $nodeStorage
   *   The node storage.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected BookManagerInterface $bookManager,
    protected EntityStorageInterface $nodeStorage,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('book.manager'),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'block_mode' => "all pages",
      'show_top_item' => FALSE,
      'use_top_level_title' => FALSE,
      'starting_level' => 1,
      'expanded' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $options = [
      'all pages' => $this->t('Show block on all pages'),
      'book pages' => $this->t('Show block only on book pages'),
      'primary book page' => $this->t('Show block only on the top level book page'),
      'child book pages' => $this->t('Show block only on child book pages'),
    ];
    $form['book_block_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Book navigation block display'),
      '#options' => $options,
      '#default_value' => $this->configuration['block_mode'],
      '#description' => $this->t("If <em>Show block on all pages</em> is selected, the block will contain the automatically generated menus for all the site's books. If <em>Show block only on book pages</em> is selected, the block will contain only the one menu corresponding to the current page's book. In this case, if the current page is not in a book, no block will be displayed. The <em>Page specific visibility settings</em> or other visibility settings can be used in addition to selectively display this block."),
    ];
    $form['book_block_mode_book_pages'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          'input[name="settings[book_block_mode]"]' => ['value' => 'book pages'],
        ],
      ],
    ];
    $form['book_block_mode_book_pages']['show_top_item'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show top level item'),
      '#default_value' => $this->configuration['show_top_item'],
      '#description' => $this->t('Enable this option to display the first page in the book with all other pages displayed below it.'),
    ];
    $form['use_top_level_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use top-level page title as block title'),
      '#default_value' => $this->configuration['use_top_level_title'],
      '#states' => [
        'visible' => [':input[name="settings[book_block_mode]"]' => ['value' => 'book pages']],
      ],
    ];
    $form['starting_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Starting level'),
      '#options' => [
        1 => $this->t('Level 1 (top level)'),
        2 => $this->t('Level 2'),
        3 => $this->t('Level 3'),
        4 => $this->t('Level 4'),
        5 => $this->t('Level 5'),
        6 => $this->t('Level 6'),
        7 => $this->t('Level 7'),
        8 => $this->t('Level 8'),
        9 => $this->t('Level 9'),
      ],
      '#default_value' => $this->configuration['starting_level'] ?? 1,
      '#description' => $this->t('The level in the book hierarchy at which to start rendering. Level 1 shows the entire book, level 2 starts with children of the top page, etc.'),
    ];
    $form['max_depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Max depth'),
      '#options' => [
        0 => $this->t('No max'),
        1 => $this->t('Level 1 (top level)'),
        2 => $this->t('Level 2'),
        3 => $this->t('Level 3'),
        4 => $this->t('Level 4'),
        5 => $this->t('Level 5'),
        6 => $this->t('Level 6'),
        7 => $this->t('Level 7'),
        8 => $this->t('Level 8'),
        9 => $this->t('Level 9'),
      ],
      '#default_value' => $this->configuration['max_depth'] ?? 0,
      '#description' => $this->t('The maximum depth of the book that should be rendered.'),
    ];
    $form['expanded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expanded'),
      '#description' => $this->t('If checked, the menu will be expanded by default.'),
      '#default_value' => $this->configuration['expanded'],
    ];

    $books = $this->bookManager->getAllBooks();
    $book_titles = array_column($books, 'title');
    $book_ids = array_column($books, 'bid');
    $book_options = [0 => '- None -'] + array_combine($book_ids, $book_titles);
    array_unshift($book_titles, '- None -');
    $form['book_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select book menu (optional)'),
      '#description' => $this->t('Select the book to display the contents of. Make a selection here if you want to use this block to display the contents of a specific book.'),
      '#default_value' => $this->configuration['book_select'] ?? 0,
      '#options' => $book_options,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['block_mode'] = $form_state->getValue('book_block_mode');
    $this->configuration['show_top_item'] = $form_state->getValue('book_block_mode_book_pages')['show_top_item'];
    $this->configuration['use_top_level_title'] = $form_state->getValue('use_top_level_title');
    $this->configuration['starting_level'] = $form_state->getValue('starting_level');
    $this->configuration['expanded'] = $form_state->getValue('expanded');
    $this->configuration['book_select'] = $form_state->getValue('book_select');
    $this->configuration['max_depth'] = $form_state->getValue('max_depth');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function build(): array {
    $current_bid = 0;

    if (!empty($this->configuration['book_select'])) {
      $selected_book = $this->configuration['book_select'];
    }

    if (!empty($this->configuration['max_depth'])) {
      $max_depth = $this->configuration['max_depth'];
    }

    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof BookInterface) {
      $book = $node->getBook();
      if (!empty($book['bid'])) {
        $current_bid = $book['bid'];
      }
    }

    if ($this->configuration['block_mode'] == 'all pages') {
      $book_menus = [];
      $pseudo_tree = [0 => ['below' => FALSE]];
      $books = $this->bookManager->getAllBooks();
      foreach ($books as $book_id => $book) {
        if ($book['bid'] == $current_bid) {
          // If the current page is a node associated with a book, the menu
          // needs to be retrieved.
          $data = $this->bookManager->bookTreeAllData($selected_book ?? $book['bid'], $book, $max_depth ?? NULL, $this->configuration['starting_level'], $this->configuration['expanded']);
          $book_menus[$book_id] = $this->bookManager->bookTreeOutput($data);
        }
        else {
          // Since we know we will only display a link to the top node, there
          // is no reason to run an additional menu tree query for each book.
          $book['in_active_trail'] = FALSE;
          // Check whether user can access the book link.
          $book_node = $this->nodeStorage->load($book['nid']);
          $book['access'] = $book_node->access('view');
          $pseudo_tree[0]['link'] = $book;
          $book_menus[$book_id] = $this->bookManager->bookTreeOutput($pseudo_tree);
        }
        $book_menus[$book_id] += [
          '#book_title' => $book['title'],
        ];
      }
      if ($book_menus) {
        return [
          '#theme' => 'book_all_books_block',
        ] + $book_menus;
      }
    }
    elseif ($current_bid) {

      // Only display this block when the user is browsing a book
      // and included unpublished books if the user has access.
      $query = $this->nodeStorage->getQuery()
        ->accessCheck()
        ->condition('nid', $book['bid'], '=');
      $nid = $query->execute();

      // Only show the block if the user has view access for the top-level node.
      if ($nid) {
        $node = $this->routeMatch->getParameter('node');
        $current_nid = $node->id();
        $tree = $this->bookManager->bookTreeAllData($selected_book ?? $book['bid'], $book, $max_depth ?? NULL, $this->configuration['starting_level'], $this->configuration['expanded']);
        $data = reset($tree);

        // Handle different display modes.
        if ($this->configuration['block_mode'] == 'primary book page') {
          $primary_book_nid = array_pop($nid);
          if ($current_nid !== $primary_book_nid) {
            return [];
          }
        }
        elseif ($this->configuration['block_mode'] == 'child book pages') {
          $primary_book_nid = array_pop($nid);
          if ($current_nid === $primary_book_nid) {
            return [];
          }
        }

        // Prepare the output based on settings.
        $starting_level = $this->configuration['starting_level'];
        $current_depth = $book['depth'];

        // When starting_level > 1, and we're on a page at or below that level,
        // show only the current page's subtree, not siblings.
        if ($starting_level > 1 && $current_depth >= $starting_level) {
          // Find the current page in the tree and show its children.
          $current_subtree = $this->findNodeInTree($tree, $current_nid);
          if ($current_subtree && !empty($current_subtree['below'])) {
            $output = $this->bookManager->bookTreeOutput($current_subtree['below']);
          }
          else {
            return [];
          }
        }
        elseif ($this->configuration['show_top_item']) {
          $output = $this->bookManager->bookTreeOutput($tree);
        }
        else {
          if (!empty($data['below'])) {
            $output = $this->bookManager->bookTreeOutput($data['below']);
          }
          else {
            return [];
          }
        }

        // Apply the top-level title if configured.
        if ($this->configuration['use_top_level_title'] && isset($data['link']['title'])) {
          $output['#title'] = $data['link']['title'];
        }
        return $output;
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // ::build() varies by the "book navigation" cache context.
    // Additional cache contexts, e.g. those that determine link text or
    // accessibility of a menu, will be bubbled automatically.
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'route.book_navigation',
      'user.node_grants:view',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'node_list',
      'book_settings',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    if ($this->configuration['block_mode'] != 'all pages') {
      $node = $this->routeMatch->getParameter('node');
      if (!($node instanceof BookInterface) || empty($node->getBook()['bid'])) {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::allowed();
  }

  /**
   * Finds a node in the book tree by its ID.
   *
   * @param array $tree
   *   The book tree structure.
   * @param int|string $nid
   *   The node ID to find.
   *
   * @return array|null
   *   The tree entry for the node, or NULL if not found.
   */
  protected function findNodeInTree(array $tree, int|string $nid): ?array {
    foreach ($tree as $item) {
      if (isset($item['link']['nid']) && $item['link']['nid'] == $nid) {
        return $item;
      }
      if (!empty($item['below'])) {
        $found = $this->findNodeInTree($item['below'], $nid);
        if ($found !== NULL) {
          return $found;
        }
      }
    }
    return NULL;
  }

}
