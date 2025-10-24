<?php

namespace Drupal\drd\Plugin\AdvancedQueue\JobType;

/**
 * Interface for action queue jobs.
 */
interface ActionInterface {

  /**
   * Process the action which got prepared in advancne.
   *
   * @return array|bool
   *   The result from the action.
   */
  public function processAction(): bool|array;

}
