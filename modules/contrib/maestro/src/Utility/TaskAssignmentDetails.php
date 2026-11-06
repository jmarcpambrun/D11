<?php

namespace Drupal\maestro\Utility;

use Drupal\maestro\Engine\MaestroEngine;

/**
 * Builds concise task context for either task console.
 */
class TaskAssignmentDetails {

  /**
   * Build details after the caller has checked access to the task.
   */
  public static function build($queue): array {
    $processID = $queue->process_id->getString();
    $template = MaestroEngine::getTemplate(MaestroEngine::getTemplateIdFromProcessId($processID));
    $storage = \Drupal::entityTypeManager()->getStorage('maestro_production_assignments');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('queue_id', $queue->id())->sort('id')->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $assignment) {
      $type = $assignment->assign_type->getString();
      $types = ['user' => t('User'), 'role' => t('Role'), 'group' => t('Group')];
      $method = $assignment->by_variable->value ? t('Variable') : t('Fixed');
      $rows[] = [
        ['data' => ['#plain_text' => $assignment->assign_id->getString()]],
        ['data' => ['#plain_text' => ($types[$type] ?? $type) . ' · ' . $method]],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['maestro-assignment-context']],
      '#cache' => ['max-age' => 0],
      'summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['maestro-assignment-context__summary']],
        'task' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => t('Task ID: @id', ['@id' => $queue->id()])],
        'process' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => t('Process ID: @id', ['@id' => $processID])],
        'template' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => t('Workflow: @name', ['@name' => $template ? $template->label() : '—'])],
      ],
      'assignments' => [
        '#type' => 'details',
        '#theme_wrappers' => ['maestro_assignment_panel'],
        '#attributes' => ['class' => ['maestro-detail-panel']],
        '#summary_attributes' => ['class' => ['maestro-detail-panel__summary']],
        '#title' => t('Assignment details (@count)', ['@count' => count($rows)]),
        '#open' => FALSE,
        'table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['maestro-detail-table']],
          '#header' => [t('Assigned to'), t('Assignment method')],
          '#rows' => $rows,
          '#empty' => t('No assignments.'),
        ],
      ],
    ];
  }

}
