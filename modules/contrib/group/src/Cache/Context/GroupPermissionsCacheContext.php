<?php

namespace Drupal\group\Cache\Context;

use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Access\GroupPermissionsHashGeneratorInterface;

/**
 * Defines a cache context for "per group membership permissions" caching.
 *
 * Please read the following guide on how to best use this context:
 * https://www.drupal.org/docs/8/modules/group/turning-off-caching-when-it-doesnt-make-sense.
 *
 * Cache context ID: 'user.group_permissions'.
 */
class GroupPermissionsCacheContext implements CacheContextInterface {

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected GroupPermissionsHashGeneratorInterface $permissionsHashGenerator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Group permissions");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->permissionsHashGenerator->generateHash($this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return $this->permissionsHashGenerator->getCacheableMetadata($this->currentUser);
  }

}
