<?php

namespace Drupal\ai_integration_eca_agents\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Definition of the ECA Successor data type.
 */
final class EcaSuccessorDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    $properties = [];

    $properties['element_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('The ID of an existing action or gateway.'))
      ->setRequired(TRUE)
      ->addConstraint('Regex', [
        'pattern' => '/^[\w]+$/',
        'message' => 'The %value ID is not valid.',
      ]);

    $properties['condition'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('The ID of an existing condition.'))
      ->addConstraint('Regex', [
        'pattern' => '/^[\w]+$/',
        'message' => 'The %value ID is not valid.',
      ]);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'eca_successor'): EcaSuccessorDefinition {
    return new EcaSuccessorDefinition(['type' => $type]);
  }

}
