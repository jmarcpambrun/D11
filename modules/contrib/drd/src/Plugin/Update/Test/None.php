<?php

namespace Drupal\drd\Plugin\Update\Test;

use Drupal\drd\Update\PluginStorageInterface;
use Drupal\drd\Update\PluginTestInterface;

/**
 * Provides a update test plugin that does nothing.
 *
 * @Update(
 *  id = "notest",
 *  admin_label = @Translation("No Test"),
 * )
 */
class None extends Base {

  /**
   * {@inheritdoc}
   */
  public function test(PluginStorageInterface $storage): PluginTestInterface {
    $this->succeeded = TRUE;
    return $this;
  }

}
