<?php

namespace Drupal\eca;

use Drupal\eca\Token\Browser;

/**
 * Next generation debugger.
 */
class ProcessDebugger {

  /**
   * Whether the debugger is enabled.
   *
   * @var bool
   */
  public static bool $debug = FALSE;

  /**
   * Whether the debugger has been started.
   *
   * @var bool
   */
  protected bool $started = FALSE;

  /**
   * The label of the ECA model.
   *
   * @var string
   */
  protected string $ecaLabel = '';

  /**
   * The label of the event being processed.
   *
   * @var string
   */
  protected string $eventLabel = '';

  /**
   * The history of debug entries for this process.
   *
   * @var array
   */
  protected array $history = [];

  /**
   * The event name of this process.
   *
   * @var string
   */
  protected string $eventName;

  /**
   * Constructs a debugger object.
   *
   * @param \Drupal\eca\Token\Browser $tokenBrowser
   *   The token browser.
   * @param string $ecaId
   *   The ID of the ECA model.
   * @param string $eventId
   *   The ID of the event being processed.
   */
  public function __construct(
    protected Browser $tokenBrowser,
    protected string $ecaId,
    protected string $eventId,
  ) {}

  /**
   * Checks if the debugger has been started.
   *
   * @return bool
   *   TRUE if the debugger has been started, FALSE otherwise.
   */
  public function isStarted(): bool {
    return $this->started;
  }

  /**
   * Gets the ECA model ID.
   *
   * @return string
   *   The ECA model ID.
   */
  public function getEcaId(): string {
    return $this->ecaId;
  }

  /**
   * Gets the event ID.
   *
   * @return string
   *   The event ID.
   */
  public function getEventId(): string {
    return $this->eventId;
  }

  /**
   * Gets the ECA model label.
   *
   * @return string
   *   The ECA model label.
   */
  public function getEcaLabel(): string {
    return $this->ecaLabel;
  }

  /**
   * Gets the event label.
   *
   * @return string
   *   The event label.
   */
  public function getEventLabel(): string {
    return $this->eventLabel;
  }

  /**
   * Gets the MD5 hash of the debug history.
   *
   * Stores the history in Drupal's temp store for later retrieval.
   *
   * @return string
   *   The MD5 hash of the JSON-encoded history array.
   */
  public function getHistoryHash(): string {
    return $this->tokenBrowser->storeHistory($this->history);
  }

  /**
   * Stores the history data in Drupal's shared temp store by event.
   */
  public function storeHistoryByEvent(): void {
    if (!self::$debug) {
      return;
    }
    $this->tokenBrowser->storeHistory([
      'model_id' => $this->ecaId,
      'component_id' => $this->eventId,
      'history' => $this->history,
    ], implode('::', [$this->ecaId, $this->eventId]));
  }

  /**
   * Sets the ECA model label.
   *
   * @param string $label
   *   The ECA model label.
   */
  public function setEcaLabel(string $label): void {
    $this->ecaLabel = $label;
  }

  /**
   * Sets the event label.
   *
   * @param string $label
   *   The event label.
   */
  public function setEventLabel(string $label): void {
    $this->eventLabel = $label;
  }

  /**
   * Records that the event does not apply to the ECA model configuration.
   */
  public function doesNotApply(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'does not apply'];
  }

  /**
   * Records that the ECA model or the event in the model does not exist.
   */
  public function doesNotExist(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'does not exist'];
  }

  /**
   * Records that the event does not execute.
   */
  public function doesNotExecute(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'does not execute'];
  }

  /**
   * Records that recursion has been detected during execution.
   */
  public function recursionDetected(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'recursion detected'];
  }

  /**
   * Records that the event of this debugger has started.
   *
   * Initializes the token services and records the starting state including
   * current token data.
   *
   * @param string $eventName
   *   The event name.
   */
  public function started(string $eventName): void {
    $this->started = TRUE;
    $this->eventName = $eventName;
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'started',
      'id' => $this->eventId,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records that a successor has been added to the execution queue.
   *
   * @param string $id
   *   The ID of the current component.
   * @param string $successorId
   *   The ID of the successor being added.
   * @param string|bool|null $conditionId
   *   The ID of the condition that caused the successor to be added.
   * @param int $type
   *   The type of the successor, action or gateway.
   */
  public function addSuccessor(string $id, string $successorId, string|bool|null $conditionId, int $type): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'add successor',
      'id' => $id,
      'successorId' => $successorId,
      'conditionId' => $conditionId,
      'successorType' => $type,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records that a successor has been ignored during execution.
   *
   * This happens when the condition attached to the connection to the
   * successor doesn't return TRUE.
   *
   * @param string $id
   *   The ID of the current component.
   * @param string $successorId
   *   The ID of the successor being ignored.
   * @param string|bool|null $conditionId
   *   The ID of the condition that caused the successor to be ignored.
   * @param int $type
   *   The type of the successor, action or gateway.
   */
  public function ignoreSuccessor(string $id, string $successorId, string|bool|null $conditionId, int $type): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'ignore successor',
      'id' => $id,
      'successorId' => $successorId,
      'conditionId' => $conditionId,
      'successorType' => $type,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records that access was denied for a specific action.
   *
   * @param string $id
   *   The ID of the action that was denied access.
   */
  public function accessDenied(string $id): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'access denied',
      'id' => $id,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records the execution of an action.
   *
   * @param string $id
   *   The ID of the action being executed.
   * @param mixed $object
   *   The object being passed to the action for execution.
   */
  public function execute(string $id, mixed $object): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'execute',
      'id' => $id,
      'object' => $object,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records an exception that occurred during execution.
   *
   * @param string $id
   *   The ID of the action where the exception occurred.
   * @param mixed $object
   *   The object being executed when the exception occurred.
   * @param \Exception $ex
   *   The exception that was thrown.
   */
  public function exception(string $id, mixed $object, \Exception $ex): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'execute',
      'id' => $id,
      'object' => $object,
      'exception' => $ex,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Gets the current token data.
   *
   * @return array
   *   The token data array, normalized to a maximum depth set in state.
   */
  protected function getTokenData(): array {
    return $this->tokenBrowser->normalizedTokenData($this->eventName);
  }

}
