<?php

namespace Drupal\maestro\Controller;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\maestro\Utility\MaestroStatus;
use Drupal\Core\Url;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\views\Views;

/**
 * Maestro Process Status Controller class.
 */
class MaestroProcessStatusController extends ControllerBase {

  /**
   * Returns response for the process status queries.
   *
   * @param int $processID
   *   The processID we wish to get details for.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response
   */
  public function getDetails($processID) {
    $target = $this->detailsTarget($processID);
    $urlOptions = $target === (string) $processID ? [] : ['query' => ['details_target' => $target, 'queue_id' => \Drupal::request()->query->get('queue_id', 0)]];
    $renderer = \Drupal::service('renderer');
    $build = [];
    $taskDetails = '';
    $queueID = \Drupal::request()->query->get('queue_id', 0);
    if ($queueID) {
      $queue = MaestroEngine::getQueueEntryById($queueID);
      if (!$queue || $queue->process_id->getString() != $processID || !MaestroEngine::canUserExecuteTask($queueID, \Drupal::currentUser()->id())) {
        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
      }
      $build['assignment_context'] = \Drupal\maestro\Utility\TaskAssignmentDetails::build($queue);
      $build['task_summary'] = $build['assignment_context']['summary'];
      $build['task_summary']['#weight'] = -20;
      unset($build['assignment_context']['summary']);
    }
    $modernProcess = !$queueID && str_starts_with($target, 'maestro-process-details-');
    if ($modernProcess) {
      if (!\Drupal::currentUser()->hasPermission('view maestro process entities')) {
        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
      }
      $workflow = MaestroEngine::getTemplate(MaestroEngine::getTemplateIdFromProcessId($processID));
      $build['process_summary'] = [
        '#type' => 'container', '#weight' => -20,
        '#attributes' => ['class' => ['maestro-assignment-context__summary']],
        'id' => ['#plain_text' => $this->t('Process ID: @id', ['@id' => $processID])],
        'workflow' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => $this->t('Workflow: @name', ['@name' => $workflow ? $workflow->label() : '—'])],
      ];
      $storage = \Drupal::entityTypeManager()->getStorage('maestro_queue');
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('process_id', $processID)->condition('archived', 0)->sort('id')->execute();
      foreach ($storage->loadMultiple($ids) as $task) {
        $assignments = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments')->getQuery()->accessCheck(FALSE)->condition('queue_id', $task->id())->count()->execute();
        if ($assignments) {
          $build['task_' . $task->id()] = \Drupal\maestro\Utility\TaskAssignmentDetails::build($task);
          $build['task_' . $task->id()]['summary']['task_name'] = ['#plain_text' => $this->t('Task: @task', ['@task' => $task->task_label->value])];
          unset($build['task_' . $task->id()]['summary']['process'], $build['task_' . $task->id()]['summary']['template']);
        }
      }
    }
    // first, we determine if the template even wants process shown.
    $template = MaestroEngine::getTemplate(MaestroEngine::getTemplateIdFromProcessId($processID));
    if (isset($template->show_details) && $template->show_details) {
      $templateName = MaestroEngine::getTemplateIdFromProcessId($processID);
      if (($queueID || $modernProcess) && \Drupal::hasService('maestro_taskconsole.presentation')) {
        // Use the same milestone component as the standard task console.
        $presentation = \Drupal::service('maestro_taskconsole.presentation');
        $build['status'] = $presentation->milestones($processID, $presentation->stage($processID));
      }
      else {
      // Skip the can execute check as this is not against a queue entry.
      $status_bar = MaestroStatus::getMaestroStatusBar($processID, 0, TRUE);
      $build['status'] = [
        '#prefix' => '<div id="processid-' . $processID . '" class="maestro-block-process ' . $templateName . '">',
        '#suffix' => '</div>',
        '#markup' => $status_bar['status_bar']['#children'],
      ];
      }
      $build['status']['#weight'] = -10;

      // Lets see if there's any views attached that we should be showing.
      if (isset($template->views_attached)) {
        foreach ($template->views_attached as $machine_name => $arr) {
          $view = Views::getView($machine_name);
          if ($view) {
            $display = explode(';', $arr['view_display']);
            $display_to_use = isset($display[0]) ? $display[0] : 'default';
            $render_build = $view->buildRenderable($display_to_use, [$processID, 0], FALSE);
            if ($render_build) {
              // DeprecationHelper to allow the d10 versions below 10.3 to use renderPlain
              $thisViewOutput = DeprecationHelper::backwardsCompatibleCall(
                currentVersion: \Drupal::VERSION,
                deprecatedVersion: '10.3',
                currentCallable: fn() => $renderer->renderInIsolation($render_build),
                deprecatedCallable: fn() => $renderer->renderPlain($render_build),
              );
              if ($thisViewOutput) {
                $task_information_render_array = [
                  '#theme' => 'taskconsole_views',
                  '#task_information' => $thisViewOutput,
                  '#title' => $view->storage->label(),
                ];
                // DeprecationHelper to allow the d10 versions below 10.3 to use renderPlain
                $taskDetails .= DeprecationHelper::backwardsCompatibleCall(
                  currentVersion: \Drupal::VERSION,
                  deprecatedVersion: '10.3',
                  currentCallable: fn() => $renderer->renderInIsolation($task_information_render_array),
                  deprecatedCallable: fn() => $renderer->renderPlain($task_information_render_array),
                );
              }
            }
          }
        }
      }
      // Anyone want to override the task details display or add to it?
      \Drupal::moduleHandler()->invokeAll('maestro_process_status_alter',
          [&$taskDetails, $processID, $template]);

      $build['views_bar'] = [
        '#children' => '<div class="maestro-process-details">' . $taskDetails . '</div>',
      ];

    }

    // $build = MaestroStatus::getMaestroStatusBar($processID, 0, TRUE);  //skip the can execute check as this is not against a queue entry
    // we replace the down arrow with the toggle up arrow
    $replace['expand'] = [
      '#attributes' => [
        'class' => ['maestro-timeline-status', 'maestro-status-toggle-up'],
        'title' => $this->t('Close Details'),
      ],
      '#type' => 'link',
      '#id' => 'maestro-id-ajax-' . $target,
      '#url' => Url::fromRoute('maestro.process_details_ajax_close', ['processID' => $processID], $urlOptions),
      '#title' => $this->t('Close Details'),
      '#ajax' => [
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    // Empty array.
    if (count($build) == 0) {
      $build['status'] = [
        '#plain_text' => $this->t('No details to show'),
      ];
    }

    $response = new AjaxResponse();
    // Row.
    $response->addCommand(new HtmlCommand('#details_replace_column_' . $target, $build));
    // Wrapper attribute TD tag. Toggle up arrow.
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $target . '', $replace['expand']));
    $response->addCommand(new CssCommand('#details_replace_row_' . $target, ['display' => 'table-row']));
    return $response;

  }

  /**
   * Close Details method.
   */
  public function closeDetails($processID) {
    $target = $this->detailsTarget($processID);
    $urlOptions = $target === (string) $processID ? [] : ['query' => ['details_target' => $target, 'queue_id' => \Drupal::request()->query->get('queue_id', 0)]];
    $build = [];
    // We replace the up arrow with the down arrow.
    $build['expand'] = [
      '#attributes' => [
        'class' => ['maestro-timeline-status', 'maestro-status-toggle'],
        'title' => $this->t('Open Details'),
      ],
      '#type' => 'link',
      '#id' => 'maestro-id-ajax-' . $target,
      '#url' => Url::fromRoute('maestro.process_details_ajax_open', ['processID' => $processID], $urlOptions),
      '#title' => $this->t('Open Details'),
      '#ajax' => [
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],

    ];

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#details_replace_column_' . $target, ''));
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $target, $build['expand']));
    $response->addCommand(new CssCommand('#details_replace_row_' . $target, ['display' => 'none']));

    return $response;
  }

  /**
   * Resolve a row-specific target while retaining legacy process-based links.
   */
  protected function detailsTarget($processID): string {
    $target = \Drupal::request()->query->get('details_target', '');
    $pattern = '/^maestro-process-details-' . preg_quote((string) $processID, '/') . '(?:--[a-zA-Z0-9_-]+)?$/D';
    return is_string($target) && preg_match($pattern, $target) ? $target : (string) $processID;
  }

}
