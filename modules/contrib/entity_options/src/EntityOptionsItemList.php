<?php

namespace Drupal\entity_options;

use Drupal\Core\Field\MapFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Implementation of FieldItemList for node options.
 */
class EntityOptionsItemList extends MapFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->ensurePopulated();
  }

  /**
   * Enforce singular value for this list.
   */
  protected function ensurePopulated() {
    if (!isset($this->list[0])) {
      $this->list[0] = $this->createItem();
    }
  }

}
