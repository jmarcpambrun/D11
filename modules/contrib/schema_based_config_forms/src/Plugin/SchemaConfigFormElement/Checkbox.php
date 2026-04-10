<?php

namespace Drupal\schema_based_config_forms\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;

/**
 * Provides form element building for checkbox fields.
 *
 * @SchemaConfigFormElement(
 *   id = "checkbox",
 *   title = @Translation("Checkbox"),
 *   description = @Translation("Provides form element building for the 'boolean' schema type."),
 *   types = {
 *     "boolean",
 *   },
 *   weight = 10,
 * )
 */
class Checkbox extends SchemaConfigFormElementPluginBase {

  /**
   * {@inheritdoc}
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#return_value' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    $element = [
      '#type'          => 'checkbox',
      '#default_value' => $default_value,
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    return $element;
  }

}
