<?php

namespace Drupal\schema_based_config_forms;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function array_key_exists;

/**
 * Base class for schema config form element plugins.
 */
abstract class SchemaConfigFormElementPluginBase extends PluginBase implements SchemaConfigFormElementInterface {

  use StringTranslationTrait;

  /**
   * Logger for schema config form element plugins.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.channel.schema_based_config_forms'),
    );
  }

  /**
   * Constructs a SchemaConfigFormElementPluginBase object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $string_translation, LoggerInterface $logger) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->setStringTranslation($string_translation);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * Helper to map schema properties to element properties.
   *
   * @param array &$element
   *   The Form API element array to alter.
   * @param array $field_schema
   *   The field schema passed to ::buildElement().
   * @param array|null $props
   *   (optional) A mapping array of schema keys to element property settings.
   *   See ScheamConfigFormElementInterface::DEFAULT_ELEMENT_PROPS_IN_SCHEMA for
   *   more details.
   */
  protected function addSimpleElementProps(array &$element, array $field_schema, ?array $props = NULL): void {
    $props ??= static::DEFAULT_ELEMENT_PROPS_IN_SCHEMA;

    foreach ($props as $schema_key => $prop_settings) {
      if (!array_key_exists($schema_key, $field_schema)) {
        continue;
      }

      $prop_settings ??= [];
      $element_prop_key = ($prop_settings['prop_key'] ?? NULL) ?: $schema_key;
      // $field_schema comes from YML, so it's okay to pass its data to t().
      $element_prop_value = empty($prop_settings['translatable'])
        ? $field_schema[$schema_key]
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        : $this->t($field_schema[$schema_key]);
      $element[$element_prop_key] = $element_prop_value;
    }
  }

  /**
   * Helper to set a form element's required-ness based on field schema.
   */
  protected function setRequired(array &$element, array $field_schema): void {
    $element['#required'] = empty($field_schema['nullable']);
  }

}
