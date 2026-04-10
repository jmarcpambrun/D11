<?php

namespace Drupal\drd_eca\Plugin\ECA\Event;

use Drupal\drd\Event\DrdActionFinish;
use Drupal\drd\Event\DrdActionStart;
use Drupal\drd\Event\DrdBase;
use Drupal\drd\Event\DrdEvents;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Plugin\ECA\Event\EventBase;

/**
 * Plugin implementation of the ECA Events drom DRD.
 *
 * @EcaEvent(
 *   id = "drd",
 *   deriver = "Drupal\drd_eca\Plugin\ECA\Event\DrdEventDeriver",
 *   eca_version_introduced = "4.1.0"
 * )
 */
class DrdEvent extends EventBase {

  /**
   * Return the actions of the plugin.
   *
   * @return array[]
   *   The actions of the plugin.
   */
  public static function definitions(): array {
    return [
      'drd_eca_action_started' => [
        'label' => 'DRD: Action started',
        'event_name' => DrdEvents::ACTION_STARTED,
        'event_class' => DrdActionStart::class,
      ],
      'drd_eca_action_finished' => [
        'label' => 'DRD: Action finished',
        'event_name' => DrdEvents::ACTION_FINISHED,
        'event_class' => DrdActionFinish::class,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      DrdBase::class,
    ],
    properties: [
      new Token(
        name: 'drd_action_id',
        description: 'The ID of the DRD action.',
        classes: [
          DrdBase::class,
        ],
      ),
      new Token(
        name: 'entity',
        description: 'The entity the action deals with.',
        classes: [
          DrdBase::class,
        ],
      ),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];
    if ($event instanceof DrdBase) {
      $action = $event->getAction();
      $data += [
        'drd_action_id' => $action->getPluginId(),
      ];
      $entity = $event->getEntity();
      if ($entity !== NULL) {
        $data += [
          'entity' => $entity,
        ];
      }
    }
    $data += parent::buildEventData();
    return $data;
  }

}
