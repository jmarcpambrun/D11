<?php

namespace Drupal\event_scheduler\Event;

interface EventCommonInterface {

  /**
   * @return string
   */
  public function getName(): string;

}