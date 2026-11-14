<?php

namespace Drupal\ai_integration_eca_agents\TypedData;

use Drupal\ai_integration_eca_agents\EcaElementType;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\Event\EventInterface;

/**
 * Definition of the ECA Plugin data type.
 */
final class EcaPluginDefinition extends ComplexDataDefinitionBase {

  protected const PROP_LABEL = 'label';

  protected const PROP_SUCCESSORS = 'successors';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    $properties = [];

    $properties['element_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID of the element'))
      ->setRequired(TRUE)
      ->addConstraint('Regex', [
        'pattern' => '/^[\w]+$/',
        'message' => 'The %value ID is not valid.',
      ]);

    $properties['plugin_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Plugin ID'))
      ->setRequired(TRUE)
      ->addConstraint('PluginExists', [
        'manager' => $this->getPluginManagerId(),
        'interface' => $this->getPluginInterface(),
      ]);

    if (in_array(self::PROP_LABEL, $this->getEnabledProperties(), TRUE)) {
      $properties['label'] = DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Label'))
        ->setRequired(TRUE)
        ->addConstraint('Regex', [
          'pattern' => '/([^\PC\x09\x0a\x0d])/u',
          'match' => FALSE,
          'message' => 'Text is not allowed to contain control characters, only visible characters.',
        ]);
    }

    $properties['configuration'] = DataDefinition::create('any');

    if (in_array(self::PROP_SUCCESSORS, $this->getEnabledProperties(), TRUE)) {
      $properties['successors'] = ListDataDefinition::create('eca_successor')
        ->setLabel(new TranslatableMarkup('Successors'));
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'eca_plugin'): EcaPluginDefinition {
    return new EcaPluginDefinition(['type' => $type]);
  }

  /**
   * Get the plugin manager ID.
   *
   * @return string
   *   Returns the plugin manager ID.
   */
  protected function getPluginManagerId(): string {
    return match ($this->getSetting('data_type')) {
      EcaElementType::Action => 'plugin.manager.action',
      EcaElementType::Event => 'plugin.manager.eca.event',
      EcaElementType::Condition => 'plugin.manager.eca.condition',
      default => throw new \InvalidArgumentException(t('Could not match data type @type to plugin manager ID.', [
        '@type' => $this->getSetting('data_type'),
      ])),
    };
  }

  /**
   * Get the plugin interface.
   *
   * @return string
   *   Returns the plugin interface.
   */
  protected function getPluginInterface(): string {
    return match ($this->getSetting('data_type')) {
      EcaElementType::Action => ActionInterface::class,
      EcaElementType::Event => EventInterface::class,
      EcaElementType::Condition => ConditionInterface::class,
      default => throw new \InvalidArgumentException(t('Could not match data type @type to plugin interface.', [
        '@type' => $this->getSetting('data_type'),
      ])),
    };
  }

  /**
   * Get a list of enabled properties.
   *
   * @return string[]
   *   The list of enabled properties.
   */
  protected function getEnabledProperties(): array {
    $default = [
      self::PROP_LABEL,
      self::PROP_SUCCESSORS,
    ];

    return match ($this->getSetting('data_type')) {
      EcaElementType::Condition => [],
      default => $default,
    };
  }

}
