<?php

namespace Drupal\burndown_time_tracker\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders a raw Burndown log column from the result row.
 */
#[ViewsField('burndown_log_value')]
class BurndownLogValue extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // The view query adds the raw log columns explicitly.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $property = '';
    if (!empty($this->realField)) {
      $property = 'burndown_task__log_' . $this->realField;
    }
    if ($property === '' || !isset($values->{$property})) {
      return '';
    }

    $value = $values->{$property};
    if ($this->realField === 'log_comment') {
      $value = $this->normalizeComment($value);
    }

    return $this->sanitizeValue((string) $value);
  }

  /**
   * Normalize Burndown log comment values.
   */
  protected function normalizeComment($comment) : string {
    if (is_array($comment)) {
      if (isset($comment['value']) && is_scalar($comment['value'])) {
        return (string) $comment['value'];
      }
      return '';
    }

    if (!is_scalar($comment)) {
      return '';
    }

    $comment = (string) $comment;
    if ($comment === '') {
      return '';
    }

    $decoded = @unserialize($comment, ['allowed_classes' => FALSE]);
    if ($decoded !== FALSE || $comment === 'b:0;') {
      if (is_array($decoded) && isset($decoded['value']) && is_scalar($decoded['value'])) {
        return (string) $decoded['value'];
      }
      if (is_string($decoded)) {
        return $decoded;
      }
    }

    return $comment;
  }

}
