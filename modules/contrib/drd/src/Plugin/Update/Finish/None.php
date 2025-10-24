<?php

namespace Drupal\drd\Plugin\Update\Finish;

use Drupal\drd\Update\PluginFinishInterface;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Provides a update finish plugin that does nothing.
 *
 * @Update(
 *  id = "nofinish",
 *  admin_label = @Translation("No Finishing"),
 * )
 */
class None extends Base {

  /**
   * {@inheritdoc}
   */
  public function finish(PluginStorageInterface $storage): PluginFinishInterface {
    $this->succeeded = TRUE;
    return $this;
  }

}
