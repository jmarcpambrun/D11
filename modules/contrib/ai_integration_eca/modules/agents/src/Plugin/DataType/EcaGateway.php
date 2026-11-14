<?php

namespace Drupal\ai_integration_eca_agents\Plugin\DataType;

use Drupal\ai_integration_eca_agents\TypedData\EcaGatewayDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Data type plugin of the ECA Gateway data type.
 */
#[DataType(
  id: 'eca_gateway',
  label: new TranslatableMarkup('ECA Gateway'),
  definition_class: EcaGatewayDefinition::class,
)]
class EcaGateway extends Map {

}
