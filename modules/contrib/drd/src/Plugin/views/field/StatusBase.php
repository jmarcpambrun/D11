<?php

namespace Drupal\drd\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Render\Markup;
use Drupal\drd\Entity\BaseInterface;
use Drupal\drd\Entity\DomainInterface;
use Drupal\drd\Entity\Requirement;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract field handler to display status indicator for host, core and domain.
 */
abstract class StatusBase extends FieldPluginBase implements StatusBaseInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): StatusBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): MarkupInterface|string {
    if (!empty($this->options['relationship']) && !empty($values->_relationship_entities[$this->options['relationship']])) {
      /** @var \Drupal\drd\Entity\BaseInterface $remote */
      $remote = $values->_relationship_entities[$this->options['relationship']];
    }
    else {
      /** @var \Drupal\drd\Entity\BaseInterface $remote */
      $remote = $values->_entity;
    }
    if (!($remote instanceof DomainInterface) || !$remote->isInstalled()) {
      return '';
    }

    $warnings = $this->getCategories($remote, 'warnings');
    $errors = $this->getCategories($remote, 'errors');

    $allCategories = Requirement::getCategoryKeys();

    $output = '';
    foreach ($allCategories as $category) {
      $class = [$category];
      if (in_array($category, $errors, TRUE)) {
        $class[] = 'error';
      }
      elseif (in_array($category, $warnings, TRUE)) {
        $class[] = 'warning';
      }
      else {
        $class[] = 'ok';
      }
      $output .= '<span title="' . $category . '" class="' . implode(' ', $class) . '">' . $category . '</span>';
    }
    return Markup::create('<div class="drd-remote-status">' . $output . '</div>');
  }

  /**
   * Get aggregated warnings and error for a remote entity.
   *
   * @param \Drupal\drd\Entity\BaseInterface $remote
   *   The remote DRD entity.
   * @param string $field
   *   Either "warnings" or "errors".
   *
   * @return array
   *   List of categories in which the entity has errors or warnings.
   */
  private function getCategories(BaseInterface $remote, string $field): array {
    $ids = [];
    foreach ($this->getDomains($remote) as $domain) {
      foreach ($domain->get($field)->getValue() as $value) {
        $ids[] = $value['target_id'];
      }
    }

    if (empty($ids)) {
      return [];
    }

    $query = $this->database->select('drd_requirement', 'r')
      ->fields('r', ['category'])
      ->distinct()
      ->condition('r.id', $ids, 'IN');
    return $query
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

}
