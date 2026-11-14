<?php

namespace Drupal\ai_integration_eca_agents;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai\Exception\AiFunctionCallingExecutionError;

/**
 * Shared access check for the tools that expose ECA to an agent.
 */
trait EcaAccessTrait {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Determines if the current user is allowed to work with ECA models.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  protected function hasEcaAccess(): bool {
    foreach ([
      'administer eca',
      'modeler api administer eca',
      'modeler api edit eca',
    ] as $permission) {
      if ($this->currentUser->hasPermission($permission)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Throws when the current user is not allowed to work with ECA models.
   *
   * @throws \Drupal\ai\Exception\AiFunctionCallingExecutionError
   */
  protected function checkEcaAccess(): void {
    if (!$this->hasEcaAccess()) {
      throw new AiFunctionCallingExecutionError('The current user does not have permission to work with ECA models.');
    }
  }

}
