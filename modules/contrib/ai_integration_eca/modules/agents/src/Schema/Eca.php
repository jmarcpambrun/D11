<?php

namespace Drupal\ai_integration_eca_agents\Schema;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\schemata\Schema\SchemaInterface;

/**
 * Provides support for the ECA Model data type in Schemata.
 */
class Eca implements SchemaInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The data definition.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface
   */
  protected DataDefinitionInterface $definition;

  /**
   * Metadata values that describe the schema.
   *
   * @var array
   */
  protected array $metadata = [
    'title' => 'ECA Model Schema',
    'description' => 'The schema describing the properties of an ECA model.',
  ];

  /**
   * Typed Data objects for all properties.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface[]
   */
  protected array $properties = [];

  /**
   * Constructs an ECA schema.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The Typed Data definition.
   * @param array $properties
   *   The properties.
   */
  public function __construct(DataDefinitionInterface $definition, array $properties = []) {
    $this->definition = $definition;
    $this->addProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function addProperties(array $properties): void {
    $this->properties += $properties;
  }

  /**
   * {@inheritdoc}
   *
   * The Schemata module builds the "id" of the schema from the route
   * "schemata.<entity type id>". Those routes only exist for real entity
   * types, so the ECA entity type is used here. A made up entity type id
   * would make the serialization fail with a RouteNotFoundException.
   */
  public function getEntityTypeId(): string {
    return 'eca';
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleId(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties(): array {
    return $this->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

}
