<?php

namespace Drupal\burndown_time_tracker\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enforces hours-only work units for Burndown time entry endpoints.
 */
class HoursOnlyWorkUnitSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  /**
   * Normalizes work unit to hours for relevant Burndown POST endpoints.
   */
  public function onRequest(RequestEvent $event) : void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    if ($request->getMethod() !== 'POST') {
      return;
    }

    $route_name = (string) $request->attributes->get('_route', '');
    if (!in_array($route_name, ['burndown.task_add_work', 'burndown.task_edit_log'], TRUE)) {
      return;
    }

    if ($route_name === 'burndown.task_edit_log' && !$request->request->has('work')) {
      return;
    }

    $request->request->set('work_increment', 'h');
  }

}
