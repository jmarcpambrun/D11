<?php

namespace Drupal\drd\Update;

/**
 * Manages discovery and instantiation of DRD Update Test plugins.
 */
class ManagerTests extends Manager {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'test';
  }

  /**
   * {@inheritdoc}
   */
  public function getSubDir(): string {
    return 'Plugin/Update/Test';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginInterface(): string {
    return PluginTestInterface::class;
  }

}
