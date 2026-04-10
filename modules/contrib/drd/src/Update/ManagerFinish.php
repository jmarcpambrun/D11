<?php

namespace Drupal\drd\Update;

/**
 * Manages discovery and instantiation of DRD Update Finish plugins.
 */
class ManagerFinish extends Manager {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'finish';
  }

  /**
   * {@inheritdoc}
   */
  public function getSubDir(): string {
    return 'Plugin/Update/Finish';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginInterface(): string {
    return PluginFinishInterface::class;
  }

}
