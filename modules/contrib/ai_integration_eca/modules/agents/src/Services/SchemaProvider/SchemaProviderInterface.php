<?php

namespace Drupal\ai_integration_eca_agents\Services\SchemaProvider;

/**
 * Interface that describes a provider of the ECA model JSON Schema.
 */
interface SchemaProviderInterface {

  /**
   * Get the JSON Schema that an ECA model payload has to comply with.
   *
   * @return string
   *   Returns the JSON Schema, encoded as JSON.
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function getModelSchema(): string;

}
