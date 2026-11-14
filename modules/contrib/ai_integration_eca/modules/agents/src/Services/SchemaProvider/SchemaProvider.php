<?php

namespace Drupal\ai_integration_eca_agents\Services\SchemaProvider;

use Drupal\ai_integration_eca_agents\Schema\Eca as EcaSchema;
use Drupal\ai_integration_eca_agents\TypedData\EcaModelDefinition;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Provides the JSON Schema of the ECA model.
 */
class SchemaProvider implements SchemaProviderInterface {

  /**
   * The generated schema, so it is only built once per request.
   *
   * @var string|null
   */
  protected ?string $schema = NULL;

  /**
   * Constructs a SchemaProvider instance.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   */
  public function __construct(
    protected SerializerInterface $serializer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSchema(): string {
    if ($this->schema === NULL) {
      $definition = EcaModelDefinition::create();
      $schema = new EcaSchema($definition, $definition->getPropertyDefinitions());
      $this->schema = $this->serializer->serialize($schema, 'schema_json:json', []);
    }

    return $this->schema;
  }

}
