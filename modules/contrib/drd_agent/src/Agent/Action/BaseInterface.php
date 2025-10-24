<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\drd_agent\Crypt\BaseMethodInterface;

/**
 * Defines an interface for Remote DRD Action Code.
 */
interface BaseInterface {

  /**
   * Inits the DRD action.
   *
   * @param \Drupal\drd_agent\Crypt\BaseMethodInterface $crypt
   *   The base method.
   * @param array $arguments
   *   The arguments.
   * @param bool $debugMode
   *   The debug mode.
   */
  public function init(BaseMethodInterface $crypt, array $arguments, bool $debugMode): void;

  /**
   * Change current session to user 1.
   *
   * @return $this
   */
  public function promoteUser(): self;

  /**
   * Get authorised Crypt object or FALSE if none is available.
   *
   * @param string $uuid
   *   UUID of the crypt instance that should be loaded.
   *
   * @return \Drupal\drd_agent\Crypt\BaseMethodInterface|bool
   *   The loaded Crypt instance if available or FALSE otherwise.
   */
  public function getCryptInstance(string $uuid): BaseMethodInterface|bool;

  /**
   * Authorize the DRD instance, all validations have passed successfully.
   *
   * @param string $remoteSetupToken
   *   The token including settings.
   *
   * @return $this
   */
  public function authorize(string $remoteSetupToken): self;

  /**
   * Get an array of database connection information.
   *
   * @return array
   *   The database connection information.
   */
  public function getDbInfo(): array;

  /**
   * Get the arguments for this request.
   *
   * @return array
   *   Normalised array of all arguments received with the request.
   */
  public function getArguments(): array;

  /**
   * Get the debug mode.
   *
   * @return bool
   *   TRUE if debug mode is active, FALSE otherwise.
   */
  public function getDebugMode(): bool;

  /**
   * Set the debug mode.
   *
   * @param bool $debugMode
   *   TRUE if active, FALSE otherwise.
   *
   * @return $this
   */
  public function setDebugMode(bool $debugMode): self;

  /**
   * Logging if in debug mode.
   *
   * @param string $message
   *   Message of the watchdog report.
   * @param array $variables
   *   Parameters for the watchdog report.
   * @param int $severity
   *   Severity of the watchdog report.
   * @param string|null $link
   *   Optional link associated with the watchdog report.
   *
   * @return $this
   *   Itself.
   */
  public function watchdog(string $message, array $variables = [], int $severity = 5, ?string $link = NULL): self;

  /**
   * Validate a one-time-token.
   *
   * @param string $ott
   *   Token to be validated.
   * @param string $remoteSetupToken
   *   Base64 encoded RemoteSetupToken from DRD.
   *
   * @return bool
   *   TRUE if token is valid and configuration succeeded, FALSE otherwise.
   */
  public function ott(string $ott, string $remoteSetupToken): bool;

  /**
   * Gets the real path.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   *   The real path.
   */
  public function realPath(string $path): string;

  /**
   * Get messages.
   *
   * @return array
   *   The messages.
   */
  public function getMessages(): array;

  /**
   * Execute an action.
   *
   * @return mixed
   *   The response of the action as an array which will be encrypted before
   *   returned to DRD.
   */
  public function execute(): mixed;

}
