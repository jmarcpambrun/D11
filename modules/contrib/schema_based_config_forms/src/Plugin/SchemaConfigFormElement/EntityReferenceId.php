<?php

namespace Drupal\schema_based_config_forms\Plugin\SchemaConfigFormElement;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\schema_based_config_forms\SchemaConfigFormElementPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides form element building for entity reference ID fields.
 *
 * @SchemaConfigFormElement(
 *   id = "entity_reference_id",
 *   title = @Translation("Entity Reference ID"),
 *   description = @Translation("Provides form element building for schema types dealing with entity references (IDs)."),
 *   weight = 10,
 * )
 */
class EntityReferenceId extends SchemaConfigFormElementPluginBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public const DEFAULT_ELEMENT_PROPS_IN_SCHEMA = parent::DEFAULT_ELEMENT_PROPS_IN_SCHEMA + [
    '#selection_settings' => NULL,
    '#selection_handler'  => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->setEntityTypeManger($container->get('entity_type.manager'));

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    if (empty($field_schema['#target_type'])) {
      $this->logger->error(
        'Config schema with type @schema_type must define a "#target_type" property specifying the target entity type ID!',
        ['@schema_type' => $type_schema['type']]
      );
      return NULL;
    }

    try {
      $entity_storage = $this->entityTypeManager->getStorage($field_schema['#target_type']);
    }
    catch (InvalidPluginDefinitionException $ex) {
      $this->logger->error(
        "Could not load storage for entity type \"@entity_type_id\" - check that this type ID is correct. Error when loading follows.\n@exception_msg",
        [
          '@entity_type_id' => $field_schema['#target_type'],
          '@exception_msg' => $ex->getMessage(),
        ]
      );
      return NULL;
    }

    $element = [
      '#type'          => 'entity_autocomplete',
      '#target_type'   => $field_schema['#target_type'],
      '#default_value' => $default_value
        ? $entity_storage->load($default_value)
        : NULL,
    ];

    $this->addSimpleElementProps($element, $field_schema);
    $this->setRequired($element, $field_schema);

    return $element;
  }

  /**
   * Sets the entity type manager.
   */
  protected function setEntityTypeManger(EntityTypeManagerInterface $entity_type_manager): static {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

}
