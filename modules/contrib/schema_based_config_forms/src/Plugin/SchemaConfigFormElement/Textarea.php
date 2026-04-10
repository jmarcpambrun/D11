<?php

namespace Drupal\schema_based_config_forms\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;

/**
 * Provides form element building for textarea-based data types.
 *
 * @SchemaConfigFormElement(
 *   id = "textarea",
 *   title = @Translation("Textarea"),
 *   description = @Translation("Provides form element building for 'text' schema type."),
 *   types = {
 *     "text",
 *   },
 *   weight = 10,
 * )
 */
class Textarea extends SchemaConfigFormElementPluginBase {

  /**
   * {@inheritdoc}
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#rows'      => NULL,
    '#cols'      => NULL,
    '#resizable' => NULL,
    '#maxlength' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    $element = [
      '#type'          => 'textarea',
      '#default_value' => $default_value,
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    return $element;
  }

}
