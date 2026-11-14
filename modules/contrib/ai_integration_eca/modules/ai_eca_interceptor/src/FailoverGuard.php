<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor;

/**
 * Tracks which AI backup targets are currently being attempted.
 *
 * The FailoverToProviderAction plugin performs its
 * backup provider call synchronously inside execute(). That call dispatches its
 * own AiExceptionEvent when it fails, which would re-trigger the very same
 * failover — so the action has to know which targets are already in flight.
 *
 * The state cannot live on the action plugin: ECA creates a fresh plugin
 * instance for every successor resolution (see
 * \Drupal\eca\Entity\Eca::createInstance()), so an instance property would be
 * empty on the nested run. A `private static` property has the opposite
 * problem: every instance shares it, so any instance can clobber a guard that
 * another instance is still relying on.
 *
 * Holding the state in a service is ECA's canonical answer to exactly this
 * problem — see \Drupal\eca_user\AccountSwitcher, which keeps a stack in a
 * service that its action plugin injects.
 *
 * Targets are reference counted rather than flagged, so the same backup target
 * can be attempted at nested levels (for example when recovering both the
 * primary call and, separately, a guardrail classifier call made while the
 * backup runs) and released correctly.
 *
 * @see \Drupal\ai_eca_interceptor\Plugin\Action\FailoverToProviderAction
 * @see \Drupal\eca_user\AccountSwitcher
 */
class FailoverGuard {

  /**
   * Reference count per backup target key.
   *
   * @var array<string, int>
   */
  protected array $active = [];

  /**
   * Marks a backup target as being attempted on the current call stack.
   *
   * @param string $key
   *   The target key, conventionally "provider_id:model_id".
   */
  public function enter(string $key): void {
    $this->active[$key] = ($this->active[$key] ?? 0) + 1;
  }

  /**
   * Releases a backup target previously marked by ::enter().
   *
   * Decrements the reference count and forgets the target once the count
   * reaches zero, so nested attempts on the same target unwind one frame at a
   * time instead of clearing the whole guard.
   *
   * @param string $key
   *   The target key that was passed to ::enter().
   */
  public function release(string $key): void {
    if (isset($this->active[$key]) && --$this->active[$key] <= 0) {
      unset($this->active[$key]);
    }
  }

  /**
   * Checks whether a backup target is currently being attempted.
   *
   * @param string $key
   *   The target key, conventionally "provider_id:model_id".
   *
   * @return bool
   *   TRUE when the target is in flight, FALSE otherwise.
   */
  public function isActive(string $key): bool {
    return isset($this->active[$key]);
  }

}
