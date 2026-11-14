<?php

namespace Drupal\ai_integration_eca_agents\TypedData;

use Drupal\ai_integration_eca_agents\EcaElementType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

/**
 * Definition of the ECA Model data type.
 */
final class EcaModelDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values = []) {
    parent::__construct($values);

    $this->addConstraint('SuccessorsAreValid');
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    $properties = [];

    $properties['model_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setRequired(TRUE)
      ->addConstraint('Regex', [
        'pattern' => '/^[\w]+$/',
        'message' => 'The %value ID is not valid.',
      ]);

    $properties['label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setRequired(TRUE)
      ->addConstraint('Length', ['max' => 255])
      ->addConstraint('NotBlank');

    $properties['version'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Version'));

    $properties['documentation'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('documentation'));

    $properties['events'] = ListDataDefinition::create('eca_plugin')
      ->setLabel(new TranslatableMarkup('Events'))
      ->setRequired(TRUE)
      ->setItemDefinition(
        EcaPluginDefinition::create()
          ->setSetting('data_type', EcaElementType::Event)
      )
      ->addConstraint('NotNull');

    $properties['conditions'] = ListDataDefinition::create('eca_plugin')
      ->setLabel(new TranslatableMarkup('Conditions'))
      ->setItemDefinition(
        EcaPluginDefinition::create()
          ->setSetting('data_type', EcaElementType::Condition)
      );

    $properties['gateways'] = ListDataDefinition::create('eca_gateway')
      ->setLabel(new TranslatableMarkup('Gateways'));

    $properties['actions'] = ListDataDefinition::create('eca_plugin')
      ->setLabel(new TranslatableMarkup('Actions'))
      ->setRequired(TRUE)
      ->setItemDefinition(
        EcaPluginDefinition::create()
          ->setSetting('data_type', EcaElementType::Action)
      )
      ->addConstraint('NotNull');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'eca_model'): EcaModelDefinition {
    return new EcaModelDefinition(['type' => $type]);
  }

}
