<?php

namespace Drupal\schema_based_config_forms\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase as CoreConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function array_key_exists;
use function count;
use function in_array;

/**
 * Base class for implementing system configuration forms via config schema.
 */
abstract class ConfigFormBase extends CoreConfigFormBase {

  public const MAIN_FORM_GROUP_ELEMENT_NAME = 'config';

  public const ELEMENT_TYPES_TO_GROUP_CHILDREN_IN_PARENT = [
    'vertical_tabs',
  ];

  /**
   * Constructs a new config form object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected SchemaConfigFormElementPluginManager $schemaElementPluginManager,
    TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.schema_config_form_element'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form[static::MAIN_FORM_GROUP_ELEMENT_NAME] = [
      '#tree' => FALSE,
    ];
    $main_form_group_type = $this->getContainerTypeForDepth(0);
    $group_configs_in_main_form_element = in_array($main_form_group_type, static::ELEMENT_TYPES_TO_GROUP_CHILDREN_IN_PARENT, TRUE);
    if ($main_form_group_type) {
      $form[static::MAIN_FORM_GROUP_ELEMENT_NAME]['#type'] = $main_form_group_type;
    }

    $config_names = $this->getEditableConfigNames();
    foreach ($config_names as $config_name) {
      $schema = $this->getSchema($config_name, TRUE);
      if (!$schema) {
        continue;
      }

      $config_form_key = $this->sanitizeConfigKeyForForm($config_name);
      $config_object = $this->configFactory->get($config_name);
      $form[$config_form_key] = $this->buildFormElement($config_form_key, $schema, $config_object, 1);
      if ($form[$config_form_key] === NULL) {
        unset($form[$config_form_key]);
      }
      elseif ($group_configs_in_main_form_element && $form[$config_form_key]) {
        $form[$config_form_key]['#group'] = static::MAIN_FORM_GROUP_ELEMENT_NAME;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    $config_names = $this->getEditableConfigNames();
    foreach ($config_names as $config_name) {
      $schema = $this->getSchema($config_name, TRUE);
      if (!$schema) {
        continue;
      }

      $sanitized_config_name = $this->sanitizeConfigKeyForForm($config_name);
      $data = [];
      foreach (array_keys($schema['mapping']) as $key) {
        $data[$key] = $form_state->getValue([$sanitized_config_name, $key]);
      }
      $this->configFactory
        ->getEditable($config_name)
        ->setData($data)
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Recursively builds the field data for form elements.
   *
   * Add form-specific build overrides by overriding this method, and passing on
   * anything that does not need custom handling to the parent method.
   */
  protected function buildFormElement(string $key, array $field_schema, Config $config, int $depth, string $config_key_prefix = ''): ?array {
    if (isset($field_schema['#schema_form_hide']) && $field_schema['#schema_form_hide'] === TRUE) {
      return NULL;
    }

    if (!empty($field_schema['#schema_form_plugin'])) {
      try {
        $schema_to_element_plugin = $this->schemaElementPluginManager
          ->getInstanceByPluginId($field_schema['#schema_form_plugin']);
      }
      catch (PluginException) {
        $this->getLogger('schema_based_config_forms')->error(
          'Plugin "@plugin_id" requested for field "@key" in schema for "@config_object" could not be instantiated. Error: @exception_msg',
          [
            '@plugin_id' => $field_schema['#schema_form_plugin'],
            '@key' => $config_key_prefix . ($config_key_prefix ? '.' : '') . $key,
            '@config_object' => $config->getName(),
          ]
        );
        return NULL;
      }

      return $schema_to_element_plugin
        ->buildElement($field_schema, $this->getDefaultValue($config, $key, $config_key_prefix));
    }
    elseif (empty($field_schema['type'])) {
      $this->getLogger('schema_based_config_forms')->warning(
        'No type provided for field "@key" in schema for "@config_object"',
        [
          '@key' => $config_key_prefix . ($config_key_prefix ? '.' : '') . $key,
          '@config_object' => $config->getName(),
        ]
      );
      return NULL;
    }

    if (isset($field_schema['#parent_type']) && $field_schema['#parent_type'] === 'config_object') {
      return $this->buildMapping($key, $field_schema, $config, $depth, $config_key_prefix);
    }

    $type_schema = [];
    $parent_type = $field_schema['type'];
    $child_type = NULL;
    $schema_to_element_plugin = NULL;
    // If the form config type doesn't have a plugin directly associated,
    // fall back up the schema type definitions tree if possible.
    while (!$schema_to_element_plugin && $parent_type) {
      $type_schema = $this->getSchema($parent_type, FALSE);
      if (!$type_schema) {
        $this->getLogger('schema_based_config_forms')->error(
          'Config schema type "@parent_type" that is supposed to be the parent of type "@child_type" does not exist!',
          [
            '@parent_type' => $parent_type,
            '@child_type'  => $child_type ?: $key,
          ]
        );
        break;
      }
      $child_type = $type_schema['type'];
      $parent_type = $type_schema['#parent_type'] ?? NULL;

      $schema_to_element_plugin = $this->schemaElementPluginManager
        ->getInstanceBySchemaType($child_type);

      // Build mappings for mapping types.
      // Implementing it here allows us to support falling through to standard
      // mapping logic for types defining specific schema mappings.
      if (!$schema_to_element_plugin && ($child_type === 'mapping' || $parent_type === 'mapping')) {
        $mapping_schema = $field_schema + $type_schema;
        if (isset($field_schema['label'])) {
          $mapping_schema['label'] = $field_schema['label'];
        }
        return $this->buildMapping($key, $mapping_schema, $config, $depth, $config_key_prefix);
      }
    }

    if ($schema_to_element_plugin) {
      unset($type_schema['#parent_type']);
      return $schema_to_element_plugin
        ->buildElement($field_schema, $this->getDefaultValue($config, $key, $config_key_prefix), $type_schema);
    }

    $this->getLogger('schema_based_config_forms')->error(
      'No plugins support schema type "@schema_type" or its parent types for schema_based_config_forms. You may need to enable a module providing a supporting plugin, write your own plugin, or use hook_schema_config_form_element_info_alter() to assign this type to an existing plugin.',
      ['@schema_type' => $field_schema['type']]
    );
    return NULL;
  }

  /**
   * Recursively builds form elements for a mapping and its properties.
   */
  protected function buildMapping(string $key, array &$field_schema, Config $config, int $depth, string $config_key_prefix = ''): ?array {
    $is_config_object = isset($field_schema['#parent_type']) && $field_schema['#parent_type'] === 'config_object';
    $field = [
      '#tree' => TRUE,
    ];

    if (!empty($field_schema['label']) && empty($field_schema['#schema_form_label_hide'])) {
      $field['#title'] = $field_schema['label'];
    }

    $field_type = $field_schema['#type'] ?? $this->getContainerTypeForDepth($depth, $key);
    if ($field_type) {
      $field['#type'] = $field_type;

      if ($field['#type'] === 'details') {
        $field['#open'] = $field_schema['#open'] ?? TRUE;
      }
    }

    if (!empty($field_schema['mapping'])) {
      $subelements_prefix = $config_key_prefix . ($config_key_prefix ? '.' : '') . $key;
      $group_children_in_parent = in_array($field_type, static::ELEMENT_TYPES_TO_GROUP_CHILDREN_IN_PARENT, TRUE);
      foreach ($field_schema['mapping'] as $child_key => $child_item) {
        $field[$child_key] = $this->buildFormElement(
          $child_key,
          $child_item,
          $config,
          $depth + 1,
          $is_config_object ? '' : $subelements_prefix
        );

        if ($field[$child_key] === NULL) {
          unset($field[$child_key]);
        }
        elseif ($group_children_in_parent && $field[$child_key]) {
          $field[$child_key]['#group'] = $key;
        }
      }
      unset($field_schema['mapping']);
    }

    return $field;
  }

  /**
   * Function defining what container element type should be used.
   */
  protected function getContainerTypeForDepth(int $depth, ?string $key = NULL): ?string {
    return match($depth) {
      0 => count($this->getEditableConfigNames()) > 1 ? 'vertical_tabs' : NULL,
      1 => count($this->getEditableConfigNames()) > 1 ? 'details' : NULL,
      2 => 'details',
      default => 'fieldset',
    };
  }

  /**
   * Sanitizes a configuration name (e.g. module.settings) for use as Form API keys.
   */
  protected function sanitizeConfigKeyForForm(string $config_name): string {
    return str_replace('.', '__', $config_name);
  }

  /**
   * Helper function to get a default value.
   *
   * Automatically ignores config overrides.
   */
  protected function getDefaultValue(Config $config, string $key, string $key_prefix = ''): mixed {
    $merged_key = $this->mergeKey($key, $key_prefix);
    return $config->getOriginal($merged_key, FALSE);
  }

  /**
   * Helper function to get a complete config key string, given the possibility of a passed prefix.
   */
  protected function mergeKey(string $key, string $key_prefix): string {
    return $key_prefix
      ? $key_prefix . '.' . $key
      : $key;
  }

  /**
   * Gets the schema for a given config type.
   */
  final protected function getSchema(string $config_name, bool $is_config_object): ?array {
    // Because this method is marked final, it's safe to refer to this abstract
    // class with 'self' to share static cache across children.
    $config_schemas =& drupal_static(self::class . '-schema');
    if (!isset($config_schemas)) {
      $config_schemas = [];
    }

    if (array_key_exists($config_name, $config_schemas)) {
      return $config_schemas[$config_name] ?: NULL;
    }

    $original_config_schemas =& drupal_static(self::class . '-schema-originals');
    if (!isset($original_config_schemas)) {
      // Calling getDefiniton() for a specific record is actually destructive to
      // base data about schemas. (!!!)
      // So we have to bypass the typed config manager's caching to get the
      // original data from the YML.
      // We cache it statically once per execution so we don't have to keep
      // wiping the typed config manager's cache.
      $this->typedConfigManager->useCaches(FALSE);
      $original_config_schemas = $this->typedConfigManager->getDefinitions();
      $this->typedConfigManager->useCaches(TRUE);
    }

    $config_schemas[$config_name] = $this->typedConfigManager->getDefinition($config_name);
    $config_schema =& $config_schemas[$config_name];

    if ($config_schema['type'] === 'undefined') {
      $this->getLogger('schema_based_config_forms')->error(
        'Could not load definition for config schema type "@config_name"',
        ['@config_name' => $config_name]
      );
      $config_schema = NULL;
      return NULL;
    }

    if ($is_config_object) {
      $config_schema['#parent_type'] = 'config_object';

      // If the definition has no label and so has inherited the config_object label, remove it.
      if (isset($config_schema['label']) && $config_schema['label'] === $this->getConfigObjectCoreSchemaLabel()) {
        unset($config_schema['label']);
      }

      // Remove some non-editable keys core adds to all config objects, such as langcode.
      foreach ($this->getConfigObjectCoreSchemaKeys() as $core_key) {
        unset($config_schema['mapping'][$core_key]);
      }
    }
    elseif (
      !empty($original_config_schemas[$config_name]['type'])
      && $config_schema['type'] !== $original_config_schemas[$config_name]['type']
    ) {
      // Provide data about what the next type up in the chain would be.
      $config_schema['#parent_type'] = $original_config_schemas[$config_name]['type'];
    }
    elseif (
      $config_schema['type'] === 'mapping'
      && !isset($config_schema['mapping'])
      && isset($original_config_schemas[$config_name]['mapping'])
    ) {
      // Provide specific mapping data for mapping type schemas.
      $config_schema['mapping'] = $original_config_schemas[$config_name]['mapping'];
    }

    return $config_schema;
  }

  /**
   * Helper method that returns the top-level keys from the mapping for core's base config_object schema type.
   */
  final protected function getConfigObjectCoreSchemaKeys(): array {
    // Because this method is marked final, it's safe to refer to this abstract
    // class with 'self' to share static cache across children.
    $config_object_core_schema_keys = &drupal_static(self::class . '-schema-config_object-mapping_keys');
    if (isset($config_object_core_schema_keys)) {
      return $config_object_core_schema_keys;
    }

    $config_object_schema = $this->typedConfigManager->getDefinition('config_object');
    $config_object_core_schema_keys = array_keys($config_object_schema['mapping'] ?? []);
    return $config_object_core_schema_keys;
  }

  /**
   * Helper method that returns the label for core's base config_object schema type.
   */
  final protected function getConfigObjectCoreSchemaLabel(): string {
    // Because this method is marked final, it's safe to refer to this abstract
    // class with 'self' to share static cache across children.
    $config_object_core_schema_label = &drupal_static(self::class . '-schema-config_object-label');
    if (isset($config_object_core_schema_label)) {
      return $config_object_core_schema_label;
    }

    $config_object_schema = $this->typedConfigManager->getDefinition('config_object');
    $config_object_core_schema_label = $config_object_schema['label'] ?? '';
    return $config_object_core_schema_label;
  }

}
