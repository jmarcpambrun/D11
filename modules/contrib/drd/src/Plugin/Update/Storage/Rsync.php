<?php

namespace Drupal\drd\Plugin\Update\Storage;

use Drupal\drd\Plugin\Update\RsyncTrait;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Provides a Rsync storage update plugin.
 *
 * @Update(
 *  id = "rsync",
 *  admin_label = @Translation("RSync from Live Site"),
 * )
 */
class Rsync extends Base {

  use RsyncTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'drupalroot' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareWorkingDirectory(): PluginStorageInterface {
    parent::prepareWorkingDirectory();

    try {
      $this->sync($this, TRUE);
    }
    catch (\Exception) {
      // Ignore.
    }
    return $this;
  }

}
