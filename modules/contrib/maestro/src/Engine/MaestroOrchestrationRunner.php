<?php

declare(strict_types=1);

namespace Drupal\maestro\Engine;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\user\UserInterface;

/**
 * Runs the Maestro orchestrator under one lock and one identity.
 *
 * Every entry point that runs the orchestrator (the /orchestrator URL, task
 * console refreshes, process kickoff and Drush) goes through this service, so
 * they all lock, choose the account and clean the queue the same way.
 *
 * The account a run executes as is, in order of precedence:
 * - the account passed to ::run(), e.g. Drush's --user option;
 * - the "run as" user set in the Maestro engine settings;
 * - the current user, when neither is given.
 * Every task the run executes sees that account as the current user, so
 * permission checks, such as a Tool task's, are made against it.
 *
 * The account switch happens here and not in MaestroEngine::cleanQueue(): the
 * engine does not decide who it runs as, the caller of an orchestration does.
 */
class MaestroOrchestrationRunner {

  /**
   * The lock name shared by every orchestration run.
   */
  public const LOCK_NAME = 'maestro_orchestrator';

  /**
   * Constructs a MaestroOrchestrationRunner.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher, used to run as the run-as account.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, used to load the configured run-as user.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The Maestro logger channel.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LockBackendInterface $lock,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * Runs the orchestrator once.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account to run as. NULL uses the configured run-as user, or the
   *   current user when none is configured.
   *
   * @return \Drupal\maestro\Engine\OrchestrationOutcome
   *   Whether the run completed, was locked out, or was refused.
   */
  public function run(?AccountInterface $account = NULL): OrchestrationOutcome {
    $account ??= $this->getRunAsAccount();
    if ($account === FALSE) {
      return OrchestrationOutcome::AccountUnavailable;
    }
    // A blocked account must not act, whether it was configured or passed in.
    // The anonymous user entity reports blocked, but anonymous is a valid
    // identity to run as.
    if ($account instanceof UserInterface && !$account->isAnonymous() && $account->isBlocked()) {
      $this->logger->error('The orchestrator was not run: user "@user" is blocked.', ['@user' => $account->getAccountName()]);
      return OrchestrationOutcome::AccountUnavailable;
    }

    $config = $this->configFactory->get('maestro.settings');
    $lock_duration = (int) $config->get('maestro_orchestrator_lock_execution_time');
    if (!$this->lock->acquire(self::LOCK_NAME, $lock_duration > 0 ? $lock_duration : 30)) {
      return OrchestrationOutcome::Locked;
    }

    $switched = FALSE;
    try {
      if ($account !== NULL && (int) $account->id() !== (int) $this->currentUser->id()) {
        $this->accountSwitcher->switchTo($account);
        $switched = TRUE;
      }
      $engine = new MaestroEngine();
      if ($config->get('maestro_orchestrator_development_mode') == '1') {
        // Does a cache reset before loading entities during orchestration.
        $engine->enableDevelopmentMode();
      }
      $engine->cleanQueue();
    }
    finally {
      if ($switched) {
        $this->accountSwitcher->switchBack();
      }
      $this->lock->release(self::LOCK_NAME);
    }
    return OrchestrationOutcome::Completed;
  }

  /**
   * Loads the run-as user configured in the Maestro engine settings.
   *
   * @return \Drupal\user\UserInterface|false|null
   *   The configured user, NULL when none is configured, or FALSE when the
   *   configured user no longer exists. A missing user refuses the run rather
   *   than falling back to the current user, so tasks never run as an
   *   identity the site builder did not choose.
   */
  protected function getRunAsAccount(): UserInterface|false|null {
    $uid = $this->configFactory->get('maestro.settings')->get('maestro_orchestrator_run_as');
    if ($uid === NULL || $uid === '' || (int) $uid === 0) {
      return NULL;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user instanceof UserInterface) {
      $this->logger->error('The orchestrator was not run: the configured run-as user (uid @uid) does not exist.', ['@uid' => $uid]);
      return FALSE;
    }
    return $user;
  }

}
