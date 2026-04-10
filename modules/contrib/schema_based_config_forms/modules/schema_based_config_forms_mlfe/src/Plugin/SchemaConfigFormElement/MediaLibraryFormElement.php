<?php

namespace Drupal\schema_based_config_forms_mlfe\Plugin\SchemaConfigFormElement;

use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;
use function is_array;

/**
 * Provides form element building for Media Library Form Element fields.
 *
 * @SchemaConfigFormElement(
 *   id = "media_library_form_element",
 *   title = @Translation("Media Library Form Element"),
 *   description = @Translation("Provides form element building for Media Library Form Element fields."),
 *   types = {
 *     "media_reference_id",
 *     "media_references"
 *   },
 *   weight = 10,
 * )
 */
class MediaLibraryFormElement extends SchemaConfigFormElementPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    if (empty($field_schema['#allowed_bundles'])) {
      $this->logger->error(
        'Config schema with type @schema_type must define an "#allowed_bundles" property!',
        ['@schema_type' => $type_schema['type']]
      );
      return NULL;
    }

    $element = [
      '#type'            => 'media_library',
      '#allowed_bundles' => is_array($field_schema['#allowed_bundles'])
        ? $field_schema['#allowed_bundles']
        : [$field_schema['#allowed_bundles']],
      '#default_value'   => $default_value,
      '#cardinality'     => ($field_schema['#cardinality'] ?? NULL)
        ?: ($type_schema['type'] === 'media_references' ? -1 : 1),
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    return $element;
  }

}
