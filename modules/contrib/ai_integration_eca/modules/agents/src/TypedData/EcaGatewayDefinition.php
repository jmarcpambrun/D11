<?php

namespace Drupal\ai_integration_eca_agents\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

/**
 * Definition of the ECA Gateway data type.
 */
final class EcaGatewayDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    $properties = [];

    $properties['gateway_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID of the element'))
      ->setRequired(TRUE)
      ->addConstraint('Regex', [
        'pattern' => '/^[\w]+$/',
        'message' => 'The %value ID is not valid.',
      ]);

    $properties['type'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setSetting('allowed_values_function', '')
      ->setSetting('allowed_values', [0])
      ->addConstraint('Choice', [
        'choices' => [0],
      ]);

    $properties['successors'] = ListDataDefinition::create('eca_successor')
      ->setLabel('Successors');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'eca_gateway'): EcaGatewayDefinition {
    return new EcaGatewayDefinition(['type' => $type]);
  }

}
