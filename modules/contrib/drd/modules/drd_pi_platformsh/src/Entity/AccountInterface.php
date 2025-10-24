<?php

namespace Drupal\drd_pi_platformsh\Entity;

/**
 * Provides an interface for defining Account entities.
 */
interface AccountInterface {

  /**
   * API token of this account.
   *
   * @return string|null
   *   API token.
   */
  public function getApiToken(): ?string;

  /**
   * Set the API token of this account.
   *
   * @param string $apiToken
   *   API token.
   *
   * @return $this
   */
  public function setApiToken(string $apiToken): self;

}
