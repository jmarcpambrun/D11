<?php

namespace Drupal\maestro;

/**
 * The Maestro Engine Task Orchestration Module Interface.
 * Used explicitly for tasks which interface with the Orchestration module.
 */
interface MaestroEngineTaskOrchestrationInterface extends MaestroEngineTaskInterface {

  /**
   * Defines how a Maestro task responds to the Orchestrator Module's 
   * task completion signal. Each task type can behave as they choose to 
   * based on this signal.
   */
  public function orchestrationAllowTaskCompletion($queueID);

}
