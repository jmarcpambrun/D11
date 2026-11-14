<?php

namespace Drupal\ai_integration_eca_agents\Plugin\DataType;

use Drupal\ai_integration_eca_agents\TypedData\EcaPluginDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Data type plugin of the ECA Plugin data type.
 */
#[DataType(
  id: 'eca_plugin',
  label: new TranslatableMarkup('ECA Plugin'),
  definition_class: EcaPluginDefinition::class,
)]
class EcaPlugin extends Map {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    try {
      return $this->get('label')->getString();
    }
    catch (\InvalidArgumentException $e) {
      // The definition of the plugin doesn't specify a label.
      return parent::getName();
    }
  }

}
