<?php

namespace Drupal\ai_integration_eca_agents\Plugin\Menu\LocalAction;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Defines a local action plugin with the needed dialog attributes.
 */
class AiEcaAgentsDialogLocalAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);

    $attributes = [
      'class' => ['use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode([
        'width' => 1000,
        'dialogClass' => 'ui-dialog-off-canvas',
      ]),
    ];
    $options['attributes'] = $this->pluginDefinition['attributes'] ?? [];
    $options['attributes'] = NestedArray::mergeDeep($options['attributes'], $attributes);

    return $options;
  }

}
