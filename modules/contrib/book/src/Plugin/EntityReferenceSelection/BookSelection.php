<?php

declare(strict_types=1);

namespace Drupal\book\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Entity Reference handler for selecting book nodes.
 */
#[EntityReferenceSelection(
  id: "default:book_node_selection",
  label: new TranslatableMarkup("Book node selection"),
  group: "default",
  weight: 1,
  entity_types: ["node"]
)]
final class BookSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $query = parent::buildEntityQuery($match, $match_operator);
    $query->addTag('books');
    return $query;
  }

}
