<?php

declare(strict_types=1);

namespace Drupal\maestro\Engine;

/**
 * The outcome of one orchestration run.
 *
 * @see \Drupal\maestro\Engine\MaestroOrchestrationRunner::run()
 */
enum OrchestrationOutcome {

  /**
   * The queue was cleaned.
   */
  case Completed;

  /**
   * Another orchestration run holds the lock, so this one did not run.
   */
  case Locked;

  /**
   * The run-as account does not exist or is blocked, so the run was refused.
   */
  case AccountUnavailable;

}
