<?php

namespace Drupal\key_value_field\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;

/**
 * Plugin implementation of the 'key_value' field type.
 *
 * @FieldType(
 *   id = "key_value",
 *   label = @Translation("Key / Value (plain)"),
 *   description = @Translation("Key / value pairs where the latter one is allowed to contain only plain text."),
 *   category = "key_value",
 *   default_widget = "key_value_textfield",
 *   default_formatter = "key_value",
 *   column_groups = {
 *     "key" = {
 *       "label" = @Translation("Key"),
 *       "translatable" = TRUE,
 *     },
 *     "value" = {
 *       "label" = @Translation("Value"),
 *       "translatable" = TRUE,
 *     },
 *     "description" = {
 *       "label" = @Translation("Description"),
 *       "translatable" = TRUE,
 *     },
 *   },
 * )
 */
class KeyValueItem extends StringItem {

  // Add overrides from the common trait.
  use KeyValueFieldTypeTrait;

}
