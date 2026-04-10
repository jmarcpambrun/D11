<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\Release;

/**
 * Provides a 'ReleaseLock' action.
 *
 * @Action(
 *  id = "drd_action_release_lock",
 *  label = @Translation("Lock a project release"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd",
 * )
 */
class ReleaseLock extends BaseGlobal {

  /**
   * Indicator whether the release is locked.
   *
   * @var bool
   */
  protected bool $lock = TRUE;

  /**
   * Function to be executed.
   *
   * @var string
   */
  protected string $function = 'lockRelease';

  /**
   * {@inheritdoc}
   */
  public function executeAction(): mixed {
    $release = Release::find($this->arguments['projectName'], $this->arguments['version']);
    if (!$release) {
      $this->log('error', 'Release not found.');
      return FALSE;
    }

    if ($this->arguments['cores'] === NULL) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $release
        ->setLocked($this->lock)
        ->save();
      return TRUE;
    }

    if (empty($this->arguments['cores'])) {
      $this->log('warning', 'No core entity found.');
      return FALSE;
    }

    /** @var \Drupal\drd\Entity\CoreInterface $core */
    foreach ($this->arguments['cores'] as $core) {
      $core
        ->{$this->function}($release)
        ->save();
    }
    return TRUE;
  }

}
