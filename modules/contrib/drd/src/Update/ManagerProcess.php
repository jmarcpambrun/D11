<?php

namespace Drupal\drd\Update;

/**
 * Manages discovery and instantiation of DRD Update Process plugins.
 */
class ManagerProcess extends Manager {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'process';
  }

  /**
   * {@inheritdoc}
   */
  public function getSubDir(): string {
    return 'Plugin/Update/Process';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginInterface(): string {
    return PluginProcessInterface::class;
  }

}
