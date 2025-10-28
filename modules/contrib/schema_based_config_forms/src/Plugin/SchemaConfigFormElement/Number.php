<?php

namespace Drupal\schema_based_config_forms\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;
use function array_key_exists;

/**
 * Provides form element building for number fields.
 *
 * @SchemaConfigFormElement(
 *   id = "number",
 *   title = @Translation("Number"),
 *   description = @Translation("Provides form element building for numbers."),
 *   types = {
 *     "float",
 *     "integer",
 *   },
 *   weight = 10,
 * )
 */
class Number extends SchemaConfigFormElementPluginBase {

  /**
   * {@inheritdoc}
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#min'  => NULL,
    '#max'  => NULL,
    '#step' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    $element = [
      '#type'          => 'number',
      '#default_value' => $default_value,
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    if ($type_schema['type'] === 'integer' && !array_key_exists('#step', $element)) {
      $element['#step'] = 1;
    }

    return $element;
  }

}
