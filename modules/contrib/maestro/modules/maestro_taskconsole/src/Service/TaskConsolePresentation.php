<?php

namespace Drupal\maestro_taskconsole\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * Builds presentation data without changing task execution or assignments.
 */
class TaskConsolePresentation {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected DateFormatterInterface $dateFormatter,
    protected RendererInterface $renderer,
    protected ModuleHandlerInterface $moduleHandler,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Returns context only for an executable task and its owning process.
   */
  public function context($queue_id, $process_id = NULL): ?array {
    $queue = $this->entityTypeManager->getStorage('maestro_queue')->load($queue_id);
    if (!$queue || ($process_id !== NULL && (int) $queue->process_id->getString() !== (int) $process_id)) {
      return NULL;
    }
    if (!MaestroEngine::canUserExecuteTask($queue_id, $this->currentUser->id())) {
      return NULL;
    }
    $process_id = (int) $queue->process_id->getString();
    $process = MaestroEngine::getProcessEntryById($process_id);
    if (!$process) {
      return NULL;
    }
    $template_id = MaestroEngine::getTemplateIdFromProcessId($process_id);
    return [
      'queue' => $queue,
      'process_id' => $process_id,
      'template_id' => $template_id,
      'template' => MaestroEngine::getTemplate($template_id),
      'task_definition' => MaestroEngine::getTemplateTaskByID($template_id, $queue->task_id->getString()),
      'task' => $queue->task_label->getString(),
      'flow' => $process->process_name->getString(),
      // Preserve the existing console's Assigned timestamp semantics.
      'assigned' => $this->dateFormatter->format((int) $queue->created->getString(), 'custom', 'j M Y · H:i'),
      'stage' => $this->stage($process_id),
    ];
  }

  /**
   * Reads configured process milestones, without predicting future stages.
   */
  public function stage($process_id): array {
    $template = MaestroEngine::getTemplate(MaestroEngine::getTemplateIdFromProcessId($process_id));
    $count = (int) MaestroEngine::getProcessVariable('workflow_timeline_stage_count', $process_id);
    if (!$template || empty($template->default_workflow_timeline_stage_count) || $count < 1) {
      return [];
    }
    $current = (int) MaestroEngine::getProcessVariable('workflow_current_stage', $process_id);
    $message = (string) MaestroEngine::getProcessVariable('workflow_current_stage_message', $process_id);
    $valid_current = $current >= 1 && $current <= $count;
    return [
      'count' => $count,
      'current' => $valid_current ? $current : 0,
      'message' => $message,
      'summary' => $valid_current
        ? $this->t('Stage @current of @count', ['@current' => $current, '@count' => $count])
        : $this->t('Workflow stages'),
    ];
  }

  /**
   * Renders milestones, retaining Maestro's status-bar alteration hook.
   *
   * Call only after authorizing the task/process context.
   */
  public function milestones($process_id, array $stage): array {
    if (!$stage) {
      return [];
    }
    $entries = MaestroEngine::getAllStatusEntriesForProcess($process_id);
    $steps = [];
    for ($number = 1; $number <= $stage['count']; $number++) {
      $current = $number === $stage['current'];
      $state = $current ? 'current' : ($stage['current'] && $number < $stage['current'] ? 'previous' : 'upcoming');
      $steps[] = [
        'number' => $number,
        'label' => $current ? $stage['message'] : ($entries[$number]['message'] ?? ''),
        // The status machine name set by the task that reached this stage.
        'status' => $entries[$number]['status'] ?? '',
        'state' => $state,
        'current' => $current,
        // Position is not proof that every task in an earlier stage succeeded.
        'state_label' => $current ? $this->t('Current stage') : ($state === 'previous' ? $this->t('Previous stage') : $this->t('Upcoming stage')),
      ];
    }
    $render = ['#theme' => 'maestro_taskconsole_milestones', '#steps' => $steps, '#summary' => $stage['summary']];
    // Existing implementations expect rendered markup here, not a render array.
    $status_bar = method_exists($this->renderer, 'renderInIsolation')
      ? $this->renderer->renderInIsolation($render)
      : $this->renderer->renderPlain($render);
    $this->moduleHandler->invokeAll('maestro_task_console_status_bar_alter', [&$status_bar, $process_id]);
    return ['#children' => $status_bar, '#cache' => ['max-age' => 0]];
  }

}
