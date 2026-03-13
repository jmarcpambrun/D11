<?php

namespace Drupal\book\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\book\BookHelperTrait;
use Drupal\node\NodeInterface;

/**
 * Determines if a node's outline settings can be accessed.
 */
class BookNodeOutlineAccessCheck implements AccessInterface {

  use BookHelperTrait;

  /**
   * Constructs a BookNodeOutlineAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   An immutable configuration object.
   */
  public function __construct(protected AccountInterface $currentUser, protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * Checks if user has permission to access a node's book settings.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node requested to be removed from its book.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    // If content type is allowed book type, then check for 'add content to
    // books' permission.
    $allowed_types_config = $this->configFactory->get('book.settings')->get('allowed_types') ?? [];
    $allowed_types = $this->getBookContentTypes($allowed_types_config);
    if (in_array($node->getType(), $allowed_types, TRUE)) {
      return AccessResult::allowedIfHasPermission($this->currentUser, 'add content to books')
        ->orif(AccessResult::allowedIfHasPermission($this->currentUser, 'administer book outlines'));
    }
    return AccessResult::forbidden();
  }

}
