<?php

namespace Drupal\book\Hook;

use Drupal\book\BookManagerInterface;
use Drupal\book\BookOutline;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for book.
 */
class BookThemeHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'book.outline')]
    protected BookOutline $bookOutline,
    #[Autowire(service: 'book.manager')]
    protected BookManagerInterface $bookManager,
    protected LanguageManagerInterface $languageManager,
    #[Autowire(service: 'renderer')]
    protected Renderer $renderer,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() : array {
    return [
      'book_navigation' => [
        'variables' => [
          'book_link' => NULL,
          'show_tree' => TRUE,
        ],
        'initial preprocess' => static::class . ':preprocessBookNavigation',
      ],
      'book_tree' => [
        'variables' => ['items' => [], 'attributes' => []],
      ],
      'book_export_html' => [
        'variables' => [
          'title' => NULL,
          'book_title' => NULL,
          'contents' => NULL,
          'depth' => NULL,
        ],
        'initial preprocess' => static::class . ':preprocessBookExportHtml',
      ],
      'book_all_books_block' => [
        'render element' => 'book_menus',
        'initial preprocess' => static::class . ':preprocessBookAllBooksBlock',
      ],
      'book_node_export_html' => [
        'variables' => ['node' => NULL, 'content' => NULL, 'children' => NULL],
        'initial preprocess' => static::class . ':preprocessBookNodeExportHtml',
      ],
    ];
  }

  /**
   * Prepares variables for book listing block templates.
   *
   * Default template: book-all-books-block.html.twig.
   *
   * All non-renderable elements are removed so that the template has full
   * access to the structured data but can also simply iterate over all
   * elements and render them (as in the default template).
   *
   * @param array $variables
   *   An associative array containing the following key:
   *   - book_menus: An associative array containing renderable menu links for
   *     all book menus.
   */
  public function preprocessBookAllBooksBlock(array &$variables): void {
    // Remove all non-renderable elements.
    $elements = $variables['book_menus'];
    $variables['book_menus'] = [];
    foreach (Element::children($elements) as $index) {
      $variables['book_menus'][] = [
        'id' => $index,
        'menu' => $elements[$index],
        'title' => $elements[$index]['#book_title'],
      ];
    }
  }

  /**
   * Prepares variables for book navigation templates.
   *
   * Default template: book-navigation.html.twig.
   *
   * @param array $variables
   *   An associative array containing the following key:
   *   - book_link: An associative array of book link properties.
   *     Properties used: bid, link_title, depth, pid, nid.
   *
   * @throws \Exception
   */
  public function preprocessBookNavigation(array &$variables): void {
    $book_link = $variables['book_link'];

    // Load the top item in the book so we can get the title.
    $book_manager = $this->bookManager;
    $book_top = $book_manager->loadBookLink($book_link['bid']);

    // Provide extra variables for themers. Not needed by default.
    $variables['book_id'] = $book_link['bid'];
    $variables['book_title'] = $book_top['title'] ?? '';
    $variables['book_url'] = Url::fromRoute('entity.node.canonical', ['node' => $book_link['bid']])->toString();
    $variables['current_depth'] = $book_link['depth'] ?? 0;
    $variables['tree'] = '';

    $book_outline = $this->bookOutline;

    if ($book_link['nid']) {
      $variables['tree'] = $book_outline->childrenLinks($book_link);

      $build = [];

      if ($prev = $book_outline->prevLink($book_link)) {
        $prev_href = Url::fromRoute('entity.node.canonical', ['node' => $prev['nid']])->toString();
        $build['#attached']['html_head_link'][][] = [
          'rel' => 'prev',
          'href' => $prev_href,
        ];
        $variables['prev_url'] = $prev_href;
        $variables['prev_title'] = $prev['title'];
      }

      if ($book_link['pid'] && $parent = $book_manager->loadBookLink($book_link['pid'])) {
        $parent_href = Url::fromRoute('entity.node.canonical', ['node' => $book_link['pid']])->toString();
        $build['#attached']['html_head_link'][][] = [
          'rel' => 'up',
          'href' => $parent_href,
        ];
        $variables['parent_url'] = $parent_href;
        $variables['parent_title'] = $parent['title'];
      }

      if ($next = $book_outline->nextLink($book_link)) {
        $next_href = Url::fromRoute('entity.node.canonical', ['node' => $next['nid']])->toString();
        $build['#attached']['html_head_link'][][] = [
          'rel' => 'next',
          'href' => $next_href,
        ];
        $variables['next_url'] = $next_href;
        $variables['next_title'] = $next['title'];
      }
    }

    if (!empty($build)) {
      $this->renderer->render($build);
    }

    $variables['has_links'] = FALSE;
    // Link variables to filter for values and set state of the flag variable.
    $links = ['prev_url', 'prev_title', 'parent_url', 'parent_title', 'next_url', 'next_title'];
    foreach ($links as $link) {
      if (isset($variables[$link])) {
        // Flag when there is a value.
        $variables['has_links'] = TRUE;
      }
      else {
        // Set empty to prevent notices.
        $variables[$link] = '';
      }
    }
  }

  /**
   * Prepares variables for book export templates.
   *
   * Default template: book-export-html.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - title: The title of the book.
   *   - contents: Output of each book page.
   *   - depth: The max depth of the book.
   */
  public function preprocessBookExportHtml(array &$variables): void {
    global $base_url;
    $language_interface = $this->languageManager->getCurrentLanguage();

    $variables['base_url'] = $base_url;
    $variables['language'] = $language_interface;
    $variables['language_rtl'] = ($language_interface->getDirection() == LanguageInterface::DIRECTION_RTL);

    // HTML element attributes.
    $attributes = [];
    $attributes['lang'] = $language_interface->getId();
    $attributes['dir'] = $language_interface->getDirection();
    $variables['html_attributes'] = new Attribute($attributes);
  }

  /**
   * Prepares variables for single node export templates.
   *
   * Default template: book-node-export-html.html.twig.
   *
   * By default, this function performs special preprocessing of the title
   * field, so it is available as a variable in the template. This
   * preprocessing is skipped if:
   * - a module makes the field's display configurable via the field UI by means
   *   of BaseFieldDefinition::setDisplayConfigurable()
   * - AND the additional entity type property
   *   'enable_base_field_custom_preprocess_skipping' has been set using
   *   hook_entity_type_build().
   *
   * @param array $variables
   *   An associative array containing the following keys:
   *   - node: The node that will be output.
   *   - children: All the rendered child nodes within the current node.
   *     Defaults to an empty string.
   */
  public function preprocessBookNodeExportHtml(array &$variables): void {
    $node = $variables['node'];
    $variables['depth'] = $node->getBook()['depth'];

    $skip_custom_preprocessing = $node->getEntityType()->get('enable_base_field_custom_preprocess_skipping');
    if (!$skip_custom_preprocessing || !$node->getFieldDefinition('title')->isDisplayConfigurable('view')) {
      $variables['title'] = $node->label();
    }
  }

}
