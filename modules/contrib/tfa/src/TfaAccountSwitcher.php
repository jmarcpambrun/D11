<?php

declare(strict_types=1);

namespace Drupal\tfa;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorate the AccountSwitcher to allow bypassing in TfaUserSetSubscriber.
 *
 * @internal
 *   This class is publicly called as a decorator for the 'account_switcher'
 *   service. The internal operations are subject to change at any time.
 */
final class TfaAccountSwitcher implements AccountSwitcherInterface {

  /**
   * Stack of previous UID's.
   *
   * @var int[]
   */
  protected array $uidStack = [];

  /**
   * Create a new TfaAccountSwitcher service.
   */
  public function __construct(
    #[AutowireDecorated] protected AccountSwitcherInterface $inner,
    protected AccountProxyInterface $currentUser,
    #[Autowire(service: 'cache.tfa_memcache')] protected CacheBackendInterface $memoryCache,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function switchTo(AccountInterface $account): self {
    $uid_before_switch = (int) $this->currentUser->id();
    $this->memoryCache->set('tfa_account_switcher_bypass', (int) $account->id());
    $this->inner->switchTo($account);
    $this->uidStack[] = $uid_before_switch;
    // Normally deleted by TfaUserSetSubscriber however we perform removal
    // as a failsafe should the inner service not switch the user.
    $this->memoryCache->delete('tfa_account_switcher_bypass');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function switchBack(): self {
    if (!empty($this->uidStack)) {
      $this->memoryCache->set('tfa_account_switcher_bypass', array_pop($this->uidStack));
    }
    $this->inner->switchBack();
    // Normally deleted by TfaUserSetSubscriber however we perform removal
    // as a failsafe should the inner service not switch the user.
    $this->memoryCache->delete('tfa_account_switcher_bypass');
    return $this;
  }

}
