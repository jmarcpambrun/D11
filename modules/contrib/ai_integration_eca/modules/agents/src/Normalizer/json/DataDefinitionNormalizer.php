<?php

namespace Drupal\ai_integration_eca_agents\Normalizer\json;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\schemata_json_schema\Normalizer\json\JsonNormalizerBase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer for DataDefinitionInterface instances.
 *
 * DataDefinitionInterface is the ultimate parent to all data definitions. This
 * service must always be low priority for data definitions, otherwise the
 * simpler normalization process it supports will take precedence over all the
 * complexities most entity properties contain before reaching this level.
 *
 * DataDefinitionNormalizer produces scalar value definitions.
 *
 * Unlike the other Normalizer services in the JSON Schema module, this one is
 * used by the hal_schemata normalizer. It is unlikely divergent requirements
 * will develop.
 *
 * All the TypedData normalizers extend from this class.
 */
class DataDefinitionNormalizer extends JsonNormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected string $supportedInterfaceOrClass = DataDefinitionInterface::class;

  /**
   * The inner normalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected NormalizerInterface $inner;

  /**
   * Constructs a DataDefinitionNormalizer object.
   *
   * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer
   *   The decorated normalizer.
   */
  public function __construct(NormalizerInterface $normalizer) {
    $this->inner = $normalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize(mixed $entity, $format = NULL, array $context = []): array|bool|string|int|float|null|\ArrayObject {
    $normalized = $this->inner->normalize($entity, $format, $context);

    if (
      empty($entity->getSetting('allowed_values_function'))
      && !empty($entity->getSetting('allowed_values'))
    ) {
      $normalized['properties'][$context['name']]['enum'] = array_keys($entity->getSetting('allowed_values'));
    }

    return $normalized;
  }

}
