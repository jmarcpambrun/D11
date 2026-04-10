<?php

namespace Drupal\drd_pi\Plugin\Action;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\drd\Plugin\Action\BaseGlobal;
use Drupal\drd_pi\DrdPiAccount;

/**
 * Provides a 'Sync' action.
 *
 * @Action(
 *  id = "drd_action_pi_sync",
 *  label = @Translation("Platform integration sync"),
 *  type = "drd",
 * )
 */
class Sync extends BaseGlobal {

  /**
   * Return a list of all configured accounts of this type.
   *
   * @return \Drupal\drd_pi\DrdPiAccountInterface[]
   *   List of accounts.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAccounts(): array {
    $accounts = [];
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($definition->entityClassImplements(DrdPiAccount::class)) {
        $storage = $this->entityTypeManager->getStorage($definition->id());
        /** @var \Drupal\drd_pi\DrdPiAccountInterface $account */
        foreach ($storage->loadMultiple() as $account) {
          if ($account->status()) {
            $accounts[] = $account;
          }
        }
      }
    }
    return $accounts;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(): mixed {
    try {
      foreach ($this->getAccounts() as $account) {
        $this->log('info', 'Syncing @platform account @label', [
          '@platform' => $account->getPlatformName(),
          '@label' => $account->label(),
        ]);
        $account->sync();
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // @todo Log this exception.
    }
    return TRUE;
  }

}
