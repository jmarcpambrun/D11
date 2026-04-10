<?php

namespace Drupal\drd\Plugin\AdvancedQueue\JobType;

/**
 * Provides an AdvancedQueue JobType for DRD globally.
 *
 * @AdvancedQueueJobType(
 *  id = "drd_action_global",
 *  label = @Translation("DRD Global Action"),
 * )
 */
class ActionGlobal extends Action {

  /**
   * {@inheritdoc}
   */
  public function processAction(): array|bool {
    /** @var \Drupal\drd\Plugin\Action\BaseGlobalInterface $action */
    $action = $this->action;

    return $this->actionManager->executeAction($action);
  }

}
