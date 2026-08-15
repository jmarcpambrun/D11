<?php

namespace Drupal\burndown\Services;

use Drupal\Component\Utility\DiffArray;

/**
 * Provides a service for listing changes on and Entity.
 */
class ChangeDiffService {

  /**
   * Constructs a new ChangeDiffService object.
   */
  public function __construct() {

  }

  /**
   * Build a nicely formatted list of changes on an entity.
   */
  public function getChanges($entity) {
    $change_list = '';

    if (isset($entity->original)) {
      $changed = array_keys(DiffArray::diffAssocRecursive($entity->toArray(), $entity->original->toArray()));

      // Produce a nicely formatted change list.
      foreach ($changed as $field_name) {
        // Ignore changed timestamp, sorting fields, the log, and any fields
        // that Drupal or entity presave hooks manage automatically on every
        // save so they don't produce spurious change-log entries.
        $ignore_fields = [
          'backlog_sort',
          'board_sort',
          'changed',
          'log',
          'revision_default',
          'revision_log',
          'revision_log_message',
          'revision_timestamp',
          'revision_translation_affected',
          'revision_uid',
          'user_id',
          'vid',
          'watch_list',
        ];
        if (in_array($field_name, $ignore_fields)) {
          continue;
        }

        $field_definition = $entity->getFieldDefinition($field_name);
        if ($field_definition && $field_definition->getType() === 'entity_reference') {
          $original = ($entity->original->get($field_name)->entity) ? $entity->original->get($field_name)->entity->label() : '';
          $new = ($entity->get($field_name)->entity) ? $entity->get($field_name)->entity->label() : '';
        }
        else {
          $original = $entity->original->get($field_name)->value;
          $new = $entity->get($field_name)->value;
        }

        $diff = $this->htmlDiff($original, $new);
        $label = $entity->get($field_name)->getFieldDefinition()->getLabel();
        if (!is_string($label)) {
          $label = $label->__tostring();
        }
        if (!empty($change_list)) {
          $change_list .= '<br>';
        }
        $change_list .= '<label>' . $label . '</label>: ' . $diff;
      }
    }

    return $change_list;
  }

  /**
   * Get a nicely formatted difference between two strings.
   *
   * @see: https://github.com/paulgb/simplediff/blob/master/php/simplediff.php
   */
  private function diff($old, $new) {
    $matrix = [];
    $maxlen = 0;
    $omax = 0;
    $nmax = 0;

    foreach ($old as $oindex => $ovalue) {
      $nkeys = array_keys($new, $ovalue);
      foreach ($nkeys as $nindex) {
        $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
          $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
        if ($matrix[$oindex][$nindex] > $maxlen) {
          $maxlen = $matrix[$oindex][$nindex];
          $omax = $oindex + 1 - $maxlen;
          $nmax = $nindex + 1 - $maxlen;
        }
      }
    }

    if ($maxlen == 0) {
      return [['d' => $old, 'i' => $new]];
    }

    return array_merge(
      $this->diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
      array_slice($new, $nmax, $maxlen),
      $this->diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
  }

  /**
   * Render a diff as HTML.
   *
   * @param string $old
   *   The original text.
   * @param string $new
   *   The new text.
   *
   * @return string
   *   HTML formatted diff.
   */
  private function htmlDiff($old, $new) {
    if (empty($new)) {
      return "<del>" . $old . "</del>";
    }
    if (empty($old)) {
      return "<ins>" . $new . "</ins>";
    }

    $ret = '';
    $diff = $this->diff(preg_split("/[\s]+/", $old), preg_split("/[\s]+/", $new));

    foreach ($diff as $k) {
      if (is_array($k)) {
        $ret .= (!empty($k['d']) ? "<del>" . implode(' ', $k['d']) . "</del> " : '') .
          (!empty($k['i']) ? "<ins>" . implode(' ', $k['i']) . "</ins> " : '');
      }
      else {
        $ret .= $k . ' ';
      }
    }

    return $ret;
  }

}
