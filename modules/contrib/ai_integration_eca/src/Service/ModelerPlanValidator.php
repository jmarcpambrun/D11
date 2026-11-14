<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Validates a plan of graph operations proposed by the assistant.
 *
 * The hard part is references. An operation has to be able to name a
 * component that does not exist yet, because the whole point of a plan is
 * "add this, then connect it to the thing you just added". The assistant
 * cannot know the ID the modeler will mint, so it declares its own token on
 * the operation that creates something and uses that token afterwards. Real
 * IDs from the canvas and tokens from earlier in the same plan are accepted
 * in the same fields, and the browser swaps the tokens for real IDs as it
 * executes.
 *
 * References are resolved strictly in order: an operation may only refer to
 * something the canvas already had or something an earlier operation in the
 * same plan created. A forward reference is a rejected plan, not a warning,
 * because it would fail halfway through execution.
 */
class ModelerPlanValidator implements ModelerPlanValidatorInterface {

  /**
   * The plan was not shaped like a list of operations this module knows.
   */
  public const REASON_SHAPE = 'shape';

  /**
   * The plan referred to something that will not exist when it runs.
   */
  public const REASON_REFERENCE = 'reference';

  /**
   * The plan used a plugin this site does not offer.
   */
  public const REASON_PLUGIN = 'plugin';

  /**
   * The plan was longer than a user could reasonably review.
   */
  public const REASON_SIZE = 'size';

  /**
   * Maximum number of operations accepted in one plan.
   *
   * A plan is reviewed by a human before it is applied, and a list longer
   * than this stops being reviewed and starts being trusted, which is the
   * one thing this feature must not encourage.
   */
  public const MAX_OPERATIONS = 10;

  /**
   * The operation kinds this module knows how to execute.
   *
   * Removals are deliberately absent. An assistant that can delete a
   * maintainer's work on its reading of a sentence is a bad trade, and
   * nothing here needs it: everything this feature offers is additive.
   */
  protected const KINDS = ['addNode', 'addEdge', 'setCondition', 'updateNode'];

  /**
   * The component types the modeler's addNode() can actually place.
   *
   * The full modeler_api range runs to seven, but a link (5) belongs to a
   * connection, and a swimlane (3) or annotation (7) is not a step, so only
   * these four can be created as nodes.
   */
  protected const ADDABLE_COMPONENT_TYPES = [1, 2, 4, 6];

  /**
   * Matches a reference token the assistant may invent for a new component.
   *
   * Deliberately narrow: the token is only ever compared against other
   * tokens and never interpreted, so there is no reason to allow anything
   * that could be confused with a real ID or with markup.
   */
  protected const REF_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

  /**
   * Maximum number of characters accepted for a plugin identifier.
   */
  protected const MAX_PLUGIN_CHARACTERS = 255;

  /**
   * Maximum number of characters accepted for an identifier or reference.
   */
  protected const MAX_ID_CHARACTERS = 128;

  /**
   * Maximum number of characters kept for a label.
   */
  protected const MAX_LABEL_CHARACTERS = 128;

  /**
   * Maximum number of characters kept for an annotation.
   */
  protected const MAX_ANNOTATION_CHARACTERS = 500;

  /**
   * Maximum number of configuration entries kept for one component.
   */
  protected const MAX_CONFIG_ENTRIES = 25;

  /**
   * Maximum number of characters kept for one configuration value.
   */
  protected const MAX_CONFIG_VALUE_CHARACTERS = 200;

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $operations, array $context): array {
    if ($operations === NULL) {
      return $this->result([], '');
    }
    if (!is_array($operations) || !array_is_list($operations)) {
      return $this->result([], self::REASON_SHAPE);
    }
    if ($operations === []) {
      return $this->result([], '');
    }
    if (count($operations) > self::MAX_OPERATIONS) {
      return $this->result([], self::REASON_SIZE);
    }

    // What a reference is allowed to point at. Both sets grow as the plan is
    // walked, so that an operation can use what an earlier one created.
    $nodes = array_flip($this->identifiers($context['nodes'] ?? NULL));
    $edges = array_flip($this->identifiers($context['edges'] ?? NULL));
    $plugins = array_flip($this->identifiers($context['plugins'] ?? NULL));

    $validated = [];
    foreach ($operations as $operation) {
      if (!is_array($operation)) {
        return $this->result([], self::REASON_SHAPE);
      }
      $kind = $operation['kind'] ?? NULL;
      if (!is_string($kind) || !in_array($kind, self::KINDS, TRUE)) {
        return $this->result([], self::REASON_SHAPE);
      }

      $reason = '';
      $entry = match ($kind) {
        'addNode' => $this->validateAddNode($operation, $nodes, $plugins, $reason),
        'addEdge' => $this->validateAddEdge($operation, $nodes, $edges, $reason),
        'setCondition' => $this->validateSetCondition($operation, $edges, $plugins, $reason),
        'updateNode' => $this->validateUpdateNode($operation, $nodes, $reason),
      };
      if ($entry === NULL) {
        return $this->result([], $reason === '' ? self::REASON_SHAPE : $reason);
      }

      $validated[] = $entry;
    }

    return $this->result($validated, '');
  }

  /**
   * Validates an operation that adds a component.
   *
   * @param array $operation
   *   The raw operation.
   * @param array $nodes
   *   The known component references, extended with this operation's own.
   * @param array $plugins
   *   The known plugin IDs, empty when the check is to be skipped.
   * @param string $reason
   *   Set to the rejection reason when the operation is refused.
   *
   * @return array|null
   *   The validated operation, or NULL when it is unusable.
   */
  protected function validateAddNode(array $operation, array &$nodes, array $plugins, string &$reason): ?array {
    $plugin = $this->plugin($operation['plugin'] ?? NULL, $plugins, $reason);
    if ($plugin === NULL) {
      return NULL;
    }

    // is_numeric() already rejects booleans, so a "true" here cannot slip
    // through as the number one.
    $componentType = $operation['componentType'] ?? NULL;
    if (!is_numeric($componentType)) {
      return NULL;
    }
    $componentType = (int) $componentType;
    if (!in_array($componentType, self::ADDABLE_COMPONENT_TYPES, TRUE)) {
      return NULL;
    }

    $configuration = $this->configuration($operation['configuration'] ?? NULL);
    if ($configuration === NULL) {
      return NULL;
    }

    $ref = $this->declareReference($operation['ref'] ?? NULL, $nodes, $reason);
    if ($ref === NULL) {
      return NULL;
    }

    return [
      'kind' => 'addNode',
      'ref' => $ref,
      'plugin' => $plugin,
      'componentType' => $componentType,
      'label' => $this->text($operation['label'] ?? NULL, self::MAX_LABEL_CHARACTERS),
      'configuration' => $configuration,
    ];
  }

  /**
   * Validates an operation that connects two components.
   *
   * @param array $operation
   *   The raw operation.
   * @param array $nodes
   *   The known component references.
   * @param array $edges
   *   The known connection references, extended with this operation's own.
   * @param string $reason
   *   Set to the rejection reason when the operation is refused.
   *
   * @return array|null
   *   The validated operation, or NULL when it is unusable.
   */
  protected function validateAddEdge(array $operation, array $nodes, array &$edges, string &$reason): ?array {
    $source = $this->reference($operation['source'] ?? NULL, $nodes, $reason);
    $target = $this->reference($operation['target'] ?? NULL, $nodes, $reason);
    if ($source === NULL || $target === NULL) {
      return NULL;
    }
    if ($source === $target) {
      $reason = self::REASON_REFERENCE;
      return NULL;
    }

    $ref = $this->declareReference($operation['ref'] ?? NULL, $edges, $reason);
    if ($ref === NULL) {
      return NULL;
    }

    return [
      'kind' => 'addEdge',
      'ref' => $ref,
      'source' => $source,
      'target' => $target,
    ];
  }

  /**
   * Validates an operation that puts a condition on a connection.
   *
   * The modeler inserts a condition as a node that splits the connection in
   * two, so the connection named here stops existing the moment the
   * operation runs. It is therefore taken out of the known set, which turns
   * a second condition on the same connection into a rejected plan rather
   * than a plan that fails while it is being applied.
   *
   * @param array $operation
   *   The raw operation.
   * @param array $edges
   *   The known connection references, reduced by the one consumed here.
   * @param array $plugins
   *   The known plugin IDs, empty when the check is to be skipped.
   * @param string $reason
   *   Set to the rejection reason when the operation is refused.
   *
   * @return array|null
   *   The validated operation, or NULL when it is unusable.
   */
  protected function validateSetCondition(array $operation, array &$edges, array $plugins, string &$reason): ?array {
    $edge = $this->reference($operation['edge'] ?? NULL, $edges, $reason);
    if ($edge === NULL) {
      return NULL;
    }
    $plugin = $this->plugin($operation['plugin'] ?? NULL, $plugins, $reason);
    if ($plugin === NULL) {
      return NULL;
    }
    $configuration = $this->configuration($operation['configuration'] ?? NULL);
    if ($configuration === NULL) {
      return NULL;
    }

    unset($edges[$edge]);

    return [
      'kind' => 'setCondition',
      'edge' => $edge,
      'plugin' => $plugin,
      'label' => $this->text($operation['label'] ?? NULL, self::MAX_LABEL_CHARACTERS),
      'configuration' => $configuration,
    ];
  }

  /**
   * Validates an operation that changes an existing component.
   *
   * @param array $operation
   *   The raw operation.
   * @param array $nodes
   *   The known component references.
   * @param string $reason
   *   Set to the rejection reason when the operation is refused.
   *
   * @return array|null
   *   The validated operation, or NULL when it is unusable.
   */
  protected function validateUpdateNode(array $operation, array $nodes, string &$reason): ?array {
    $node = $this->reference($operation['node'] ?? NULL, $nodes, $reason);
    if ($node === NULL) {
      return NULL;
    }

    $entry = ['kind' => 'updateNode', 'node' => $node];

    $entry['label'] = array_key_exists('label', $operation) && is_string($operation['label'])
      ? $this->text($operation['label'], self::MAX_LABEL_CHARACTERS)
      : NULL;
    $entry['annotation'] = array_key_exists('annotation', $operation) && is_string($operation['annotation'])
      ? $this->text($operation['annotation'], self::MAX_ANNOTATION_CHARACTERS)
      : NULL;

    if (array_key_exists('configuration', $operation) && $operation['configuration'] !== NULL) {
      $configuration = $this->configuration($operation['configuration']);
      if ($configuration === NULL) {
        return NULL;
      }
      $entry['configuration'] = $configuration;
    }
    else {
      $entry['configuration'] = NULL;
    }

    // An update that updates nothing is not an operation, it is noise the
    // user would have to read and approve for no result.
    if ($entry['label'] === NULL && $entry['annotation'] === NULL && $entry['configuration'] === NULL) {
      return NULL;
    }

    return $entry;
  }

  /**
   * Resolves a reference to something the plan may name.
   *
   * @param mixed $value
   *   The raw reference.
   * @param array $known
   *   The identifiers and tokens available at this point in the plan.
   * @param string $reason
   *   Set to the rejection reason when the reference does not resolve.
   *
   * @return string|null
   *   The reference, or NULL when it names nothing.
   */
  protected function reference(mixed $value, array $known, string &$reason): ?string {
    if (!is_string($value)) {
      return NULL;
    }
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > self::MAX_ID_CHARACTERS) {
      return NULL;
    }
    if (!isset($known[$value])) {
      $reason = self::REASON_REFERENCE;
      return NULL;
    }

    return $value;
  }

  /**
   * Registers the token an operation invents for what it creates.
   *
   * @param mixed $value
   *   The raw token, which may be absent.
   * @param array $known
   *   The set the token is added to.
   * @param string $reason
   *   Set to the rejection reason when the token is unusable.
   *
   * @return string|null
   *   The token, an empty string when none was given, or NULL when the token
   *   is malformed or already taken.
   */
  protected function declareReference(mixed $value, array &$known, string &$reason): ?string {
    if ($value === NULL || $value === '') {
      return '';
    }
    if (!is_string($value) || preg_match(self::REF_PATTERN, $value) !== 1) {
      return NULL;
    }
    // Reusing a token that already names something would silently redirect
    // every later reference to it.
    if (isset($known[$value])) {
      $reason = self::REASON_REFERENCE;
      return NULL;
    }
    $known[$value] = TRUE;

    return $value;
  }

  /**
   * Validates a plugin identifier.
   *
   * @param mixed $value
   *   The raw plugin identifier.
   * @param array $plugins
   *   The known plugin IDs, empty when the check is to be skipped.
   * @param string $reason
   *   Set to the rejection reason when the plugin is unusable.
   *
   * @return string|null
   *   The plugin identifier, or NULL when it is unusable.
   */
  protected function plugin(mixed $value, array $plugins, string &$reason): ?string {
    if (!is_string($value)) {
      return NULL;
    }
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > self::MAX_PLUGIN_CHARACTERS) {
      return NULL;
    }
    // A suggestion that names a plugin which does not exist simply fails to
    // add and says so. An operation that does is part of a plan the user has
    // already approved, so it gets caught here instead.
    if ($plugins !== [] && !isset($plugins[$value])) {
      $reason = self::REASON_PLUGIN;
      return NULL;
    }

    return $value;
  }

  /**
   * Validates a configuration map.
   *
   * @param mixed $value
   *   The raw configuration, which may be absent.
   *
   * @return array|null
   *   The cleaned configuration, which may be empty, or NULL when the value
   *   was not a map at all.
   */
  protected function configuration(mixed $value): ?array {
    if ($value === NULL) {
      return [];
    }
    // An empty array is an empty map as far as JSON is concerned, but a
    // populated list is not a configuration at all.
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
      return NULL;
    }

    $configuration = [];
    foreach ($value as $key => $entry) {
      if (count($configuration) >= self::MAX_CONFIG_ENTRIES) {
        break;
      }
      $key = trim((string) $key);
      if ($key === '' || mb_strlen($key) > self::MAX_ID_CHARACTERS) {
        continue;
      }
      if ($entry === NULL) {
        $configuration[$key] = '';
        continue;
      }
      if (is_bool($entry)) {
        $configuration[$key] = $entry;
        continue;
      }
      // Anything that is not a scalar would have to be guessed at, and a
      // guessed configuration value is worse than an absent one.
      if (!is_scalar($entry)) {
        continue;
      }
      $configuration[$key] = is_string($entry)
        ? $this->text($entry, self::MAX_CONFIG_VALUE_CHARACTERS)
        : $entry;
    }

    return $configuration;
  }

  /**
   * Casts a value to a trimmed, length limited string.
   *
   * @param mixed $value
   *   The value to cast.
   * @param int $limit
   *   The maximum number of characters kept.
   *
   * @return string
   *   The resulting string.
   */
  protected function text(mixed $value, int $limit): string {
    if (!is_string($value)) {
      return '';
    }
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

    return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
  }

  /**
   * Reads a list of identifiers out of the supplied context.
   *
   * @param mixed $identifiers
   *   The raw identifiers.
   *
   * @return array
   *   The usable identifiers.
   */
  protected function identifiers(mixed $identifiers): array {
    if (!is_array($identifiers)) {
      return [];
    }

    $usable = [];
    foreach ($identifiers as $identifier) {
      if (is_string($identifier) && $identifier !== '' && mb_strlen($identifier) <= self::MAX_ID_CHARACTERS) {
        $usable[] = $identifier;
      }
    }

    return $usable;
  }

  /**
   * Builds the return value.
   *
   * @param array $operations
   *   The validated operations.
   * @param string $reason
   *   The rejection reason, or an empty string.
   *
   * @return array
   *   The result.
   */
  protected function result(array $operations, string $reason): array {
    return ['operations' => $operations, 'reason' => $reason];
  }

}
