<?php

namespace Drupal\book\Hook;

use Drupal\book\Access\BookNodeOutlineAccessCheck;
use Drupal\book\BookHelperTrait;
use Drupal\book\BookManagerInterface;
use Drupal\book\BookInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for book.
 */
class BookHooks {

  use BookHelperTrait;
  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountProxyInterface $currentUser,
    #[Autowire(service: 'access_check.book.node_outline')]
    protected BookNodeOutlineAccessCheck $bookNodeOutlineAccessCheck,
    #[Autowire(service: 'book.manager')]
    protected BookManagerInterface $bookManager,
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): string {
    switch ($route_name) {
      case 'help.page.book':
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Book module is used for creating structured, multipage content, such as site resource guides, manuals, and wikis. It allows you to create content that has chapters, sections, subsections, or any similarly-tiered structure. Installing the module creates a new content type <em>Book page</em>. For more information, see the <a href=":book">online documentation for the Book module</a>.', [':book' => 'https://www.drupal.org/documentation/modules/book']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Adding and managing book content') . '</dt>';
        $output .= '<dd>' . $this->t('Books have a hierarchical structure, called a <em>book outline</em>. Each book outline can have nested pages up to nine levels deep. Multiple content types can be configured to behave as a book outline. From the content edit form, it is possible to add a page to a book outline or create a new book.') . '</dd>';
        $output .= '<dd>' . $this->t('You can assign separate permissions for <em>creating new books</em> as well as <em>creating</em>, <em>editing</em> and <em>deleting</em> book content. Users with the <em>Add content and child pages to books and manage their hierarchies</em> permission can add book content to a book by selecting the appropriate book outline while editing the content. Users with the <em>Add non-book content to outlines</em> permission can add <em>any</em> type of content to a book. Users with the <em>Administer book outlines</em> permission can view a list of all books, and edit and rearrange section titles on the <a href=":admin-book">Book list page</a>.', [
          ':admin-book' => Url::fromRoute('book.admin')
            ->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring content types for books') . '</dt>';
        $output .= '<dd>' . $this->t('The <em>Book page</em> content type is the initial content type installed for book outlines. On the <a href=":admin-settings">Book settings page</a> you can configure content types that can used in book outlines.', [
          ':admin-settings' => Url::fromRoute('book.settings')->toString(),
        ]) . '</dd>';
        $output .= '<dd>' . $this->t('Users with the <em>Add content and child pages to books</em> permission will see a link to <em>Add child page</em> when viewing a content item that is part of a book outline. This link will allow users to create a new content item of the content type you select on the <a href=":admin-settings">Book settings page</a>. By default, this is the <em>Book page</em> content type.', [
          ':admin-settings' => Url::fromRoute('book.settings')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Book navigation') . '</dt>';
        $output .= '<dd>' . $this->t("Book pages have a default book-specific navigation block. This navigation block contains links that lead to the previous and next pages in the book, and to the level above the current page in the book's structure. This block can be enabled on the <a href=':admin-block'>Blocks layout page</a>. For book pages to show up in the book navigation, they must be added to a book outline.", [
          ':admin-block' => ($this->moduleHandler->moduleExists('block')) ? Url::fromRoute('block.admin_display')->toString() : '#',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Collaboration') . '</dt>';
        $output .= '<dd>' . $this->t('Books can be created collaboratively, as they allow users with appropriate permissions to add pages into existing books, and add those pages to a custom table of contents.') . '</dd>';
        $output .= '<dt>' . $this->t('Printing books') . '</dt>';
        $output .= '<dd>' . $this->t("Users with the <em>View printer-friendly books</em> permission can select the <em>printer-friendly version</em> link visible at the bottom of a book page's content to generate a printer-friendly display of the page and all of its subsections.") . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'book.admin':
        return '<p>' . $this->t('The book module offers a means to organize a collection of related content pages, collectively known as a book. When viewed, this content automatically displays links to adjacent book pages, providing a simple navigation system for creating and reviewing structured content.') . '</p>';

      case 'entity.node.book_outline_form':
        return '<p>' . $this->t('The outline feature allows you to include pages in the <a href=":book">Book hierarchy</a>, as well as move them within the hierarchy or to <a href=":book-admin">reorder an entire book</a>.', [
          ':book' => Url::fromRoute('book.render')->toString(),
          ':book-admin' => Url::fromRoute('book.admin')->toString(),
        ]) . '</p>';
    }
    return '';
  }

  /**
   * Implements hook_ENTITY_TYPE_load().
   */
  #[Hook('node_load')]
  public function nodeLoad($nodes): void {
    // Filter only nodes that can be included in books.
    $valid_nids = [];
    $access_check = $this->bookNodeOutlineAccessCheck;
    foreach ($nodes as $key => $node) {
      if ($node instanceof BookInterface && $access_check->access($node)) {
        $valid_nids[] = $key;
      }
    }

    if (!empty($valid_nids)) {
      $book_manager = $this->bookManager;
      $links = $book_manager->loadBookLinks($valid_nids, FALSE);
      foreach ($links as $record) {
        $book_new = $record;
        $book_new['link_path'] = 'node/' . $record['nid'];
        $book_new['link_title'] = $nodes[$record['nid']]->label();
        $nodes[$record['nid']]->setBook($book_new);
      }
    }
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array $entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['node']
      ->setFormClass('book_outline', 'Drupal\book\Form\BookOutlineForm')
      ->setLinkTemplate('book-outline-form', '/node/{node}/outline')
      ->setLinkTemplate('book-remove-form', '/node/{node}/outline/remove')
      ->addConstraint('BookOutline', []);
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   *
   * Sets the Book entity class for book-allowed bundles that don't have a
   * custom bundle class defined. This approach allows other modules to define
   * their own bundle classes extending Node without conflicts.
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    $allowed_types = $this->configFactory->get('book.settings')->get('allowed_types') ?? [];
    foreach ($allowed_types as $type_config) {
      $type = $type_config['content_type'] ?? NULL;
      // Only set the Book class if no custom bundle class is defined.
      if ($type && isset($bundles['node'][$type]) && !isset($bundles['node'][$type]['class'])) {
        $bundles['node'][$type]['class'] = 'Drupal\book\Entity\Node\Book';
      }
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $fields = [];
    $allowed_types_config = $this->configFactory->get('book.settings')->get('allowed_types');
    $allowed_types = $this->getBookContentTypes($allowed_types_config);
    if ($allowed_types) {
      foreach ($allowed_types as $node_type) {
        $fields['node'][$node_type]['display']['book_navigation'] = [
          'label' => $this->t('Book navigation'),
          'description' => $this->t('Book navigation links'),
          'weight' => 100,
          'visible' => FALSE,
        ];
        $fields['node'][$node_type]['display']['book_navigation_without_tree'] = [
          'label' => $this->t('Book navigation (without tree links)'),
          'description' => $this->t('Book navigation links (without tree links)'),
          'weight' => 100,
          'visible' => FALSE,
        ];
      }
    }
    return $fields;
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $node, EntityViewDisplayInterface $display, $view_mode): void {
    $field_key = NULL;
    foreach (['book_navigation', 'book_navigation_without_tree'] as $key) {
      if ($display->getComponent($key)) {
        $field_key = $key;
        break;
      }
    }
    if ($field_key) {
      $book = $node->getBook();

      // Not all data is available in preview mode, load it here if needed.
      if (empty($book['p1']) || empty($book['link_path'])) {
        $book = $this->bookManager->loadBookLink((int) $node->id());
      }

      if (!empty($book['bid']) && !$node->isNew()) {
        $book_node = Node::load($book['bid']);
        if (!$book_node?->access()) {
          return;
        }
        $build[$field_key] = [
          '#theme' => 'book_navigation',
          '#book_link' => $book,
          '#show_tree' => !$display->getComponent('book_navigation_without_tree'),
          // The book navigation is a listing of Node entities, so associate its
          // list cache tag for correct invalidation.
          '#cache' => [
            'tags' => $node->getEntityType()->getListCacheTags(),
          ],
        ];
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_prepare_form().
   */
  #[Hook('node_prepare_form')]
  public function nodePrepareForm(NodeInterface $node, $operation, FormStateInterface $form_state): void {
    $access_check = $this->bookNodeOutlineAccessCheck->access($node);
    if ($access_check instanceof AccessResultForbidden) {
      return;
    }

    $book_manager = $this->bookManager;

    // Avoid layout builder form as it won't have the book widget on it.
    // If we set defaults on this form, it will interfere with the
    // BookOutlineConstraintValidator and prevent saving layouts on nodes using
    // content moderation and book.
    if ($operation === 'layout_builder') {
      return;
    }

    // Prepare defaults for the add/edit form.
    $book_access = $this->bookNodeOutlineAccessCheck->access($node);
    $book = $node->getBook();
    if (empty($book) && $book_access instanceof AccessResultAllowed) {
      $book_new = [];
      $query = $this->requestStack->getCurrentRequest()->query;
      if ($node->isNew() && !is_null($query->get('parent')) && is_numeric($query->get('parent'))) {
        // Handle "Add child page" and "Add sibling page" links:
        $parent = $book_manager->loadBookLink($query->get('parent'));

        if ($parent && $parent['access']) {
          // @todo check new config.
          $book_new['create_new_book'] = TRUE;
          $book_new['parent_parameter'] = $query->get('parent');
          $book_new['bid'] = $parent['bid'];
          $book_new['pid'] = $parent['nid'];
          // Copy the parent's materialized path so the parent selector
          // JS library can auto-populate the active trail.
          for ($i = 1; $i <= 9; $i++) {
            if (!empty($parent["p$i"])) {
              $book_new["p$i"] = $parent["p$i"];
            }
          }
        }
      }
      // Set defaults.
      $node_ref = !$node->isNew() ? $node->id() : 'new';
      $book_new += $book_manager->getLinkDefaults($node_ref);
      $node->setBook($book_new);
    }
    else {
      if (isset($book['bid']) && !isset($book['original_bid'])) {
        $node->setBookKey('original_bid', $book['bid']);
      }
    }
    // Find the depth limit for the parent select.
    if (isset($book['bid']) && !isset($book['parent_depth_limit'])) {
      $node->setBookKey('parent_depth_limit', $book_manager->getParentDepthLimit($book));
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('node_presave')]
  public function nodePresave(EntityInterface $node): void {
    // Make sure a new node gets a new menu link.
    if ($node->isNew() && $node instanceof BookInterface) {
      $node->setBookKey('nid', NULL);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $node): void {
    if ($node instanceof BookInterface) {
      $book_manager = $this->bookManager;
      $book_manager->updateOutline($node);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('node_update')]
  public function nodeUpdate(EntityInterface $node): void {
    if ($node instanceof BookInterface) {
      $book_manager = $this->bookManager;
      $book_manager->updateOutline($node);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete().
   */
  #[Hook('node_predelete')]
  public function nodePredelete(EntityInterface $node): void {
    if ($node instanceof BookInterface) {
      $book = $node->getBook();
      if (!empty($book['bid'])) {
        $book_manager = $this->bookManager;
        $book_manager->deleteFromBook($book['nid']);
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node_type entities.
   *
   * Updates book settings configuration object if the machine-readable name of
   * a node type is changed.
   */
  #[Hook('node_type_update')]
  public function nodeTypeUpdate(NodeTypeInterface $type): void {
    if ($type->getOriginalId() != $type->id()) {
      $config = $this->configFactory->getEditable('book.settings');
      // Update the list of node types that are allowed to be added to books.
      $allowed_types = $this->getBookContentTypes($config->get('allowed_types'));
      $old_key = array_search($type->getOriginalId(), $allowed_types);

      if ($old_key !== FALSE) {
        $allowed_types[$old_key] = $type->id();
        // Ensure that the allowed_types array is sorted consistently.
        // @see BookSettingsForm::submitForm()
        sort($allowed_types);
        $config->set('allowed_types', $allowed_types);
      }

      // Update the setting for the "Add child page" link.
      if ($config->get('child_type') == $type->getOriginalId()) {
        $config->set('child_type', $type->id());
      }
      $config->save();
    }
  }

}
