<?php

namespace Drupal\schema_based_config_forms\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;
use function array_key_exists;
use function function_exists;
use function is_array;
use function is_string;

/**
 * Provides form element building for number fields.
 *
 * @SchemaConfigFormElement(
 *   id = "text_format",
 *   title = @Translation("Formatted Text"),
 *   description = @Translation("Provides form element building for the 'text_format' schema type."),
 *   types = {
 *     "text_format",
 *   },
 *   weight = 10,
 * )
 */
class TextFormat extends SchemaConfigFormElementPluginBase {

  /**
   * {@inheritdoc}
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#base_type' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    $default_value = $default_value ?: [];
    $element = [
      '#type' => 'text_format',
      '#default_value' => $default_value['value'] ?? '',
      '#format' => $default_value['format'] ?? NULL,
    ];

    // For the #allowed_formats property, support either direct array value or a single string.
    if (array_key_exists('#allowed_formats', $field_schema)) {
      $allowed_formats = is_string($field_schema['#allowed_formats'])
        ? [$field_schema['#allowed_formats']]
        : $field_schema['#allowed_formats'];
      if (!is_array($allowed_formats)) {
        throw new \UnexpectedValueException(
          'text_format config schema defined with an unknown value type for the #allowed_formats property. Should be an array, or optionally a string for a single value.'
        );
      }
      $element['#allowed_formats'] = $allowed_formats;

      // If the #format property was not defined directly by default_value or
      // YAML, try to set it based off #allowed_formats.
      if (!isset($element['#format']) && $element['#allowed_formats']) {
        $element['#format'] = reset($element['#allowed_formats']);
      }
    }

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    // Support the allowed_formats module.
    if (array_key_exists('#allowed_format_hide_settings', $field_schema)) {
      if (is_array($field_schema['#allowed_format_hide_settings'])) {
        $element['#allowed_format_hide_settings'] = $field_schema['#allowed_format_hide_settings'];
      }
      elseif ($field_schema['#allowed_format_hide_settings'] === TRUE) {
        $element['#allowed_format_hide_settings'] = [
          'hide_guidelines' => TRUE,
          'hide_help' => TRUE,
        ];
      }
      else {
        throw new \UnexpectedValueException(
          'text_format config schema defined with an unknown value type for the allowed_format_hide_settings property. Should be an array, or optionally a string for a single value.'
        );
      }

      if (function_exists('_allowed_formats_remove_textarea_help')) {
        $element['#after_build'][] = '_allowed_formats_remove_textarea_help';
      }
    }

    return $element;
  }

}
