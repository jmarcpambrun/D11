<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Builds compact prompt context out of a modeler graph snapshot.
 *
 * The snapshot arrives from the browser and can be arbitrarily large, so this
 * service is deliberately defensive about size. It renders a plain text
 * description instead of JSON, because the punctuation and repeated keys of
 * JSON buy nothing here but cost a noticeable share of the token budget.
 */
class ModelerContextBuilder implements ModelerContextBuilderInterface {

  /**
   * Maximum number of characters the generated context may occupy.
   */
  public const MAX_CONTEXT_CHARACTERS = 60000;

  /**
   * Maximum number of characters a single configuration value may occupy.
   */
  protected const MAX_VALUE_CHARACTERS = 200;

  /**
   * Maximum number of configuration entries rendered for one component.
   */
  protected const MAX_CONFIG_ENTRIES = 25;

  /**
   * Maximum number of characters rendered for a free text property.
   */
  protected const MAX_TEXT_CHARACTERS = 500;

  /**
   * Configuration key fragments whose values are never sent to a provider.
   *
   * The ECA token syntax uses the word "token" all over the place, so that
   * term is deliberately absent from this list.
   */
  protected const REDACTED_KEY_FRAGMENTS = [
    'password',
    'secret',
    'api_key',
    'apikey',
    'private_key',
    'credential',
  ];

  /**
   * Maps the modeler component type constants to their machine names.
   */
  protected const COMPONENT_TYPES = [
    1 => 'start',
    2 => 'subprocess',
    3 => 'swimlane',
    4 => 'element',
    5 => 'link',
    6 => 'gateway',
    7 => 'annotation',
  ];

  /**
   * The component catalog renderer.
   *
   * @var \Drupal\ai_integration_eca\Service\ModelerComponentCatalogInterface
   */
  protected ModelerComponentCatalogInterface $catalog;

  /**
   * Constructs a ModelerContextBuilder object.
   *
   * @param \Drupal\ai_integration_eca\Service\ModelerComponentCatalogInterface $catalog
   *   The component catalog renderer.
   */
  public function __construct(ModelerComponentCatalogInterface $catalog) {
    $this->catalog = $catalog;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $snapshot, string $prompt = ''): string {
    $nodes = $this->normalizeNodes($snapshot['nodes'] ?? []);
    $edges = $this->normalizeEdges($snapshot['edges'] ?? []);
    $selection = $this->normalizeSelection($snapshot['selection'] ?? []);
    $focus = $this->determineFocus($selection, $edges);

    // The catalog is rendered first and its budget taken off the top, so that
    // a large model cannot crowd out the one thing that stops the assistant
    // from inventing plugin IDs.
    $catalog = $this->catalog->render(
      $snapshot['components'] ?? NULL,
      $prompt,
      $this->usedPlugins($nodes, $edges)
    );
    $budget = self::MAX_CONTEXT_CHARACTERS
      - ($catalog === '' ? 0 : mb_strlen($catalog) + 2);

    // Render with decreasing amounts of detail until the result fits into the
    // budget. Structure is preserved for as long as possible, because a model
    // whose shape is known but whose configuration is not can still be
    // explained usefully, while the reverse cannot.
    $strategies = [
      ['configuration' => TRUE, 'focusOnly' => FALSE],
      ['configuration' => TRUE, 'focusOnly' => TRUE],
      ['configuration' => FALSE, 'focusOnly' => TRUE],
    ];

    $context = '';
    foreach ($strategies as $strategy) {
      $context = $this->render(
        $snapshot,
        $nodes,
        $edges,
        $selection,
        $focus,
        $strategy['configuration'],
        $strategy['focusOnly']
      );
      if (mb_strlen($context) <= $budget) {
        return $this->append($context, $catalog);
      }
    }

    return $this->append($this->truncate($context, $budget), $catalog);
  }

  /**
   * Collects the plugin identifiers the model already uses.
   *
   * @param array $nodes
   *   The normalized nodes.
   * @param array $edges
   *   The normalized edges.
   *
   * @return array
   *   The plugin identifiers, without duplicates.
   */
  protected function usedPlugins(array $nodes, array $edges): array {
    $plugins = [];
    foreach ($nodes as $node) {
      if ($node['plugin'] !== '') {
        $plugins[$node['plugin']] = $node['plugin'];
      }
    }
    foreach ($edges as $edge) {
      if ($edge['condition'] !== '') {
        $plugins[$edge['condition']] = $edge['condition'];
      }
    }

    return array_values($plugins);
  }

  /**
   * Appends the component catalog to the model description.
   *
   * @param string $context
   *   The model description.
   * @param string $catalog
   *   The rendered catalog, which may be empty.
   *
   * @return string
   *   The combined context.
   */
  protected function append(string $context, string $catalog): string {
    return $catalog === '' ? $context : $context . "\n\n" . $catalog;
  }

  /**
   * Renders the context with a given level of detail.
   *
   * @param array $snapshot
   *   The raw snapshot, used for the model metadata and the terminology.
   * @param array $nodes
   *   The normalized nodes.
   * @param array $edges
   *   The normalized edges.
   * @param array $selection
   *   The normalized selection.
   * @param array|null $focus
   *   Node IDs that are close to the selection, or NULL when nothing is
   *   selected and therefore everything is equally relevant.
   * @param bool $withConfiguration
   *   Whether component configuration is rendered at all.
   * @param bool $focusOnly
   *   Whether configuration is limited to the focused components.
   *
   * @return string
   *   The rendered context.
   */
  protected function render(array $snapshot, array $nodes, array $edges, array $selection, ?array $focus, bool $withConfiguration, bool $focusOnly): string {
    $lines = [];

    $lines[] = 'MODEL';
    foreach ($this->modelLines($snapshot['model'] ?? []) as $line) {
      $lines[] = $line;
    }
    $lines[] = 'Components: ' . count($nodes);
    $lines[] = 'Connections: ' . count($edges);

    $terminology = $this->terminologyLine($snapshot['componentLabels'] ?? []);
    if ($terminology !== '') {
      $lines[] = '';
      $lines[] = 'TERMINOLOGY';
      $lines[] = $terminology;
    }

    $selectionLines = $this->selectionLines($selection, $nodes);
    if ($selectionLines !== []) {
      $lines[] = '';
      $lines[] = 'SELECTION';
      foreach ($selectionLines as $line) {
        $lines[] = $line;
      }
    }

    if ($nodes !== []) {
      $lines[] = '';
      $lines[] = 'COMPONENTS';
      foreach ($nodes as $node) {
        $detailed = $withConfiguration
          && (!$focusOnly || $focus === NULL || in_array($node['id'], $focus, TRUE));
        foreach ($this->componentLines($node, $detailed) as $line) {
          $lines[] = $line;
        }
      }
    }

    if ($edges !== []) {
      $lines[] = '';
      $lines[] = 'CONNECTIONS';
      foreach ($edges as $edge) {
        foreach ($this->connectionLines($edge, $withConfiguration) as $line) {
          $lines[] = $line;
        }
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Builds the lines describing the model metadata.
   *
   * @param array $model
   *   The model metadata from the snapshot.
   *
   * @return array
   *   The rendered lines.
   */
  protected function modelLines(array $model): array {
    $lines = [];
    $metadata = is_array($model['metadata'] ?? NULL) ? $model['metadata'] : [];

    $label = $this->text($metadata['label'] ?? '');
    if ($label !== '') {
      $lines[] = 'Label: ' . $label;
    }
    $id = $this->text($model['id'] ?? '');
    if ($id !== '') {
      $lines[] = 'Machine name: ' . $id;
    }
    $version = $this->text($model['version'] ?? '');
    if ($version !== '') {
      $lines[] = 'Version: ' . $version;
    }
    $documentation = $this->text($metadata['documentation'] ?? '');
    if ($documentation !== '') {
      $lines[] = 'Documentation: ' . $documentation;
    }
    if (!empty($metadata['tags']) && is_array($metadata['tags'])) {
      $tags = array_filter(array_map(
        fn ($tag) => $this->text($tag),
        $metadata['tags']
      ));
      if ($tags !== []) {
        $lines[] = 'Tags: ' . implode(', ', $tags);
      }
    }

    return $lines;
  }

  /**
   * Builds the sentence describing the model owner terminology.
   *
   * The modeler is not ECA specific, so the wording used on screen is supplied
   * at runtime by whichever model owner is active. Passing it on keeps the
   * answer aligned with what the user actually sees.
   *
   * @param array $labels
   *   The component labels from the snapshot.
   *
   * @return string
   *   The rendered sentence, or an empty string when no labels are known.
   */
  protected function terminologyLine(array $labels): string {
    $parts = [];
    foreach (['start', 'subprocess', 'element', 'link', 'gateway'] as $key) {
      $label = $this->text($labels[$key] ?? '');
      if ($label !== '') {
        $parts[] = $key . ' = "' . $label . '"';
      }
    }
    if ($parts === []) {
      return '';
    }

    return 'The model owner names the component types as follows: '
      . implode(', ', $parts) . '.';
  }

  /**
   * Builds the lines describing the current selection.
   *
   * @param array $selection
   *   The normalized selection.
   * @param array $nodes
   *   The normalized nodes.
   *
   * @return array
   *   The rendered lines.
   */
  protected function selectionLines(array $selection, array $nodes): array {
    $lines = [];
    $labels = [];
    foreach ($nodes as $node) {
      $labels[$node['id']] = $node['label'];
    }

    $selectedNodes = [];
    foreach ($selection['nodeIds'] as $nodeId) {
      $selectedNodes[] = '[' . $nodeId . ']'
        . (($labels[$nodeId] ?? '') === '' ? '' : ' "' . $labels[$nodeId] . '"');
    }
    if (count($selectedNodes) === 1) {
      $lines[] = 'The user has selected the component ' . $selectedNodes[0] . '.';
    }
    elseif ($selectedNodes !== []) {
      $lines[] = 'The user has selected ' . count($selectedNodes)
        . ' components: ' . implode(', ', $selectedNodes) . '.';
    }

    $selectedEdges = array_map(
      fn (string $edgeId): string => '[' . $edgeId . ']',
      $selection['edgeIds']
    );
    if (count($selectedEdges) === 1) {
      $lines[] = 'The user has selected the connection ' . $selectedEdges[0] . '.';
    }
    elseif ($selectedEdges !== []) {
      $lines[] = 'The user has selected ' . count($selectedEdges)
        . ' connections: ' . implode(', ', $selectedEdges) . '.';
    }

    if ($lines !== []) {
      $lines[] = 'Focus the answer on the selection unless the question asks otherwise.';
    }

    return $lines;
  }

  /**
   * Builds the lines describing one component.
   *
   * @param array $node
   *   The normalized node.
   * @param bool $detailed
   *   Whether the configuration is rendered.
   *
   * @return array
   *   The rendered lines.
   */
  protected function componentLines(array $node, bool $detailed): array {
    $line = '[' . $node['id'] . ']';
    if ($node['componentType'] !== '') {
      $line .= ' (' . $node['componentType'] . ')';
    }
    if ($node['label'] !== '') {
      $line .= ' "' . $node['label'] . '"';
    }
    if ($node['plugin'] !== '') {
      $line .= ' plugin: ' . $node['plugin'];
    }
    $lines = [$line];

    if ($node['description'] !== '') {
      $lines[] = '  description: ' . $node['description'];
    }
    if ($node['annotation'] !== '') {
      $lines[] = '  annotation: ' . $node['annotation'];
    }
    if ($detailed && $node['configuration'] !== []) {
      $lines[] = '  configuration:';
      foreach ($this->configurationLines($node['configuration'], '    ') as $entry) {
        $lines[] = $entry;
      }
    }

    return $lines;
  }

  /**
   * Builds the lines describing one connection.
   *
   * @param array $edge
   *   The normalized edge.
   * @param bool $withConfiguration
   *   Whether the condition configuration is rendered.
   *
   * @return array
   *   The rendered lines.
   */
  protected function connectionLines(array $edge, bool $withConfiguration): array {
    $line = '[' . $edge['source'] . '] -> [' . $edge['target'] . ']';
    if ($edge['conditionLabel'] !== '' || $edge['condition'] !== '') {
      $line .= ' when';
      if ($edge['conditionLabel'] !== '') {
        $line .= ' "' . $edge['conditionLabel'] . '"';
      }
      if ($edge['condition'] !== '') {
        $line .= ' condition: ' . $edge['condition'];
      }
    }
    $lines = [$line];

    if ($edge['annotation'] !== '') {
      $lines[] = '  annotation: ' . $edge['annotation'];
    }
    if ($withConfiguration && $edge['conditionConfiguration'] !== []) {
      foreach ($this->configurationLines($edge['conditionConfiguration'], '  ') as $entry) {
        $lines[] = $entry;
      }
    }

    return $lines;
  }

  /**
   * Renders a configuration array as indented key/value lines.
   *
   * @param array $configuration
   *   The configuration to render.
   * @param string $indent
   *   The indentation prefix.
   *
   * @return array
   *   The rendered lines.
   */
  protected function configurationLines(array $configuration, string $indent): array {
    $lines = [];
    $rendered = 0;

    foreach ($configuration as $key => $value) {
      if ($rendered >= self::MAX_CONFIG_ENTRIES) {
        $lines[] = $indent . '[' . (count($configuration) - $rendered)
          . ' further entries omitted]';
        break;
      }
      $key = (string) $key;
      $lines[] = $indent . $key . ': ' . $this->configurationValue($key, $value);
      $rendered++;
    }

    return $lines;
  }

  /**
   * Renders a single configuration value.
   *
   * @param string $key
   *   The configuration key, used to detect credential-like entries.
   * @param mixed $value
   *   The configuration value.
   *
   * @return string
   *   The rendered value.
   */
  protected function configurationValue(string $key, mixed $value): string {
    $haystack = mb_strtolower($key);
    foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
      if (str_contains($haystack, $fragment)) {
        return '[redacted]';
      }
    }

    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if ($value === NULL) {
      return 'null';
    }
    if (is_scalar($value)) {
      $value = (string) $value;
    }
    else {
      $value = json_encode($value);
      $value = $value === FALSE ? '[unreadable value]' : $value;
    }

    $value = $this->collapseWhitespace($value);
    if (mb_strlen($value) > self::MAX_VALUE_CHARACTERS) {
      $value = mb_substr($value, 0, self::MAX_VALUE_CHARACTERS) . '...[truncated]';
    }

    return $value;
  }

  /**
   * Normalizes the nodes of a snapshot.
   *
   * Only the properties that help explain a model survive. Canvas coordinates
   * and the runtime flags the modeler injects for rendering are dropped, both
   * because they cost tokens and because they invite the model to reason about
   * things the user did not ask about.
   *
   * @param mixed $nodes
   *   The raw nodes from the snapshot.
   *
   * @return array
   *   The normalized nodes.
   */
  protected function normalizeNodes(mixed $nodes): array {
    if (!is_array($nodes)) {
      return [];
    }

    $normalized = [];
    foreach ($nodes as $node) {
      if (!is_array($node) || !isset($node['id']) || !is_scalar($node['id'])) {
        continue;
      }
      $data = is_array($node['data'] ?? NULL) ? $node['data'] : [];
      $componentType = $data['componentType'] ?? NULL;
      if (is_numeric($componentType)) {
        $componentType = self::COMPONENT_TYPES[(int) $componentType] ?? '';
      }

      $normalized[] = [
        'id' => $this->text($node['id']),
        'label' => $this->text($data['label'] ?? ''),
        'plugin' => $this->text($data['plugin'] ?? ''),
        'componentType' => $this->text($componentType ?? ''),
        'description' => $this->text($data['description'] ?? ''),
        'annotation' => $this->text($data['annotation'] ?? ''),
        'configuration' => is_array($data['configuration'] ?? NULL) ? $data['configuration'] : [],
      ];
    }

    return $normalized;
  }

  /**
   * Normalizes the edges of a snapshot.
   *
   * @param mixed $edges
   *   The raw edges from the snapshot.
   *
   * @return array
   *   The normalized edges.
   */
  protected function normalizeEdges(mixed $edges): array {
    if (!is_array($edges)) {
      return [];
    }

    $normalized = [];
    foreach ($edges as $edge) {
      if (!is_array($edge) || !isset($edge['source'], $edge['target'])) {
        continue;
      }
      $data = is_array($edge['data'] ?? NULL) ? $edge['data'] : [];
      $configuration = $data['conditionConfiguration'] ?? NULL;

      $normalized[] = [
        'id' => $this->text($edge['id'] ?? ''),
        'source' => $this->text($edge['source']),
        'target' => $this->text($edge['target']),
        'condition' => $this->text($data['condition'] ?? ''),
        'conditionLabel' => $this->text($data['conditionLabel'] ?? ''),
        'conditionConfiguration' => is_array($configuration) ? $configuration : [],
        'annotation' => $this->text($data['annotation'] ?? ''),
      ];
    }

    return $normalized;
  }

  /**
   * Normalizes the selection of a snapshot.
   *
   * @param mixed $selection
   *   The raw selection from the snapshot.
   *
   * @return array
   *   An array with the "nodeIds" and "edgeIds" keys, each containing selected
   *   identifiers in selection order.
   */
  protected function normalizeSelection(mixed $selection): array {
    $normalized = ['nodeIds' => [], 'edgeIds' => []];
    if (!is_array($selection)) {
      return $normalized;
    }

    foreach (['nodeIds', 'edgeIds'] as $key) {
      $values = $selection[$key] ?? [];
      if (!is_array($values)) {
        continue;
      }
      foreach ($values as $value) {
        if (!is_scalar($value)) {
          continue;
        }
        $value = $this->text($value);
        if ($value !== '' && !in_array($value, $normalized[$key], TRUE)) {
          $normalized[$key][] = $value;
        }
      }
    }

    return $normalized;
  }

  /**
   * Determines which components are close enough to the selection to matter.
   *
   * @param array $selection
   *   The normalized selection.
   * @param array $edges
   *   The normalized edges.
   *
   * @return array|null
   *   The IDs of every selected node and their direct neighbors, or NULL when
   *   no node is selected.
   */
  protected function determineFocus(array $selection, array $edges): ?array {
    if ($selection['nodeIds'] === []) {
      return NULL;
    }

    $selected = array_fill_keys($selection['nodeIds'], TRUE);
    $focus = [];
    foreach ($selection['nodeIds'] as $nodeId) {
      $focus[$nodeId] = $nodeId;
    }
    foreach ($edges as $edge) {
      if (isset($selected[$edge['source']])) {
        $focus[$edge['target']] = $edge['target'];
      }
      if (isset($selected[$edge['target']])) {
        $focus[$edge['source']] = $edge['source'];
      }
    }

    return array_values($focus);
  }

  /**
   * Casts a value to a trimmed, length limited, single line string.
   *
   * @param mixed $value
   *   The value to cast.
   *
   * @return string
   *   The resulting string.
   */
  protected function text(mixed $value): string {
    if (!is_scalar($value)) {
      return '';
    }
    $value = $this->collapseWhitespace((string) $value);
    if (mb_strlen($value) > self::MAX_TEXT_CHARACTERS) {
      $value = mb_substr($value, 0, self::MAX_TEXT_CHARACTERS) . '...[truncated]';
    }

    return $value;
  }

  /**
   * Collapses all whitespace runs into single spaces.
   *
   * @param string $value
   *   The value to collapse.
   *
   * @return string
   *   The collapsed value.
   */
  protected function collapseWhitespace(string $value): string {
    return trim((string) preg_replace('/\s+/u', ' ', $value));
  }

  /**
   * Cuts the context down to the budget and says so.
   *
   * @param string $context
   *   The context to truncate.
   * @param int $budget
   *   The number of characters the result may occupy.
   *
   * @return string
   *   The truncated context.
   */
  protected function truncate(string $context, int $budget): string {
    $notice = "\n[The model is too large to describe completely. "
      . 'The description above stops here.]';

    return mb_substr($context, 0, $budget - mb_strlen($notice)) . $notice;
  }

}
