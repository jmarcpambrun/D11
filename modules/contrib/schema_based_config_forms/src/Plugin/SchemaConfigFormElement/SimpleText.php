<?php

namespace Drupal\schema_based_config_forms\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;

/**
 * Provides form element building for simple text-based data types.
 *
 * @SchemaConfigFormElement(
 *   id = "simple_text",
 *   title = @Translation("Simple Text"),
 *   description = @Translation("Provides form element building for basic text schema types."),
 *   types = {
 *     "email",
 *     "label",
 *     "string",
 *   },
 *   weight = 10,
 * )
 */
class SimpleText extends SchemaConfigFormElementPluginBase {

  public const DEFAULT_FORM_ELEMENT_TYPE = 'textfield';

  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#size'      => NULL,
    '#pattern'   => NULL,
    '#maxlength' => NULL,
  ];

  public const SCHEMA_TYPE_TO_FORM_ELEMENT_TYPE = [
    'email'  => 'email',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    $element_type = static::SCHEMA_TYPE_TO_FORM_ELEMENT_TYPE[$type_schema['type']] ?? static::DEFAULT_FORM_ELEMENT_TYPE;
    $element = [
      '#type'          => $element_type,
      '#default_value' => $default_value,
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    return $element;
  }

}
