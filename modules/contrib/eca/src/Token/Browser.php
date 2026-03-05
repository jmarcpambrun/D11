<?php

namespace Drupal\eca\Token;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attribute\Token;
use Drupal\eca\EcaEvents;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\PluginManager\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides services for normalizing and browsing tokens.
 */
class Browser {

  /**
   * The private temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $privateTempStore;

  /**
   * The shared temp store.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected SharedTempStore $sharedTempStore;

  /**
   * The main request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The token info.
   *
   * @var array|null
   */
  protected ?array $tokenInfo = NULL;

  /**
   * Making sure that we don't run this twice.
   *
   * @var bool
   */
  protected bool $isRunning = FALSE;

  /**
   * The event classes indexed by event name.
   *
   * @var array
   */
  protected array $eventClasses = [];

  /**
   * List of normalized values plus a hash of the original value.
   *
   * @var array
   */
  protected array $processedValues = [];

  /**
   * Max depth for token data.
   *
   * @var int
   */
  protected int $depth;

  /**
   * Constructs the browser object.
   */
  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected TokenServices $token,
    protected Event $eventPluginManager,
    protected PrivateTempStoreFactory $privateTempStoreFactory,
    protected SharedTempStoreFactory $sharedTempStoreFactory,
    protected AccountProxyInterface $currentUser,
    protected RequestStack $requestStack,
    protected TimeInterface $time,
    protected StateInterface $state,
  ) {
    $this->privateTempStore = $privateTempStoreFactory->get('eca_process_debugger');
    $this->sharedTempStore = $sharedTempStoreFactory->get('eca_process_debugger');
    $this->request = $requestStack->getCurrentRequest();
    $this->depth = $this->state->get('_eca_internal_debug_data_depth', 3) ?? 3;
  }

  /**
   * Store history data for debugging purposes.
   *
   * @param array $history
   *   The history data.
   * @param string|null $event
   *   The event or NULL. If no event is given, the data gets hashed
   *   and stored privately for the current user. If the event is
   *   provided, the data is stored together with request data in the shared
   *   temp store.
   *
   * @return string|null
   *   The hash or NULL.
   */
  public function storeHistory(array $history, ?string $event = NULL): ?string {
    if ($event === NULL) {
      $hash = md5(json_encode($history));
      try {
        $this->privateTempStore->set($hash, $history);
      }
      catch (\Exception) {
        // Silently fail if temp store is not available.
        // This prevents breaking the debugger if temp store has issues.
      }
      return $hash;
    }
    $testingKey = 'testing::' . $event;
    $testingData = $this->sharedTempStore->get($testingKey);
    if (is_string($testingData)) {
      $this->sharedTempStore->set($testingKey, $history);
    }
    $history += [
      'timestamp' => $this->time->getRequestTime(),
      'user' => [
        'uid' => $this->currentUser->id(),
        'name' => $this->currentUser->getAccountName(),
      ],
      'ip' => $this->request->getClientIp(),
      'url' => $this->request->getRequestUri(),
    ];
    $data = $this->getHistoryByEvent($event);
    $data[] = $history;
    // Only store the 10 latest cases of that event.
    if (count($data) > 10) {
      $data = array_slice($data, -10);
    }
    try {
      $this->sharedTempStore->set($event, $data);
    }
    catch (\Exception) {
      // Silently fail if temp store is not available.
      // This prevents breaking the debugger if temp store has issues.
    }
    return NULL;
  }

  /**
   * Get the history data by hash.
   *
   * @param string $hash
   *   The hash.
   *
   * @return array
   *   The history data.
   */
  public function getHistoryByHash(string $hash): array {
    return $this->privateTempStore->get($hash) ?? [];
  }

  /**
   * Get the history data by event.
   *
   * @param string $event
   *   The event.
   *
   * @return array
   *   The history data.
   */
  public function getHistoryByEvent(string $event): array {
    return $this->sharedTempStore->get($event) ?? [];
  }

  /**
   * Initializes the testing mode for an event.
   *
   * @param string $event
   *   The event.
   *
   * @return string
   *   The job ID.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function initTesting(string $event): string {
    $jobId = (new Random())->string();
    $this->sharedTempStore->set('testing::' . $event, $jobId);
    $this->sharedTempStore->set('jobid::testing::' . $jobId, $event);
    return $jobId;
  }

  /**
   * Polls the testing mode for the history data.
   *
   * @param string $jobId
   *   The job ID.
   *
   * @return array|null
   *   The history data, if the test has been completed. NULL otherwise.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function pollTesting(string $jobId): ?array {
    $event = $this->sharedTempStore->get('jobid::testing::' . $jobId);
    $data = $this->sharedTempStore->get('testing::' . $event);
    if ($data !== $jobId && is_array($data)) {
      $this->sharedTempStore->delete('testing::' . $event);
      $this->sharedTempStore->delete('jobid::testing::' . $jobId);
      return $data['history'] ?? [];
    }
    return NULL;
  }

  /**
   * Normalizes all current token data.
   *
   * @param string $eventName
   *   The event name.
   *
   * @return array
   *   The normalized token data.
   */
  public function normalizedTokenData(string $eventName): array {
    if ($this->isRunning) {
      return [];
    }
    $this->isRunning = TRUE;
    if ($this->tokenInfo === NULL) {
      $this->tokenInfo = $this->token->getInfo();
    }
    $data = $this->token->getTokenData();
    if (!isset($this->eventClasses[$eventName])) {
      $eventPluginId = $this->eventPluginManager->getPluginIdForSystemEvent($eventName);
      try {
        $definition = $this->eventPluginManager->getDefinition($eventPluginId);
      }
      catch (PluginNotFoundException) {
        $definition = [
          'class' => $eventName,
          'event_class' => self::class,
        ];
      }
      $this->eventClasses[$eventName] = [
        'class' => $definition['class'],
        'eventClass' => $definition['event_class'],
      ];
    }
    /** @var \Drupal\eca\Attribute\Token $supportedToken */
    foreach ($this->getSupportedTokens($this->eventClasses[$eventName]['class'], $this->eventClasses[$eventName]['eventClass']) as $supportedToken) {
      // Make sure this is not overriding an existing token. Try to find an
      // alias, or ignore the token if none is found.
      $key = $supportedToken->name;
      if (isset($data[$key])) {
        $key = NULL;
        foreach ($supportedToken->aliases as $alias) {
          if (!isset($data[$alias])) {
            $key = $alias;
            break;
          }
        }
        if ($key === NULL) {
          continue;
        }
      }
      // Only add the token if it actually exists. An example of a declared but
      // often not existing token is the session_user.
      if ($this->token->hasTokenData($key)) {
        $data[$key] = $supportedToken;
      }
    }
    $normalizedData = [];
    foreach ($data as $key => $value) {
      if (!isset($this->processedValues[$key]) || $this->processedValues[$key]['hash'] !== md5(serialize($value))) {
        $this->processedValues[$key] = [
          'data' => $this->normalizeValue($key, $value),
          'hash' => md5(serialize($value)),
        ];
      }
      $normalizedData[$key] = $this->processedValues[$key]['data'];
    }
    uasort($normalizedData, static fn(array $a, array $b) => strnatcasecmp($a['label'], $b['label']));
    $this->isRunning = FALSE;
    return $normalizedData;
  }

  /**
   * Normalizes a single token value.
   *
   * @param string $token
   *   The token key.
   * @param mixed $value
   *   The token value.
   * @param int $depth
   *   The current recursion depth.
   *
   * @return array
   *   The normalized data for this value.
   */
  protected function normalizeValue(string $token, mixed $value, int $depth = 1): array {
    $keyParts = explode(':', $token);
    $key = end($keyParts);
    $normalized = [
      'label' => ucwords(str_replace(['_', '-'], ' ', $key)),
      'token' => $token,
    ];
    if ($value instanceof EntityAdapter) {
      $value = $value->getEntity();
    }
    elseif ($value instanceof DataTransferObject) {
      try {
        $properties = $value->getProperties(TRUE);
        if (!empty($properties)) {
          $value = $properties;
        }
      }
      catch (MissingDataException) {
        // Ignore for now.
      }
    }
    elseif ($value instanceof Token && $value->type !== '') {
      $type = $value->type;
      $value = $this->token->getTokenData($key);
      if ($value instanceof ContentEntityInterface) {
        $key = $value->getEntityTypeId();
      }
      else {
        $key = $type;
      }
    }

    if (isset($this->tokenInfo['types'][$key])) {
      $dataNeeded = $this->tokenInfo['types'][$key]['needs-data'] ?? NULL;
      if ($dataNeeded && isset($this->tokenInfo['tokens'][$dataNeeded])) {
        $normalized['data'] = $this->normalizeRecursive($token, $dataNeeded, [$key => $value]);
      }
    }
    elseif (is_scalar($value)) {
      $normalized['value'] = $value;
    }
    elseif (is_array($value)) {
      if ($depth < $this->depth) {
        $normalized['data'] = [];
        foreach ($value as $subKey => $subValue) {
          $normalized['data'][$subKey] = $this->normalizeValue($token . ':' . $subKey, $subValue, $depth + 1);
        }
      }
    }
    elseif ($value instanceof Token) {
      if ($value->properties) {
        $normalized['data'] = [];
        foreach ($value->properties as $property) {
          $normalized['data'][$property->name] = $this->normalizeValue($token . ':' . $property->name, $property, $depth + 1);
        }
      }
      else {
        $normalized['value'] = $this->token->replaceClear('[' . $token . ']');
      }
    }
    elseif ($value instanceof PrimitiveInterface) {
      $normalized['value'] = $value->getValue();
    }
    elseif ($value instanceof ContentEntityInterface) {
      $entityTypeId = $value->getEntityTypeId();
      $normalized['data'] = $this->normalizeRecursive($token, $entityTypeId, [$entityTypeId => $value]);
    }
    elseif ($value instanceof DataTransferObject) {
      // For complex DTOs, weÄve already converted its properties to an
      // array above.
      $normalized['value'] = $value->getString();
    }
    elseif ($value instanceof TypedDataInterface) {
      $normalized['value'] = $value->getDataDefinition()->getDataType();
    }
    else {
      $normalized['value'] = is_object($value) ?
        '[Object: ' . get_class($value) . ']' :
        '[Unknown type: ' . gettype($value) . ']';
    }
    return $normalized;
  }

  /**
   * Recursively normalizes token data.
   *
   * @param string $tokenPath
   *   The current token path (e.g. 'node').
   * @param string $tokenType
   *   The token type to normalize (e.g. 'node').
   * @param array $data
   *   The data for token replacement.
   * @param int $depth
   *   The current recursion depth.
   *
   * @return array
   *   The normalized data for this level.
   */
  protected function normalizeRecursive(string $tokenPath, string $tokenType, array $data, int $depth = 1): array {
    $normalized = [];
    if ($depth > $this->depth || !isset($this->tokenInfo['tokens'][$tokenType])) {
      return $normalized;
    }

    foreach ($this->tokenInfo['tokens'][$tokenType] as $dataKey => $dataValue) {
      $currentTokenPath = $tokenPath . ':' . $dataKey;
      $normalized[$dataKey] = [
        'label' => $dataValue['name'],
        'token' => $currentTokenPath,
      ];

      $value = reset($data);
      if ($depth === 1 && ($dataValue['type'] ?? '') === 'url' && $value instanceof ContentEntityInterface && $value->isNew()) {
        // On the top level, we could have a new entity which doesn't have an ID
        // yet, and hence there wouldn't be a URL either.
        $normalized[$dataKey]['value'] = '';
      }
      elseif (isset($dataValue['type']) && isset($this->tokenInfo['types'][$dataValue['type']])) {
        // If we have a subsequent level, we process that recursively and
        // don't replace the token itself, because that could be very
        // expensive, i.e. if the current token is an entity that would be
        // fully rendered when used in token replacement.
        $nestedData = $this->normalizeRecursive($currentTokenPath, $dataValue['type'], $data, $depth + 1);
        if (!empty($nestedData)) {
          $normalized[$dataKey]['data'] = $nestedData;
        }
      }
      else {
        try {
          $normalized[$dataKey]['value'] = $this->token->replaceClear('[' . $currentTokenPath . ']', $data);
        }
        catch (\Throwable) {
          // Ignore exceptions during token replacement.
        }
      }
    }
    return $normalized;
  }

  /**
   * Helper function to get token info.
   */
  public function getSupportedTokens(string $class, string $eventClass): array {
    // @todo Make sure that all possible sources have the token attributes
    //   defined.
    $sources = $this->eventDispatcher->getListeners(EcaEvents::BEFORE_INITIAL_EXECUTION);
    array_unshift($sources, [$class, 'buildEventData']);
    array_unshift($sources, [$class, 'getData']);
    foreach ($this->token->getDataProviders() as $dataProvider) {
      array_unshift($sources, [$dataProvider::class, 'buildEventData']);
      array_unshift($sources, [$dataProvider::class, 'getData']);
    }

    $tokens = [];
    foreach ($sources as $source) {
      if (!is_array($source)) {
        // Some tests define a listener as a closure, which is not an array.
        // Those can be ignored.
        continue;
      }
      [$class, $methodName] = $source;
      try {
        $reflection = new \ReflectionMethod($class, $methodName);
      }
      catch (\ReflectionException) {
        continue;
      }
      do {
        foreach ($reflection->getAttributes() as $attribute) {
          if ($attribute->getName() === 'Drupal\eca\Attribute\Token') {
            /** @var \Drupal\eca\Attribute\Token $token */
            $token = $attribute->newInstance();
            if ($this->getSupportedProperties($eventClass, $token)) {
              $tokens[] = $token;
            }
          }
        }
        try {
          $reflection = $reflection->getPrototype();
        }
        catch (\ReflectionException) {
          $reflection = NULL;
        }
      } while ($reflection !== NULL);
    }
    return $tokens;
  }

  /**
   * Recursive helper function to get token property info.
   *
   * @param string $eventClass
   *   The event class.
   * @param \Drupal\eca\Attribute\Token $token
   *   The token.
   *
   * @return bool
   *   TRUE, if the token has any attributes, FALSE otherwise.
   */
  private function getSupportedProperties(string $eventClass, Token $token): bool {
    if ($this->isClassSupported($eventClass, $token->classes)) {
      $properties = [];
      foreach ($token->properties as $property) {
        if ($this->getSupportedProperties($eventClass, $property)) {
          $properties[] = $property;
        }
      }
      $token->properties = $properties;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determines if the event class is covered by the list of classes.
   *
   * @param string $eventClass
   *   The event class.
   * @param array $classes
   *   The list of classes.
   *
   * @return bool
   *   TRUE, if either the list of classes if empty, or if the event class is
   *   an instance of one of the given classes; FALSE otherwise.
   */
  private function isClassSupported(string $eventClass, array $classes): bool {
    if (empty($classes)) {
      return TRUE;
    }
    foreach ($classes as $class) {
      if (is_a($eventClass, $class, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
