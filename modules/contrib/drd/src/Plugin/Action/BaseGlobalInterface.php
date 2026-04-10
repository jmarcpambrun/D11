<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Interface for global actions that can be executed locally.
 */
interface BaseGlobalInterface extends BaseInterface {

  /**
   * Execute the global action.
   *
   * @return mixed
   *   The response from the action if available.
   */
  public function executeAction(): mixed;

}
