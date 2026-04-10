<?php

namespace Drupal\drd_agent\Agent\Remote;

/**
 * Implements the Monitoring class.
 */
class Monitoring extends Base {

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $review = [];

    if ($this->moduleHandler->moduleExists('monitoring')) {
      foreach (monitoring_sensor_run_multiple() as $result) {
        $review[$result->getSensorId()] = $result->toArray();
        $review[$result->getSensorId()]['label'] = $result->getSensorConfig()->getLabel();
      }
    }

    return $review;
  }

}
