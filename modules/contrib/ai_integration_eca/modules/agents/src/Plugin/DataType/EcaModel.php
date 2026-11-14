<?php

namespace Drupal\ai_integration_eca_agents\Plugin\DataType;

use Drupal\ai_integration_eca_agents\TypedData\EcaModelDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Data type plugin of the ECA Model data type.
 */
#[DataType(
  id: 'eca_model',
  label: new TranslatableMarkup('ECA Model'),
  definition_class: EcaModelDefinition::class,
)]
class EcaModel extends Map {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return !empty($this->get('label')->getString()) ? $this->get('label')->getString() : 'the model';
  }

}
