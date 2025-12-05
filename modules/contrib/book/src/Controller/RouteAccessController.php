<?php

namespace Drupal\book\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for route access.
 */
class RouteAccessController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the route access controller.
   *
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The BookManager service.
   */
  public function __construct(
    protected BookManagerInterface $bookManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('book.manager')
    );
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The book node.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node): AccessResultInterface {
    // Checks if user has permission to reorder pages.
    $hasAccess = $account->hasPermission('reorder book pages');

    // Checks if book has children.
    $haveChildren = $this->checkIfBookHasChildren($node);

    if ($haveChildren) {
      // Checks if number of book page is more than one.
      $ifExceedsCount = $this->checkIfChildIsGreaterThanOne($node);
    }
    else {
      $ifExceedsCount = FALSE;
    }

    return AccessResult::allowedIf($hasAccess && $haveChildren && $ifExceedsCount);
  }

  /**
   * Checks if a book have children.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The book node.
   */
  public function checkIfBookHasChildren(NodeInterface $node): bool {
    return (bool) ($node->book['has_children'] ?? FALSE);
  }

  /**
   * Checks if child of a book is more than one.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The book node.
   */
  public function checkIfChildIsGreaterThanOne(NodeInterface $node): bool {
    $children = $this->bookManager->bookSubtreeData($node->book);
    $child = reset($children);

    return !empty($child['below']) && count($child['below']) > 1;
  }

}
