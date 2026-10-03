<?php

namespace Drupal\maestro\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller backing the Maestro entries in the Navigation menu.
 */
class MaestroNavigationController extends ControllerBase {

  /**
   * Redirects to the most relevant Maestro overview page.
   *
   * This is the destination of the top-level "Maestro" link in the
   * Navigation sidebar. It exists only to give that link a real, permission
   * gated route to check access against, since the Task Console it usually
   * lands on lives in an optional submodule.
   */
  public function overview() {
    if ($this->moduleHandler()->moduleExists('maestro_taskconsole')) {
      return $this->redirect('maestro_taskconsole.taskconsole');
    }
    if ($this->currentUser()->hasPermission('administer maestro templates')) {
      return $this->redirect('entity.maestro_template.list');
    }
    return $this->redirect('<front>');
  }

  /**
   * Runs the orchestrator using the site's configured token.
   *
   * The maestro.orchestrator route itself only requires 'access content',
   * since it is designed to be hit externally (e.g. by cron) using the
   * token as the real safeguard. This route requires the stricter
   * 'administer maestro queue entities' permission so the Navigation menu
   * link is only offered to users who administer Maestro, then hands off
   * to the real orchestrator route.
   */
  public function runOrchestrator() {
    $token = $this->config('maestro.settings')->get('maestro_orchestrator_token');
    return $this->redirect('maestro.orchestrator', ['token' => $token]);
  }

}
