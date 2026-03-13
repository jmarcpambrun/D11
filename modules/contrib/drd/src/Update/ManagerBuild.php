<?php

namespace Drupal\drd\Update;

/**
 * Manages discovery and instantiation of DRD Update Build plugins.
 */
class ManagerBuild extends Manager {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'build';
  }

  /**
   * {@inheritdoc}
   */
  public function getSubDir(): string {
    return 'Plugin/Update/Build';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginInterface(): string {
    return PluginBuildInterface::class;
  }

}
