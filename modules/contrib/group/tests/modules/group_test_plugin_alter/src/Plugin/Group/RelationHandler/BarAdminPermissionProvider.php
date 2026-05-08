<?php

namespace Drupal\group_test_plugin_alter\Plugin\Group\RelationHandler;

use Drupal\group\Plugin\Group\RelationHandler\PermissionProviderInterface;
use Drupal\group\Plugin\Group\RelationHandler\PermissionProviderTrait;

/**
 * Alters admin permission for a specific plugin to original + 'bar'.
 */
class BarAdminPermissionProvider implements PermissionProviderInterface {

  use PermissionProviderTrait;

  public function __construct(PermissionProviderInterface $parent) {
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminPermission() {
    return $this->parent->getAdminPermission() . 'bar';
  }

}
