<?php

namespace Drupal\drd_agent\Agent\Remote;

use Drupal\Core\Session\UserSession;
use Drupal\security_review\Controller\ChecklistController;

/**
 * Implements the SecurityReview class.
 */
class SecurityReview extends Base {

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $review = [];

    if ($this->moduleHandler->moduleExists('security_review')) {
      /** @var \Drupal\security_review\SecurityReview $security_review */
      $security_review = $this->container->get('security_review');

      // Only check once per day.
      if ($this->time->getRequestTime() - $security_review->getLastRun() > 86400) {
        /** @var \Drupal\Core\Session\AccountSwitcherInterface $switcher */
        $switcher = $this->container->get('account_switcher');
        $switcher->switchTo(new UserSession(['uid' => 1]));

        /** @var \Drupal\security_review\SecurityCheckPluginManager $pluginManager */
        $pluginManager = $this->container->get('plugin.manager.security_review.security_check');
        $security_review->runChecks($pluginManager->getChecks());
        $security_review->setLastRun(time());

        $switcher->switchBack();
      }

      $clc = ChecklistController::create($this->container);
      $review['security_review'] = [
        'title' => t('Security Review'),
        'result' => $clc->results(),
      ];

    }

    return $review;
  }

}
