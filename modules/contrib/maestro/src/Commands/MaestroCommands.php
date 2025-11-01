<?php

namespace Drupal\maestro\Commands;

use Drush\Commands\DrushCommands;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal;

/**
 * Drush commands for the Maestro module.
 */
class MaestroCommands extends DrushCommands {

  /**
   * Run the Maestro orchestrator (clean the queue).
   *
   * @command maestro:orchestrate
   * @aliases maestro-orchestrate, maestro-orch
   * @usage drush maestro:orchestrate
   *   Run the Maestro orchestrator to process the queue.
   */
  public function orchestrate() {
    $this->logger()->notice('Starting Maestro orchestrator...');

    $engine = new MaestroEngine();
    $config = Drupal::config('maestro.settings');
    $lockDuration = intval($config->get('maestro_orchestrator_lock_execution_time')) ?: 30;
    $lock = Drupal::lock();

    try {
      if ($lock->acquire('maestro_orchestrator', $lockDuration)) {
        if ($config->get('maestro_orchestrator_development_mode') == '1') {
          $engine->enableDevelopmentMode();
          $this->logger()->notice('Development mode enabled.');
        }

        $engine->cleanQueue();
        $lock->release('maestro_orchestrator');

        $this->logger()->success('Maestro orchestrator finished.');
        return 0;
      }
      else {
        $this->logger()->warning('Could not acquire maestro_orchestrator lock. Another process may be running.');
        return 1;
      }
    }
    catch (\Throwable $e) {
      $this->logger()->error('Exception while running orchestrator: ' . $e->getMessage());
      return 1;
    }
  }

  /**
   * Start a Maestro process by template machine name.
   *
   * @command maestro:start-process
   * @aliases maestro-start-process, maestro-start
   * @param string $template_machine_name
   *   The machine name of the Maestro template to start.
   * @usage drush maestro:start-process my_template
   *   Start a Maestro process using the 'my_template' template.
   */
  public function startProcess($template_machine_name) {
    $template = MaestroEngine::getTemplate($template_machine_name);
    if ($template) {
      $engine = new MaestroEngine();
      $pid = $engine->newProcess($template_machine_name);
      if ($pid) {
        $this->logger()->success('Process Started');
        $config = \Drupal::config('maestro.settings');
        // Run the orchestrator for us once on process kickoff.
        $this->orchestrate();
        return 0;
      }
      else {
        $this->logger()->error('Error!  Process unable to start!');
        return 1;
      }
    }
    else {
      $this->logger()->error('Error!  No template by that name exists!');
      return 1;
    }
  }

}
