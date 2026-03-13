<?php

namespace Drupal\schema_based_config_forms_linkit\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;

/**
 * Provides freeform form element building for Linkit fields.
 *
 * @SchemaConfigFormElement(
 *   id = "linkit_url",
 *   title = @Translation("Linkit URL"),
 *   description = @Translation("Provides form element building for Linkit URL fields. Values are stored unvalidated as set by the Linkit Element."),
 *   types = {
 *     "linkit_url",
 *   },
 *   weight = 10,
 * )
 */
class LinkitUrl extends SchemaConfigFormElementPluginBase {

  /**
   * {@inheritdoc}
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#size' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    if (empty($field_schema['#linkit_profile_id'])) {
      throw new \InvalidArgumentException('linkit-based config schema must define the ID of the Linkit profile to use as property "#linkit_profile_id".');
    }

    $element = [
      '#type'                          => 'linkit',
      '#autocomplete_route_name'       => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => $field_schema['#linkit_profile_id'],
      ],
      '#default_value'                 => $default_value,
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    return $element;
  }

}
