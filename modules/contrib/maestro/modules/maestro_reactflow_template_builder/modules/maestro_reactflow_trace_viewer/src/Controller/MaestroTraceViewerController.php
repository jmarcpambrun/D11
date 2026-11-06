<?php

namespace Drupal\maestro_reactflow_trace_viewer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_reactflow_trace_viewer\TraceLoopGrouper;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the visual (ReactFlow-overlaid) trace for a Maestro process.
 */
class MaestroTraceViewerController extends ControllerBase {

  /**
   * Page callback for maestro_reactflow_trace_viewer.view.
   *
   * @param int $processID
   *   The Maestro process ID from the route.
   *
   * @return array
   *   A render array.
   */
  public function view($processID) {
    $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
    if (!$processRecord) {
      throw new NotFoundHttpException();
    }

    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($processID);
    $template = MaestroEngine::getTemplate($templateMachineName);
    if (!$template) {
      throw new NotFoundHttpException();
    }

    $trace = $this->buildTraceData($processID, $processRecord, $template);
    // Where the viewer re-fetches this data when auto-refresh is switched on.
    $trace['refreshUrl'] = Url::fromRoute('maestro_reactflow_trace_viewer.data', ['processID' => $processID])->toString();

    $build['container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'maestro_trace_viewer_canvas'],
      '#attached' => [
        'library' => ['maestro_reactflow_trace_viewer/maestro_reactflow_trace_viewer'],
        'drupalSettings' => [
          'maestroTrace' => $trace,
        ],
      ],
    ];
    $build['#title'] = $this->t('Visual Trace: @name', ['@name' => $trace['processName']]);

    return $build;
  }

  /**
   * Returns the current trace data as JSON, for the viewer's auto-refresh.
   *
   * The same data the page embeds in drupalSettings.maestroTrace, so a
   * refresh can replace it wholesale without reloading the page.
   */
  public function data($processID): JsonResponse {
    $processRecord = \Drupal::entityTypeManager()->getStorage('maestro_process')->load($processID);
    if (!$processRecord) {
      throw new NotFoundHttpException();
    }
    $template = MaestroEngine::getTemplate(MaestroEngine::getTemplateIdFromProcessId($processID));
    if (!$template) {
      throw new NotFoundHttpException();
    }

    $response = new JsonResponse($this->buildTraceData($processID, $processRecord, $template));
    // The data changes as the process runs; never let a browser or proxy cache it.
    $response->setPrivate();
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

  /**
   * Builds the trace data shared by the page and the JSON endpoint.
   */
  private function buildTraceData($processID, $processRecord, $template): array {
    $loopData = TraceLoopGrouper::group($this->buildQueueRows($processID));
    $traceArchiveEnabled = \Drupal::entityTypeManager()->hasDefinition('maestro_trace_snapshot');

    return [
      'processName' => $processRecord->process_name->getString(),
      // 1 once the process has finished; the viewer stops auto-refreshing then.
      'processComplete' => (int) $processRecord->complete->getString() === 1,
      'tasks' => $this->buildTasksArray($template),
      'maestroTaskColours' => $this->buildTaskColours(),
      'loops' => $loopData['loops'],
      'defaultLoop' => $loopData['defaultLoop'],
      'processVariables' => $this->buildProcessVariables($processID),
      'entityIdentifiers' => array_values(MaestroEngine::getAllEntityIdentifiersForProcess($processID)),
      'traceArchiveEnabled' => $traceArchiveEnabled,
      'snapshots' => $traceArchiveEnabled ? $this->buildSnapshots($processID) : [],
    ];
  }

  /**
   * Builds the tasks array in the same shape the ReactFlow editor uses.
   *
   * Mirrors MaestroTemplateBuilderForm::buildForm() (contrib,
   * web/modules/contrib/maestro/modules/maestro_template_builder/src/Form/MaestroTemplateBuilderForm.php:368-399),
   * minus the capabilities computation this read-only viewer doesn't need.
   */
  private function buildTasksArray($template): array {
    $tasks = [];
    foreach ($template->tasks as $task) {
      $tasks[] = [
        'id' => $task['id'],
        'type' => $task['tasktype'],
        'taskname' => $task['label'],
        'left' => $task['left'],
        'top' => $task['top'],
        'to' => explode(',', $task['nextstep']),
        'falsebranch' => explode(',', $task['nextfalsestep']),
      ];
    }
    return $tasks;
  }

  /**
   * Builds the task-type-to-color map, matching the editor's own outline
   * colors so this viewer's node outlines look identical to the editor's.
   *
   * Mirrors MaestroTemplateBuilderForm.php:401-407.
   */
  private function buildTaskColours(): array {
    $taskColours = [];
    $manager = \Drupal::service('plugin.manager.maestro_tasks');
    $plugins = $manager->getDefinitions();
    foreach ($plugins as $key => $taskPlugin) {
      $task = $manager->createInstance($key, [0, 0]);
      $taskColours[$key] = $task->getTaskColours();
    }
    return $taskColours;
  }

  /**
   * Builds one row per maestro_queue entity for this process.
   *
   * Mirrors the query MaestroTrace.php uses (contrib,
   * web/modules/contrib/maestro/src/Form/MaestroTrace.php:55-61).
   */
  private function buildQueueRows($processID): array {
    $rows = [];
    $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
    $query->condition('process_id', $processID)->accessCheck(FALSE);
    $entity_ids = $query->execute();
    $dateFormatter = \Drupal::service('date.formatter');

    foreach ($entity_ids as $queueID) {
      $queueRecord = MaestroEngine::getQueueEntryById($queueID);
      if (!$queueRecord) {
        continue;
      }
      $username = 'Maestro';
      $account = User::load($queueRecord->getOwnerId());
      if ($account) {
        $username = $account->getDisplayName();
      }
      $status = (int) $queueRecord->status->getString();
      $completed = $queueRecord->completed->getString();
      $started = $queueRecord->started_date->getString();

      $rows[] = [
        'queue_id' => (int) $queueRecord->id->getString(),
        'task_id' => $queueRecord->task_id->getString(),
        'task_label' => $queueRecord->task_label->getString(),
        'status' => $status,
        'status_label' => (string) MaestroEngine::getTaskStatusLabel($status),
        'archived' => (int) $queueRecord->archived->getString(),
        'completed_formatted' => $completed ? $dateFormatter->format($completed, 'custom', 'Y-m-d H:i:s') : 'Active',
        'started_date_formatted' => $started ? $dateFormatter->format($started, 'custom', 'Y-m-d H:i:s') : '',
        'owner' => $username,
        'regen_count' => (int) $queueRecord->regen_count->getString(),
      ];
    }
    return $rows;
  }

  /**
   * Builds one row per maestro_process_variables entity for this process.
   *
   * Mirrors MaestroTrace.php:146-181.
   */
  private function buildProcessVariables($processID): array {
    $pvs = [];
    $query = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->getQuery();
    $query->condition('process_id', $processID)->accessCheck(FALSE);
    $entity_ids = $query->execute();

    foreach ($entity_ids as $variableID) {
      $varRecord = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->load($variableID);
      if (!$varRecord) {
        continue;
      }
      $pvs[] = [
        'id' => $varRecord->id->getString(),
        'variable_name' => $varRecord->variable_name->getString(),
        'variable_value' => $varRecord->variable_value->getString(),
      ];
    }
    return $pvs;
  }

  /**
   * Builds a queue_id-keyed map of {before, after} snapshot payloads from
   * the optional maestro_trace_archive module's maestro_trace_snapshot
   * entities. Only call this when the caller has already confirmed that
   * entity type exists (see the traceArchiveEnabled check in view()) --
   * the before/after data it provides is an optional enhancement, not
   * something this viewer depends on.
   */
  private function buildSnapshots($processID): array {
    $snapshots = [];
    $storage = \Drupal::entityTypeManager()->getStorage('maestro_trace_snapshot');
    $query = $storage->getQuery();
    $query->condition('process_id', $processID)->accessCheck(FALSE);
    $ids = $query->execute();

    foreach ($ids as $id) {
      $record = $storage->load($id);
      if (!$record) {
        continue;
      }
      $queueId = (int) $record->get('queue_id')->value;
      $point = $record->get('snapshot_point')->value;
      $data = $record->get('data')->getValue();
      $payload = $data[0] ?? [];
      $snapshots[$queueId][$point] = [
        'process_variables' => $payload['process_variables'] ?? [],
        'entity_identifiers' => $payload['entity_identifiers'] ?? [],
      ];
    }
    return $snapshots;
  }

}
