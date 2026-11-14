<?php

namespace Drupal\ai_integration_eca_agents\Services\ModelMapper;

use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\ai_integration_eca_agents\Plugin\DataType\EcaModel;
use Drupal\eca\Entity\Eca;

/**
 * Interface that describes a model mapper.
 */
interface ModelMapperInterface {

  /**
   * Map a payload to the ECA Model typed data.
   *
   * @param array $payload
   *   The external payload.
   *
   * @return \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaModel
   *   Returns the ECA Model typed data.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function fromPayload(array $payload): EcaModel;

  /**
   * Map an ECA-entity to the ECA Model typed data.
   *
   * @param \Drupal\eca\Entity\Eca $entity
   *   The ECA entity.
   *
   * @return \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaModel
   *   Returns the ECA Model typed data.
   */
  public function fromEntity(Eca $entity): EcaModel;

  /**
   * Convert typed data into plain values.
   *
   * The serializer is deliberately not used for this. Any module is free to
   * register a normalizer for the generic Map data type, which then silently
   * takes over the normalization of the ECA model and its elements.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The typed data to convert.
   *
   * @return mixed
   *   Returns a nested array for complex data and lists, the raw value
   *   otherwise.
   */
  public function toArray(TypedDataInterface $data): mixed;

}
