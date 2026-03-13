<?php

namespace Drupal\drd\Plugin\Update\Deploy;

use Drupal\drd\Plugin\Update\RsyncTrait;
use Drupal\drd\Update\PluginDeployInterface;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Provides a Rsync deploy update plugin.
 *
 * @Update(
 *  id = "rsync",
 *  admin_label = @Translation("RSync to Live Site"),
 * )
 */
class Rsync extends Base {

  use RsyncTrait;

  /**
   * {@inheritdoc}
   */
  public function deploy(PluginStorageInterface $storage): PluginDeployInterface {
    try {
      $this->sync($storage, FALSE);
      $this->succeeded = TRUE;
    }
    catch (\Exception) {
      // Ignore.
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function dryRun(PluginStorageInterface $storage): PluginDeployInterface {
    try {
      $this->sync($storage, FALSE, TRUE);
      $this->succeeded = TRUE;
    }
    catch (\Exception) {
      // Ignore.
    }
    return $this;
  }

}
