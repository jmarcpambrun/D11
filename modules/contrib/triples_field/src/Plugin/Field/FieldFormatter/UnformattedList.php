<?php

namespace Drupal\triples_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementations for 'triples_field' formatter.
 *
 * @FieldFormatter(
 *   id = "triples_field_unformatted_list",
 *   label = @Translation("Unformatted list"),
 *   field_types = {"triples_field"}
 * )
 */
class UnformattedList extends ListBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    $element['#attributes']['class'][] = Html::getId('triples_field-unformatted-list');
    $settings = $this->getSettings();
    $field_name = $items->getName();
    foreach ($items as $delta => $item) {
      if ($settings['inline']) {
        if (!isset($item->_attributes)) {
          $item->_attributes = [];
        }
        $item->_attributes += ['class' => ['container-inline']];
      }
      $element[$delta] = [
        '#theme' => 'triples_field_item',
        '#settings' => $settings,
        '#field_settings' => $this->getFieldSettings(),
        '#field_name' => $field_name,
        '#item' => $item,
      ];
    }

    return $element;
  }

}
