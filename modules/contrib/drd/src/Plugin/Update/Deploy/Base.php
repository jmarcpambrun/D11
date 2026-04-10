<?php

namespace Drupal\drd\Plugin\Update\Deploy;

use Drupal\drd\Plugin\Update\UpdateBase;
use Drupal\drd\Update\PluginDeployInterface;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Abstract DRD Update plugin to implement general deploy functionality.
 */
abstract class Base extends UpdateBase implements PluginDeployInterface {

  /**
   * Indicates if the deployment succeeded.
   *
   * @var bool
   */
  protected bool $succeeded = FALSE;

  /**
   * {@inheritdoc}
   */
  public function dryRun(PluginStorageInterface $storage): PluginDeployInterface {
    $storage->log('Nothing to do, dry run.');
    $this->succeeded = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function hasSucceeded(): bool {
    return $this->succeeded;
  }

}
