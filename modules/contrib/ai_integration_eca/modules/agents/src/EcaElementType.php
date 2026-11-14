<?php

namespace Drupal\ai_integration_eca_agents;

/**
 * Enum of the element types.
 */
enum EcaElementType: string {

  case Event = 'event';
  case Condition = 'condition';
  case Action = 'action';
  case Gateway = 'gateway';

  /**
   * Get the plural form of a type.
   *
   * @return string
   *   Returns the plural form of a type.
   */
  public function getPlural(): string {
    return match ($this) {
      self::Event => 'events',
      self::Condition => 'conditions',
      self::Action => 'actions',
      self::Gateway => 'gateways',
    };
  }

  /**
   * Returns a list of types, keyed by their plural form.
   *
   * @return \Drupal\ai_integration_eca_agents\EcaElementType[]
   *   The list of types, keyed by their plural form.
   */
  public static function getPluralMap(): array {
    return [
      self::Event->getPlural() => self::Event,
      self::Condition->getPlural() => self::Condition,
      self::Action->getPlural() => self::Action,
      self::Gateway->getPlural() => self::Gateway,
    ];
  }

}
