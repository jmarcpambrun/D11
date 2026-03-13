<?php

namespace Drupal\schema_based_config_forms;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for Schema Config Form Element plugins.
 */
interface SchemaConfigFormElementInterface extends ContainerFactoryPluginInterface {

  /**
   * Defines FormElement properties that can be mapped directly from schema.
   *
   * Keys are schema keys. Values are either NULL, or arrays that may contain
   * these optional properties:
   * - prop_key: the form element key to map the schema value to. Will be the
   * - translatable: Add this key set to TRUE if schema value should be passed
   *   to t() for translation.
   *
   * @var array[]
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = [
    'label'        => [
      'prop_key' => '#title',
      'translatable' => TRUE,
    ],
    '#description'  => [
      'translatable' => TRUE,
    ],
    '#field_prefix' => [
      'translatable' => TRUE,
    ],
    '#field_suffix' => [
      'translatable' => TRUE,
    ],
  ];

  /**
   * Builds the form API elements for given schema.
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array;

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

}
