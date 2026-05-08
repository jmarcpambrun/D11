<?php

namespace Drupal\group\Plugin\Group\RelationHandler;

/**
 * Provides a default post install handler.
 *
 * In case a plugin does not define a handler, the empty class is used so that
 * others can still decorate the plugin-specific service.
 */
class EmptyPostInstall implements PostInstallInterface {

  use PostInstallTrait;

  public function __construct(PostInstallInterface $parent) {
    $this->parent = $parent;
  }

}
