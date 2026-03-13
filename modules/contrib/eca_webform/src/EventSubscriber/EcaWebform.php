<?php

namespace Drupal\eca_webform\EventSubscriber;

use Drupal\eca\EcaEvents;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\EventSubscriber\EcaExecutionSubscriberBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca_webform\Event\AccessRules;
use Drupal\eca_webform\Event\ElementInputMasks;
use Drupal\eca_webform\Event\HelpInfo;
use Drupal\eca_webform\Event\WebformEventInterface;

/**
 * ECA event subscriber.
 */
class EcaWebform extends EcaExecutionSubscriberBase {

  /**
   * Subscriber method after initial execution.
   *
   * @param \Drupal\eca\Event\AfterInitialExecutionEvent $after_event
   *   The according event.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function onAfterInitialExecution(AfterInitialExecutionEvent $after_event): void {
    $event = $after_event->getEvent();
    if ($event instanceof WebformEventInterface) {
      $dto = $this->tokenService->getTokenData('webform');
      if ($dto instanceof DataTransferObject) {
        $data = $dto->toArray();
        if ($event instanceof AccessRules) {
          $event->setAccessRules($data['access_rules']);
        }
        elseif ($event instanceof ElementInputMasks) {
          $event->setInputMasks($data['input_masks']);
        }
        elseif ($event instanceof HelpInfo) {
          $event->setHelpInfo($data['help_info']);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[EcaEvents::AFTER_INITIAL_EXECUTION][] = [
      'onAfterInitialExecution',
      100,
    ];
    return $events;
  }

}
