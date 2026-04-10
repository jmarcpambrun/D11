<?php

namespace Drupal\schema_based_config_forms\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;

/**
 * Provides form element building for URL fields.
 *
 * @SchemaConfigFormElement(
 *   id = "url",
 *   title = @Translation("URL"),
 *   description = @Translation("Provides form element building for url fields."),
 *   weight = 10,
 * )
 */
class Url extends SchemaConfigFormElementPluginBase {

  /**
   * {@inheritdoc}
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#size'    => NULL,
    '#pattern' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    $element = [
      '#type'          => 'url',
      '#default_value' => $default_value,
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    return $element;
  }

}
