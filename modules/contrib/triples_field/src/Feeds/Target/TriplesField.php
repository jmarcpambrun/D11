<?php

namespace Drupal\triples_field\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a field mapper for Triple Field.
 *
 * @FeedsTarget(
 *   id = "triples_field",
 *   field_types = {"triples_field"}
 * )
 */
class TriplesField extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $configs = \Drupal::config('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    $targetDefinition = FieldTargetDefinition::createFromFieldDefinition($field_definition);
    foreach ($subfields as $subfield) {
      $targetDefinition->addProperty($subfield);
    }
    return $targetDefinition;
  }

}
