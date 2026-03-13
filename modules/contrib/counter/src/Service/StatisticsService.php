<?php

namespace Drupal\counter\Service;

use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service that contains logic for building statistics.
 */
class StatisticsService {

  protected Connection $database;

  protected RequestStack $requestStack;

  protected $moduleHandler;

  public function __construct(
    Connection $database,
    RequestStack $request_stack,
    $module_handler,
  ) {
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->moduleHandler = $module_handler;
  }

  protected function normalizeTimestamps($start, $end) {
    $s = NULL;
    $e = NULL;
    if (!empty($start)) {
      $s = is_numeric($start) ? (int) $start : @strtotime($start);
    }
    if (!empty($end)) {
      $e = is_numeric($end) ? (int) $end : @strtotime($end);
      if ($e !== FALSE && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $e = strtotime('+1 day', $e);
      }
    }
    return [$s ?: NULL, $e ?: NULL];
  }

  public function computeRange(
    string $range,
    ?string $startParam = NULL,
    ?string $endParam = NULL,
  ): array {
    $now = time();

    if ($range === 'custom') {
      [$s, $e] = $this->normalizeTimestamps($startParam, $endParam);
      if (empty($s)) {
        $s = strtotime('today', $now);
      }
      if (empty($e)) {
        $e = strtotime('tomorrow', $now);
      }
      $bucket = ($e - $s <= 86400) ? 'hour' : 'day';
      return [$s, $e, $bucket];
    }

    switch ($range) {
      case 'today':
        $start = strtotime('today', $now);
        $end = strtotime('tomorrow', $now);
        $bucket = 'hour';
        break;
      case 'yesterday':
        $start = strtotime('yesterday', $now);
        $end = $start + 86400;
        $bucket = 'hour';
        break;
      case '7days':
        $end = strtotime('tomorrow', $now);
        $start = $end - 7 * 86400;
        $bucket = 'day';
        break;
      case '30days':
        $end = strtotime('tomorrow', $now);
        $start = $end - 30 * 86400;
        $bucket = 'day';
        break;
      case 'month':
        $start = strtotime(date('Y-m-01 00:00:00', $now));
        $end = strtotime('first day of +1 month', $start);
        $bucket = 'day';
        break;
      case 'year':
        $start = strtotime(date('Y-01-01 00:00:00', $now));
        $end = strtotime((date('Y', $start) + 1) . '-01-01 00:00:00');
        $bucket = 'month';
        break;
      case 'all':
      default:
        $min = (int) $this->database->select('counter', 'c')->fields(
          'c',
          ['created'],
        )->orderBy('created', 'ASC')->range(0, 1)->execute()->fetchField();
        $max = (int) $this->database->select('counter', 'c')->fields(
          'c',
          ['created'],
        )->orderBy('created', 'DESC')->range(0, 1)->execute()->fetchField();
        if (!$min || !$max) {
          $start = strtotime('today', $now) - 6 * 86400;
          $end = strtotime('tomorrow', $now);
          $bucket = 'day';
        }
        else {
          $start = (int) $min;
          $end = (int) $max + 86400;
          $bucket = ($end - $start > 365 * 86400) ? 'month' : 'day';
        }
        break;
    }

    return [$start, $end, $bucket];
  }

  protected function buildLabels(int $start, int $end, string $bucket): array {
    $labels = [];
    if ($bucket === 'hour') {
      $current = $start;
      while ($current < $end) {
        $labels[] = date('Y-m-d H:00', $current);
        $current += 3600;
      }
    }
    elseif ($bucket === 'day') {
      $current = strtotime(date('Y-m-d 00:00:00', $start));
      while ($current < $end) {
        $labels[] = date('Y-m-d', $current);
        $current += 86400;
      }
    }
    else {
      $dt = new \DateTime();
      $dt->setTimestamp($start)->modify('first day of this month')->setTime(
        0,
        0,
        0,
      );
      $endDt = new \DateTime();
      $endDt->setTimestamp($end);
      while ($dt->getTimestamp() < $endDt->getTimestamp()) {
        $labels[] = $dt->format('Y-m');
        $dt->modify('+1 month');
      }
    }
    return $labels;
  }

  protected function bucketKey(int $created, string $bucket): string {
    return $bucket === 'hour' ? date(
      'Y-m-d H:00',
      $created,
    ) : ($bucket === 'day' ? date('Y-m-d', $created) : date('Y-m', $created));
  }

  /**
   * Fetch raw rows for given start/end.
   */
  protected function fetchRows(int $start, int $end): array {
    $query = $this->database->select('counter', 'c');
    $query->fields('c', ['created', 'ip']);
    $query->condition('c.created', $start, '>=');
    $query->condition('c.created', $end, '<');
    return $query->execute()->fetchAll();
  }

  /**
   * Main method returning prepared stats for frontend.
   *
   * Returns both series for 'views' and 'unique' plus percent/direction for
   * each.
   */
  public function getStats(
    string $range,
    bool $compare = FALSE,
    ?string $startParam = NULL,
    ?string $endParam = NULL,
  ): array {
    [$start, $end, $bucket] = $this->computeRange(
      $range,
      $startParam,
      $endParam,
    );

    if (empty($start) || empty($end)) {
      $end = time();
      $start = $end - 7 * 86400;
      $bucket = 'day';
    }

    $labels = $this->buildLabels($start, $end, $bucket);
    $labelIndex = array_flip($labels);
    $series_views = array_fill(0, count($labels), 0);
    $unique_sets_per_bucket = array_fill(0, count($labels), []);
    $union_unique = [];

    $rows = $this->fetchRows($start, $end);
    foreach ($rows as $r) {
      $key = $this->bucketKey($r->created, $bucket);
      if (isset($labelIndex[$key])) {
        $idx = $labelIndex[$key];
        $series_views[$idx]++;
        $unique_sets_per_bucket[$idx][$r->ip] = TRUE;
        $union_unique[$r->ip] = TRUE;
      }
    }

    // unique series per bucket (counts)
    $series_unique = [];
    foreach ($unique_sets_per_bucket as $s) {
      $series_unique[] = count($s);
    }
    $total_views_current = array_sum($series_views);
    $total_unique_current = count($union_unique);

    // prepare previous period if requested
    $series_views_prev = NULL;
    $series_unique_prev = NULL;
    $total_views_prev = 0;
    $total_unique_prev = 0;

    if ($compare) {
      $span = $end - $start;
      $prevStart = $start - $span;
      $prevEnd = $start;

      // fetch previous rows
      $rowsPrev = $this->fetchRows($prevStart, $prevEnd);

      // we map previous timestamps to the current labels by shifting them forward by $span
      $unique_prev_union = [];
      $series_views_prev = array_fill(0, count($labels), 0);
      $unique_sets_prev_per_bucket = array_fill(0, count($labels), []);
      foreach ($rowsPrev as $r) {
        $shifted = $r->created + $span;
        $key = $this->bucketKey($shifted, $bucket);
        if (isset($labelIndex[$key])) {
          $idx = $labelIndex[$key];
          $series_views_prev[$idx]++;
          $unique_sets_prev_per_bucket[$idx][$r->ip] = TRUE;
          $unique_prev_union[$r->ip] = TRUE;
        }
      }
      $series_unique_prev = [];
      foreach ($unique_sets_prev_per_bucket as $s) {
        $series_unique_prev[] = count($s);
      }
      $total_views_prev = array_sum($series_views_prev);
      $total_unique_prev = count($unique_prev_union);
    }

    // percents & directions for each metric
    $percent_views = NULL;
    $direction_views = NULL;
    if ($compare) {
      if ($total_views_prev == 0) {
        $percent_views = ($total_views_current === 0) ? 0 : 100;
        $direction_views = ($total_views_current >= $total_views_prev) ? 'up' : 'down';
      }
      else {
        $diff = $total_views_current - $total_views_prev;
        $percent_views = round(abs($diff) / $total_views_prev * 100, 1);
        $direction_views = ($diff >= 0) ? 'up' : 'down';
      }
    }

    $percent_unique = NULL;
    $direction_unique = NULL;
    if ($compare) {
      if ($total_unique_prev == 0) {
        $percent_unique = ($total_unique_current === 0) ? 0 : 100;
        $direction_unique = ($total_unique_current >= $total_unique_prev) ? 'up' : 'down';
      }
      else {
        $diff = $total_unique_current - $total_unique_prev;
        $percent_unique = round(abs($diff) / $total_unique_prev * 100, 1);
        $direction_unique = ($diff >= 0) ? 'up' : 'down';
      }
    }

    return [
      'labels' => $labels,
      'series_views' => $series_views,
      'series_unique' => $series_unique,
      'series_views_prev' => $series_views_prev,
      'series_unique_prev' => $series_unique_prev,
      'total_views_current' => $total_views_current,
      'total_unique_current' => $total_unique_current,
      'total_views_prev' => $total_views_prev,
      'total_unique_prev' => $total_unique_prev,
      'percent_views' => $percent_views,
      'direction_views' => $direction_views,
      'percent_unique' => $percent_unique,
      'direction_unique' => $direction_unique,
      'bucket' => $bucket,
    ];
  }

}
