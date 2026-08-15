<?php

namespace Drupal\burndown_time_tracker\Plugin\views\field;

use Drupal\burndown_time_tracker\Utils\BurndownTimeTrackerUtils;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders the edit-time operation for a Burndown hours row.
 */
#[ViewsField('burndown_hours_operations')]
class BurndownHoursOperations extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // The view query already includes the row identifiers we need.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $task_id = $values->entity_id ?? NULL;
    $delta = $values->burndown_task__log_delta ?? NULL;

    if (!is_numeric($task_id) || !is_numeric($delta)) {
      return '';
    }

    $report_url = Url::fromRoute('view.burndown_hours.hours', [], [
      'query' => BurndownTimeTrackerUtils::filterReportQuery(\Drupal::request()->query->all()),
    ])->toString();

    $url = Url::fromRoute('burndown_time_tracker.time_entry_edit', [
      'burndown_task' => (int) $task_id,
      'delta' => (int) $delta,
    ], [
      'attributes' => [
        'class' => ['use-ajax', 'button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode(['width' => 700]),
      ],
      'query' => [
        'destination' => $report_url,
      ],
    ]);

    return Link::fromTextAndUrl($this->t('Edit time'), $url)->toString();
  }

}
