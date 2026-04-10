<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'ReleaseUnlock' action.
 *
 * @Action(
 *  id = "drd_action_release_unlock",
 *  label = @Translation("Unlock a project release"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd",
 * )
 */
class ReleaseUnlock extends ReleaseLock {

  /**
   * {@inheritdoc}
   */
  protected bool $lock = FALSE;

  /**
   * {@inheritdoc}
   */
  protected string $function = 'unlockRelease';

}
