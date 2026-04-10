<?php

namespace Drupal\drd\Plugin\Update\Process;

use Drupal\drd\Update\PluginProcessInterface;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Provides a update process plugin that does nothing.
 *
 * @Update(
 *  id = "noprocess",
 *  admin_label = @Translation("No Process"),
 * )
 */
class None extends Base {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'pulldb' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function process(PluginStorageInterface $storage): PluginProcessInterface {
    parent::process($storage);
    $this->succeeded = TRUE;
    return $this;
  }

}
