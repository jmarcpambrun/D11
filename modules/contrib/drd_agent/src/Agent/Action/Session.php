<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\user\Entity\User;
use Drupal\user\OneTimeAuthentication;

/**
 * Provides a 'Session' code.
 */
class Session extends Base {

  /**
   * {@inheritdoc}
   */
  public function execute(): array {
    /** @var \Drupal\user\UserInterface $account */
    $account = User::load(1);
    if ($this->container->has(OneTimeAuthentication::class)) {
      $url = $this->container->get(OneTimeAuthentication::class)->generateOneTimeLoginUrl($account)->toString();
    }
    else {
      // Fallback for Drupal core before 11.4.0, which does not provide the
      // OneTimeAuthentication service yet.
      // @phpstan-ignore function.deprecated
      $url = user_pass_reset_url($account);
    }
    return [
      'url' => $url . '/login?destination=/admin',
    ];
  }

}
