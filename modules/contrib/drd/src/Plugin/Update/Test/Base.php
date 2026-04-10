<?php

namespace Drupal\drd\Plugin\Update\Test;

use Drupal\drd\Plugin\Update\UpdateBase;
use Drupal\drd\Update\PluginTestInterface;

/**
 * Abstract DRD Update plugin to implement general test functionality.
 */
abstract class Base extends UpdateBase implements PluginTestInterface {

  /**
   * Indicates if the test succeeded.
   *
   * @var bool
   */
  protected bool $succeeded = FALSE;

  /**
   * {@inheritdoc}
   */
  final public function hasSucceeded(): bool {
    return $this->succeeded;
  }

}
