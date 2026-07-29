<?php

declare(strict_types=1);

namespace Drupal\burndown_time_tracker\Hook;

use Drupal\burndown_time_tracker\Utils\BurndownTimeTrackerUtils;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;

/**
 * Views and page attachment hooks for Burndown Time Tracker.
 */
final class BurndownTimeTrackerViewsHooks {

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    if (empty($data['burndown_task__log']['table'])) {
      return;
    }

    unset($data['burndown_task__log']['table']['entity type']);

    $data['burndown_task__log']['table']['base'] = [
      'field' => 'entity_id',
      'title' => t('Task work log'),
      'help' => t('Work log entries stored on burndown tasks.'),
      'defaults' => [
        'field' => 'entity_id',
        'table' => 'burndown_task__log',
      ],
    ];

    $data['burndown_task__log']['entity_id'] = [
      'title' => t('Task entity ID'),
      'help' => t('The task entity ID for this log row.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
        'allow empty' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];

    $data['burndown_task__log']['log_created'] = [
      'title' => t('Log created'),
      'field' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
        'allow empty' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['burndown_task__log']['log_uid'] = [
      'title' => t('Log user'),
      'field' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
        'allow empty' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['burndown_task__log']['log_type'] = [
      'title' => t('Log type'),
      'field' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
        'allow empty' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['burndown_task__log']['log_comment'] = [
      'title' => t('Log comment'),
      'real field' => 'log_comment',
      'field' => [
        'id' => 'burndown_log_value',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
        'allow empty' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'mode' => 'comment',
      'source property' => 'burndown_task__log_log_comment',
    ];

    $data['burndown_task__log']['log_work_done'] = [
      'title' => t('Log work done'),
      'real field' => 'log_work_done',
      'field' => [
        'id' => 'burndown_log_value',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
        'allow empty' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'mode' => 'work',
      'source property' => 'burndown_task__log_log_work_done',
    ];

    $data['burndown_task__log']['delta'] = [
      'title' => t('Log delta'),
      'help' => t('The delta of this log row in the task log field.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
        'allow empty' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];

    $data['burndown_task__log']['entity_id']['relationship'] = [
      'base' => 'burndown_task_field_data',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Task'),
    ];

    if (isset($data['views']['nothing'])) {
      $data['views']['nothing']['field']['id'] = 'burndown_hours_operations';
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $attachments['#attached']['library'][] = 'burndown_time_tracker/hours_only_units';

    $route_name = \Drupal::routeMatch()->getRouteName();
    if (in_array($route_name, ['burndown.board', 'burndown.backlog'], TRUE) && \Drupal::currentUser()->hasPermission('burndown comment on task')) {
      $attachments['#attached']['library'][] = 'burndown_time_tracker/task_timer';
    }
  }

  /**
   * Implements hook_views_pre_view().
   */
  #[Hook('views_pre_view')]
  public function viewsPreView(ViewExecutable $view, $display_id, array &$args): void {
    if ($view->id() !== 'burndown_hours' || $display_id !== 'hours') {
      return;
    }

    $account = \Drupal::currentUser();
    $is_admin = $account->hasPermission('manage user work hours');
    $input = \Drupal::request()->query->all();
    $selected_uid = BurndownTimeTrackerUtils::resolveSelectedUserId($input, $is_admin, (int) $account->id());

    $toolbar = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['burndown-hours-toolbar'],
      ],
      '#cache' => [
        'contexts' => ['url.query_args', 'user.permissions'],
      ],
      'summary' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown-hours-summary'],
        ],
        'text' => [
          '#markup' => t('<strong>Total Hours (h):</strong> @hours', [
            '@hours' => number_format(BurndownTimeTrackerUtils::calculateTotalHours($selected_uid, $input), 2, '.', ''),
          ]),
        ],
      ],
      'filters' => \Drupal::formBuilder()->getForm('Drupal\\burndown_time_tracker\\Form\\HoursReportFilterForm', $selected_uid, $input, $is_admin),
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];

    if (BurndownTimeTrackerUtils::hasUnsupportedUnits($selected_uid, $input)) {
      $toolbar['warning'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            t('Only hours are supported for reporting the total time worked, please edit your entries that are using days or weeks.'),
          ],
        ],
        '#status_headings' => [
          'warning' => t('Warning message'),
        ],
      ];
    }

    $view->attachment_before[] = $toolbar;
  }

  /**
   * Implements hook_views_query_alter().
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    if ($view->id() !== 'burndown_hours' || $view->current_display !== 'hours') {
      return;
    }
    if (!$query instanceof Sql) {
      return;
    }

    $account = \Drupal::currentUser();
    $is_admin = $account->hasPermission('manage user work hours');
    $input = \Drupal::request()->query->all();
    $selected_uid = BurndownTimeTrackerUtils::resolveSelectedUserId($input, $is_admin, (int) $account->id());

    // Always enforce row-level user scope server-side.
    $query->addWhere(0, 'burndown_task__log.log_uid', $selected_uid, '=');
    $query->addField('burndown_task__log', 'log_work_done');
    $query->addField('burndown_task__log', 'log_comment');

    if (!empty($input['project']) && is_numeric($input['project'])) {
      $query->addWhere(0, 'burndown_task_field_data_burndown_task__log.project', (int) $input['project'], '=');
    }

    if (!empty($input['sprint']) && is_numeric($input['sprint'])) {
      $query->addWhere(0, 'burndown_task_field_data_burndown_task__log.sprint', (int) $input['sprint'], '=');
    }

    if (!empty($input['date_start'])) {
      $start = strtotime($input['date_start'] . ' 00:00:00');
      if ($start !== FALSE) {
        $query->addWhere(0, 'burndown_task__log.log_created', $start, '>=');
      }
    }

    if (!empty($input['date_end'])) {
      $end = strtotime($input['date_end'] . ' 23:59:59');
      if ($end !== FALSE) {
        $query->addWhere(0, 'burndown_task__log.log_created', $end, '<=');
      }
    }

    // Keep the report stable with newest log entries first.
    $query->addOrderBy('burndown_task__log', 'log_created', 'DESC');
  }

}
