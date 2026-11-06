<?php

namespace Drupal\maestro\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Engine\MaestroOrchestrationRunner;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Maestro Orchestrator class.
 */
class MaestroOrchestrator extends ControllerBase {

  use AutowireTrait;

  /**
   * Constructs a MaestroOrchestrator controller.
   *
   * @param \Drupal\maestro\Engine\MaestroOrchestrationRunner $orchestrationRunner
   *   The orchestration runner.
   */
  public function __construct(
    protected readonly MaestroOrchestrationRunner $orchestrationRunner,
  ) {
  }

  /**
   * Orchestrator method
   * This method is called by the menu router for /orchestrator
   * This runs the Maestro Engine.
   */
  public function orchestrate($token = '') {
    if ($token == '') {
      // bad!  must have a value.
      return new Response('Missing Orchestrator Token', 500);
    }

    $config = $this->config('maestro.settings');
    if ($config->get('maestro_orchestrator_token') != $token) {
      return new Response('Wrong Orchestrator Token', 500);
    }

      // TODO: Handle exceptions being thrown.
      // leaving it like this will simply stall the orchestrator execution for the time being
      // How to gracefully continue?  One process failing will stall the entire engine.
    $this->orchestrationRunner->run();

      // See CronController::run as we do the same thing to return a 204 with no content to satisfy the return response.
        return new Response('', 204);
      }

  /**
   * Method used to start a process.
   */
  public function startProcess($templateMachineName = '', $redirect = 'taskconsole') {
    $template = MaestroEngine::getTemplate($templateMachineName);
    if ($template) {
      $engine = new MaestroEngine();
      $pid = $engine->newProcess($templateMachineName);
      if ($pid) {
        \Drupal::messenger()->addMessage(t('Process Started'));
        // Run the orchestrator for us once on process kickoff.
        $this->orchestrationRunner->run();
      }
      else {
        \Drupal::messenger()->addError(t('Error!  Process unable to start!'));
      }
    }
    else {
      \Drupal::messenger()->addError(t('Error!  No template by that name exits!'));
    }


    if ($redirect == 'taskconsole') {
      return new RedirectResponse(Url::fromRoute('maestro_taskconsole.taskconsole')->toString());
    }
    elseif ($redirect == 'templates') {
      return new RedirectResponse(Url::fromRoute('entity.maestro_template.list')->toString());
    }
    else {
      return new RedirectResponse(Url::fromUserInput('/' . $redirect)->toString());
    }

  }

}
