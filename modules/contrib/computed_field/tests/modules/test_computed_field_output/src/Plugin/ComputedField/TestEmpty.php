<?php

namespace Drupal\test_computed_field_output\Plugin\ComputedField;

use Drupal\computed_field\Attribute\ComputedField;
use Drupal\computed_field\Field\ComputedFieldDefinitionWithValuePluginInterface;
use Drupal\computed_field\Plugin\ComputedField\ComputedFieldBase;
use Drupal\computed_field\Plugin\ComputedField\SingleValueTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Computed field with an empty field.
 */
#[ComputedField(
  id: "test_empty",
  label: new TranslatableMarkup("Test empty"),
  field_type: "string",
  attach: [
    'scope' => 'base',
    'field_name' => 'test_empty',
    'entity_types' => ['entity_test' => []],
  ],
)]
class TestEmpty extends ComputedFieldBase {

  use SingleValueTrait;

  /**
   * {@inheritdoc}
   */
  public function singleComputeValue(EntityInterface $host_entity, ComputedFieldDefinitionWithValuePluginInterface $computed_field_definition): mixed {
    return NULL;
  }

}
