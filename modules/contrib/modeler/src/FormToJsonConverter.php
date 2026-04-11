<?php

namespace Drupal\modeler;

use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Converts a Drupal form array to a JSON-serializable format.
 */
class FormToJsonConverter {

  use StringTranslationTrait;

  public function __construct(
    protected YamlSchemaLookup $yamlSchemaLookup,
  ) {}

  /**
   * Convert Drupal form array to JSON-serializable format.
   *
   * @param array $form
   *   The processed Drupal form array.
   * @param string $plugin_schema_key
   *   The plugin's config schema key for YAML schema discovery.
   *
   * @return array
   *   The JSON-serializable form representation.
   */
  public function convert(array $form, string $plugin_schema_key = ''): array {
    $json_form = [];
    // Track the most recent textarea key so we can link use_yaml /
    // validate_yaml checkboxes back to the textarea they control.
    $last_textarea_key = '';

    foreach (Element::children($form, TRUE) as $key) {
      // Skip internal Drupal form elements.
      if (in_array($key, ['actions', 'form_build_id', 'form_token', 'form_id'], TRUE)) {
        continue;
      }
      $element = $form[$key];

      if ($key === 'eca_token_info') {
        $element['#markup'] = $this->t('This component supports tokens. You can drag tokens from the replay step data into the configuration fields.');
      }

      // Handle elements with markup (either with container type
      // or without type).
      if (isset($element['#markup']) && in_array($element['#type'] ?? '', ['container', 'markup'], TRUE)) {
        $field = [
          'key' => $key,
          'type' => 'markup',
          'markup' => $element['#markup'],
        ];

        // Only add title if it exists.
        if (isset($element['#title'])) {
          $field['title'] = $element['#title'];
        }

        $json_form[] = $field;
        continue;
      }
      if (isset($element['#type'])) {
        $field = [
          'key' => $key,
          'type' => $element['#type'],
          'title' => $element['#title'] ?? $key,
          'description' => $element['#description'] ?? '',
          'required' => $element['#required'] ?? FALSE,
          'default_value' => $element['#default_value'] ?? '',
          'token_support' => $element['#eca_token_replacement'] ?? FALSE,
        ];

        // For textarea fields, attempt to discover a YAML schema from
        // the Drupal config schema system. Convention: a schema at
        // "yaml.{plugin_schema_key}.{field_key}" defines the structure
        // of the YAML content for this field.
        if ($element['#type'] === 'textarea' && $plugin_schema_key !== '') {
          $yaml_schema = $this->yamlSchemaLookup->lookup($plugin_schema_key, $key);
          if ($yaml_schema !== NULL) {
            $field['yaml_schema'] = $yaml_schema;
          }
        }

        // Remember the last textarea key so that subsequent use_yaml /
        // validate_yaml checkboxes can reference the field they control.
        if ($element['#type'] === 'textarea') {
          $last_textarea_key = $key;
        }

        // When we encounter use_yaml or validate_yaml checkboxes produced
        // by ECA's FormFieldYamlTrait, annotate them with the key of the
        // textarea they belong to so the frontend can link them.
        if ($key === 'use_yaml' && $last_textarea_key !== '') {
          $field['yaml_field'] = $last_textarea_key;
        }
        if ($key === 'validate_yaml' && $last_textarea_key !== '') {
          $field['yaml_field'] = $last_textarea_key;
        }

        // Handle select/options elements.
        if (isset($element['#options'])) {
          $field['options'] = $element['#options'];
        }

        // Handle specific field types.
        switch ($element['#type']) {
          case 'checkbox':
            $field['default_value'] = (bool) ($element['#default_value'] ?? FALSE);
            break;

          case 'number':
            $field['min'] = $element['#min'] ?? NULL;
            $field['max'] = $element['#max'] ?? NULL;
            $field['step'] = $element['#step'] ?? NULL;
            break;
        }

        $json_form[] = $field;
      }
    }
    return $json_form;
  }

}
