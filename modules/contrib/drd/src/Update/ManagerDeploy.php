<?php

namespace Drupal\drd\Update;

/**
 * Manages discovery and instantiation of DRD Update Deploy plugins.
 */
class ManagerDeploy extends Manager {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'deploy';
  }

  /**
   * {@inheritdoc}
   */
  public function getSubDir(): string {
    return 'Plugin/Update/Deploy';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginInterface(): string {
    return PluginDeployInterface::class;
  }

}
