<?php

namespace Drupal\drd\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filters by given list of available major versions.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("drd_major_versions")
 */
class MajorVersions extends BaseManyToOne {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->valueTitle = $this->t('Major versions');
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * Helper function that generates the options.
   *
   * @return array
   *   List of core versions for a select form element.
   */
  public function generateOptions(): array {
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->database->select('drd_major', 'm')
      ->fields('m', ['majorversion'])
      ->condition('m.hidden', '0')
      ->isNull('m.parentproject');
    return $query
      ->orderBy('m.majorversion')
      ->distinct()
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

}
