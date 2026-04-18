<?php

namespace Drupal\book\Hook;

use Drupal\book\Access\BookNodeOutlineAccessCheck;
use Drupal\book\Access\BookNodePrintAccessCheck;
use Drupal\book\BookHelperTrait;
use Drupal\book\BookManager;
use Drupal\book\BookManagerInterface;
use Drupal\book\BookInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for book.
 */
class BookAlterHooks {

  use BookHelperTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'access_check.book.node_outline')]
    protected readonly BookNodeOutlineAccessCheck $bookOutlineAccessCheck,
    #[Autowire(service: 'access_check.book.node_print')]
    protected readonly BookNodePrintAccessCheck $bookPrintAccessCheck,
    protected readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'book.manager')]
    protected readonly BookManagerInterface $bookManager,
  ) {}

  /**
   * Implements hook_node_links_alter().
   */
  #[Hook('node_links_alter')]
  public function nodeLinkAlter(array &$links, NodeInterface $node, array $context): void {
    if ($node instanceof BookInterface && $context['view_mode'] != 'rss') {
      $book = $node->getBook();
      if (isset($book['depth'])) {
        if ($context['view_mode'] == 'full') {
          $child_type = $this->getBookChildType($this->configFactory->get('book.settings')->get('allowed_types'), $node->bundle());
          $fallback = $node->bundle();
          $child_type = is_null($child_type) ? $fallback : $child_type;
          $access_control_handler = $this->entityTypeManager->getAccessControlHandler('node');
          $book_access = $this->bookOutlineAccessCheck->access($node);
          if ($book_access instanceof AccessResultAllowed && $access_control_handler->createAccess($child_type) && $book['depth'] < BookManager::BOOK_MAX_DEPTH) {
            $book_links['book_add_child'] = [
              'title' => $this->t('Add child page'),
              'url' => Url::fromRoute('node.add', ['node_type' => $child_type], ['query' => ['parent' => $node->id()]]),
            ];
          }
          if (!empty($book['pid']) && $book_access instanceof AccessResultAllowed) {
            $book_links['book_add_sibling'] = [
              'title' => $this->t('Add sibling page'),
              'url' => Url::fromRoute('node.add', ['node_type' => $child_type], ['query' => ['parent' => $book['pid']]]),
            ];
          }
          $print_access = $this->bookPrintAccessCheck->access();
          if ($print_access instanceof AccessResultAllowed) {
            $book_links['book_printer'] = [
              'title' => $this->t('Printer-friendly version'),
              'url' => Url::fromRoute('book.export', [
                'type' => 'html',
                'node' => $node->id(),
              ]),
              'attributes' => ['title' => $this->t('Show a printer-friendly version of this book page and its sub-pages.')],
            ];
          }
        }
      }

      if (!empty($book_links)) {
        $links['book'] = [
          '#theme' => 'links__node__book',
          '#links' => $book_links,
          '#attributes' => ['class' => ['links', 'inline']],
        ];
      }
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for \Drupal\node\NodeForm.
   *
   * Adds the book form element to the node form.
   *
   * @see bookPickBookNojsSubmit()
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(&$form, FormStateInterface $form_state, $form_id): void {
    $node = $form_state->getFormObject()->getEntity();
    $access_return = $this->bookOutlineAccessCheck->access($node);
    if ($access_return instanceof AccessResultForbidden) {
      return;
    }
    $account = $this->currentUser;

    if ($access_return instanceof AccessResultAllowed) {
      $collapsed = !($node->isNew() && !empty($node->getBook()['pid']));
      $form = $this->bookManager->addFormElements($form, $form_state, $node, $account, $collapsed);
      // The "js-hide" class hides submit button when JavaScript is enabled.
      $form['book']['pick-book'] = [
        '#type' => 'submit',
        '#value' => $this->t('Change book (update list of parents)'),
        '#submit' => [[static::class, 'bookPickBookNojsSubmit']],
        '#weight' => 20,
        '#attributes' => [
          'class' => [
            'js-hide',
          ],
        ],
      ];
      $form['#entity_builders'][] = [static::class, 'bookNodeBuilder'];
    }
  }

  /**
   * Form submission handler for node_form().
   *
   * This handler is run when JavaScript is disabled. It triggers the form to
   * rebuild so that the "Parent item" options are changed to reflect the newly
   * selected book. When JavaScript is enabled, the submit button that triggers
   * this handler is hidden, and the "Book" dropdown directly triggers the
   * book_form_update_callback() Ajax callback instead.
   */
  public static function bookPickBookNojsSubmit($form, FormStateInterface $form_state): void {
    $node = $form_state->getFormObject()->getEntity();
    $node->book = $form_state->getValue('book');
    $form_state->setRebuild();
  }

  /**
   * Entity form builder to add the book information to the node.
   */
  public static function bookNodeBuilder($entity_type, NodeInterface $entity, &$form, FormStateInterface $form_state): void {
    $book_values = $form_state->getValue('book');
    if (isset($book_values['create_new_book'])) {
      unset($book_values['create_new_book']);
      if (is_null($book_values['bid'])) {
        $book_values['bid'] = 'new';
      }

      $entity->setBook($book_values);
    }
    else {
      $entity->setBook($form_state->getValue('book'));
    }
    // Always save a revision for non-administrators.
    if (!empty($entity->getBook()['bid']) && !\Drupal::currentUser()->hasPermission('administer nodes')) {
      $entity->setNewRevision();
    }
  }

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function bookMigrationPluginsAlter(array &$migrations): void {
    // Book settings are migrated identically for Drupal 6 and Drupal 7.
    // However, a d6_book_settings migration already existed before the
    // consolidate book_settings migration existed, so to maintain backwards
    // compatibility ensure that d6_book_settings is an alias of book_settings.
    if (isset($migrations['book_settings'])) {
      $migrations['d6_book_settings'] = &$migrations['book_settings'];
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   *
   * Alters the confirm form for a single node deletion.
   */
  #[Hook('form_node_confirm_form_alter')]
  public function formNodeConfirmFormAlter(&$form, FormStateInterface $form_state): void {
    // Only need to alter the delete operation form.
    if ($form_state->getFormObject()->getOperation() !== 'delete') {
      return;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    $access_check = $this->bookOutlineAccessCheck->access($node);
    if ($access_check instanceof AccessResultForbidden) {
      // Not a book node.
      return;
    }

    $book = $node->getBook();
    if (isset($book['has_children'])) {
      $form['book_warning'] = [
        '#markup' => '<p>' . $this->t('%title is part of a book outline, and has associated child pages. If you proceed with deletion, the child pages will be relocated automatically.', ['%title' => $node->label()]) . '</p>',
        '#weight' => -10,
      ];
    }
  }

  /**
   * Implements hook_query_TAG_alter().
   */
  #[Hook('query_books_alter')]
  public function queryTagAlter(AlterableInterface $query): void {
    // Only act on SELECT queries.
    if (!$query instanceof SelectInterface) {
      return;
    }

    // Get the base table alias safely.
    $tables = $query->getTables();
    if (!isset($tables['base_table'])) {
      return;
    }

    // Avoid adding the join twice.
    if (isset($tables['b'])) {
      return;
    }

    $query->addJoin('INNER', 'book', 'b', 'base_table.nid = b.bid');
  }

}
