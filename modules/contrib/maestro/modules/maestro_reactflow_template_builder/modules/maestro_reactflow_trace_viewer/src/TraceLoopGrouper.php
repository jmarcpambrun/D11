<?php

namespace Drupal\maestro_reactflow_trace_viewer;

/**
 * Groups Maestro queue rows into regeneration loops for the trace viewer.
 */
class TraceLoopGrouper {

  /**
   * Groups queue rows by regen_count into labelled, orderable loops.
   *
   * @param array $queueRows
   *   Each row is an associative array with at least a 'regen_count' key
   *   (int). The row shape otherwise passes through unchanged into the
   *   returned loop's 'steps' array.
   *
   * @return array
   *   [
   *     'loops' => ['<regen_count>' => ['label' => string, 'current' => bool, 'steps' => array], ...],
   *     'defaultLoop' => '<regen_count>' (string, the highest regen_count present, '0' if none),
   *   ]
   */
  public static function group(array $queueRows): array {
    $byRegen = [];
    foreach ($queueRows as $row) {
      $regenCount = (int) ($row['regen_count'] ?? 0);
      $byRegen[$regenCount][] = $row;
    }
    ksort($byRegen);

    $regenCounts = array_keys($byRegen);
    $maxRegen = empty($regenCounts) ? 0 : max($regenCounts);

    $loops = [];
    foreach ($byRegen as $regenCount => $steps) {
      $loops[(string) $regenCount] = [
        'label' => 'Loop ' . ($regenCount + 1),
        'current' => $regenCount === $maxRegen,
        'steps' => $steps,
      ];
    }

    return [
      'loops' => $loops,
      'defaultLoop' => (string) $maxRegen,
    ];
  }

}
