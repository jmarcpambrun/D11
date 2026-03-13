<?php

namespace Drupal\drd\Entity\ViewBuilder;

use Drupal\drd\Entity\DomainInterface;

/**
 * View builder handler for drd_domain.
 *
 * @ingroup drd
 */
class Domain extends Base {

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode): void {
    /** @var \Drupal\drd\Entity\DomainInterface[] $entities */
    parent::buildComponents($build, $entities, $displays, $view_mode);

    if ($displays['drd_domain']->getComponent('monitoring')) {
      foreach ($entities as $id => $entity) {
        $monitoring = $this->buildMonitoringComponent($entity);
        if (!empty($monitoring)) {
          $build[$id]['monitoring'] = $monitoring;
        }
      }
    }

    if ($displays['drd_domain']->getComponent('queryresult')) {
      foreach ($entities as $id => $entity) {
        $query_result = $this->buildQueryResultComponent($entity);
        if (!empty($query_result)) {
          $build[$id]['queryresult'] = $query_result;
        }
      }
    }

    if ($displays['drd_domain']->getComponent('status_report')) {
      $this->moduleHandler->loadInclude('drd', 'install');
      foreach ($entities as $id => $entity) {
        $build[$id]['status_report'] = $this->buildStatusReportComponent($entity);
      }
    }

    if ($displays['drd_domain']->getComponent('latest_ping_status')) {
      foreach ($entities as $id => $entity) {
        $ping_status = $this->buildPingStatusComponent($entity);
        if (!empty($ping_status)) {
          $build[$id]['latest_ping_status'] = $ping_status;
        }
      }
    }

    if ($displays['drd_domain']->getComponent('messages')) {
      foreach ($entities as $id => $entity) {
        $messages = $this->buildMessagesComponent($entity);
        if (!empty($messages)) {
          $build[$id]['messages'] = $messages;
        }
      }
    }

    if ($displays['drd_domain']->getComponent('review')) {
      foreach ($entities as $id => $entity) {
        $review = $this->buildReviewComponent($entity);
        if (!empty($review)) {
          $build[$id]['review'] = $review;
        }
      }
    }
  }

  /**
   * Build "monitoring" component.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   Domain entity.
   *
   * @return array
   *   Component.
   */
  protected function buildMonitoringComponent(DomainInterface $domain): array {
    $build = [];

    $monitoring = $domain->getMonitoring();
    if (!empty($monitoring)) {
      $result = '<table>';
      foreach ($monitoring as $item) {
        $type = match ($item['status']) {
          'CRITICAL' => 'error',
          'WARNING' => 'warning',
          default => 'ok',
        };
        $name = '<div class="drd-monitoring-info ' . $type . '"><span class="drd-icon">&nbsp;</span><div class="label">' . $item['status'] . '</div>' . $item['label'] . '</div>';
        $result .= '<tr><td>' . $name . '</td><td>' . $item['message'] . '</td>';
      }
      $result .= '</table>';
      $build = [
        '#type' => 'fieldset',
        '#title' => $this->t('Monitoring'),
        '#attributes' => [
          'class' => ['monitoring'],
        ],
        'result' => ['#markup' => $result],
      ];
    }

    return $build;
  }

  /**
   * Build "queryresult" component.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   Domain entity.
   *
   * @return array
   *   Component.
   */
  protected function buildQueryResultComponent(DomainInterface $domain): array {
    $build = [];

    $queryResult = $domain->getQueryResult();
    if (!empty($queryResult)) {
      $result = '<h4>' . $queryResult['query'] . '</h4><p>' . $queryResult['info'] . '</p>';
      if (!empty($queryResult['rows'])) {
        $result .= '<table><thead><tr>';
        foreach ($queryResult['headers'] as $header) {
          $result .= '<th>' . $header . '</th>';
        }
        $result .= '</tr></thead><tbody>';
        foreach ($queryResult['rows'] as $row) {
          $result .= '<tr>';
          foreach ($row as $item) {
            $result .= '<td>' . $item . '</td>';
          }
          $result .= '</tr>';
        }
        $result .= '</tbody></table>';
      }
      $build = [
        '#type' => 'fieldset',
        '#title' => $this->t('Query result'),
        '#attributes' => [
          'class' => ['query-result'],
        ],
        'result' => ['#markup' => $result],
      ];
    }

    return $build;
  }

  /**
   * Build "status_report" component.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   Domain entity.
   *
   * @return array
   *   Component.
   */
  protected function buildStatusReportComponent(DomainInterface $domain): array {
    return [
      '#type' => 'status_report',
      '#requirements' => $domain->getRemoteRequirements(),
    ];
  }

  /**
   * Build "latest_ping_status" component.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   Domain entity.
   *
   * @return array
   *   Component.
   */
  protected function buildPingStatusComponent(DomainInterface $domain): array {
    $build = [];

    $status = $domain->renderPingStatus();
    if (!empty($status)) {
      $build = [
        '#markup' => $status,
      ];
    }

    return $build;
  }

  /**
   * Build "messages" component.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   Domain entity.
   *
   * @return array
   *   Component.
   */
  protected function buildMessagesComponent(DomainInterface $domain): array {
    $build = [];

    $messages = $domain->getMessages();
    if (!empty($messages)) {
      foreach ($messages as &$message) {
        $message['ts'] = $this->dateFormatter->format($message['ts']);
      }
      unset($message);
      $build = [
        '#type' => 'fieldset',
        '#title' => $this->t('Messages'),
        '#attributes' => [
          'class' => ['messages'],
        ],
        'list' => [
          '#theme' => 'status_messages',
          '#message_list' => $messages,
          '#status_headings' => [
            'status' => t('Status message'),
            'error' => t('Error message'),
            'warning' => t('Warning message'),
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Build "review" component.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   Domain entity.
   *
   * @return array
   *   Component.
   */
  protected function buildReviewComponent(DomainInterface $domain): array {
    $build = [];

    $review = $domain->getReview();
    if (!empty($review)) {
      foreach ($review as $item) {
        $result = is_string($item['result']) ?
          ['#markup' => $item['result']] :
          $item['result'];
        $build[] = [
          '#type' => 'fieldset',
          '#title' => $item['title'],
          '#attributes' => [
            'class' => ['review'],
          ],
          'result' => $result,
        ];
      }
    }

    return $build;
  }

}
