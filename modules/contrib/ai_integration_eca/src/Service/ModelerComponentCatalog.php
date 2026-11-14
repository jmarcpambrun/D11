<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Renders the components a model may be built from.
 *
 * The whole catalog is far too large to send: an ECA site routinely offers
 * several hundred components, and describing all of them would spend most of
 * the context window on entries the question has nothing to do with. So the
 * catalog is ranked against the question and cut down to the entries that
 * plausibly answer it, plus everything the model already uses.
 *
 * The ranking is deliberately crude. It only has to beat sending an arbitrary
 * slice of the catalog, and a keyword count does that without a second
 * provider call or an embedding index the site would have to maintain.
 */
class ModelerComponentCatalog implements ModelerComponentCatalogInterface {

  /**
   * Maximum number of characters the rendered catalog may occupy.
   */
  public const MAX_CATALOG_CHARACTERS = 8000;

  /**
   * Maximum number of entries selected by relevance.
   *
   * Components already used in the model are added on top of this, because
   * they are not competing for relevance: the answer needs them either way.
   */
  protected const MAX_SCORED_ENTRIES = 40;

  /**
   * Maximum number of catalog entries read from the request at all.
   *
   * The catalog arrives from the browser, so its size is not something this
   * service gets to assume anything about.
   */
  protected const MAX_ACCEPTED_ENTRIES = 2000;

  /**
   * Maximum number of characters rendered for one component description.
   */
  protected const MAX_DESCRIPTION_CHARACTERS = 160;

  /**
   * Maximum number of characters rendered for a label, plugin ID or provider.
   */
  protected const MAX_NAME_CHARACTERS = 128;

  /**
   * Maximum number of keywords taken from the question.
   */
  protected const MAX_KEYWORDS = 25;

  /**
   * Minimum length of a keyword taken from the question.
   *
   * Shorter words carry almost no meaning and match far too much: "id" alone
   * would score every entry with "provider" in its description.
   */
  protected const MIN_KEYWORD_LENGTH = 3;

  /**
   * Words that are dropped from the question before ranking.
   *
   * These are the ones that show up in most questions about a model and would
   * otherwise reward entries for saying nothing in particular.
   */
  protected const STOP_WORDS = [
    'about', 'add', 'after', 'all', 'also', 'and', 'any', 'are', 'been',
    'before', 'but', 'can', 'component', 'components', 'could', 'did', 'does',
    'doing', 'each', 'find', 'for', 'from', 'get', 'has', 'have', 'help',
    'here', 'how', 'into', 'its', 'just', 'like', 'make', 'model', 'more',
    'most', 'need', 'next', 'not', 'now', 'one', 'only', 'other', 'own',
    'should', 'some', 'step', 'such', 'than', 'that', 'the', 'their', 'them',
    'then', 'there', 'these', 'they', 'this', 'those', 'use', 'used', 'using',
    'want', 'was', 'were', 'what', 'when', 'where', 'which', 'who', 'why',
    'will', 'with', 'would', 'you', 'your',
  ];

  /**
   * The weight each field contributes when it matches a keyword.
   *
   * The label is what the user reads in the component palette, so a match
   * there is the strongest signal that the two are talking about the same
   * thing. A description mentioning a word in passing is the weakest.
   */
  protected const FIELD_WEIGHTS = [
    'label' => 5,
    'plugin' => 4,
    'provider' => 2,
    'description' => 1,
  ];

  /**
   * Maps the modeler_api component type constants to their machine names.
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
   * {@inheritdoc}
   */
  public function render(mixed $components, string $prompt, array $usedPlugins): string {
    $entries = $this->normalize($components);
    if ($entries === []) {
      return '';
    }

    $selected = $this->select($entries, $this->keywords($prompt), $usedPlugins);
    $omitted = count($entries) - count($selected);

    $lines = [
      'AVAILABLE COMPONENTS',
      'These are the components this model can be built from, written as',
      '"plugin ID — label (type/number, provider): description". The number is',
      'the component type: 1 = start, 2 = subprocess, 3 = swimlane,',
      '4 = element, 5 = link, 6 = gateway, 7 = annotation.',
    ];
    $used = mb_strlen(implode("\n", $lines));

    $rendered = 0;
    foreach ($selected as $entry) {
      $line = $this->entryLine($entry);
      // Stopping before the budget rather than on it keeps the last entry
      // whole. Half an entry names a plugin that does not exist.
      if ($used + mb_strlen($line) + 1 > self::MAX_CATALOG_CHARACTERS) {
        $omitted += count($selected) - $rendered;
        break;
      }
      $lines[] = $line;
      $used += mb_strlen($line) + 1;
      $rendered++;
    }

    if ($omitted > 0) {
      $lines[] = '[' . $omitted . ' further components omitted. The list above '
        . 'holds the ones most likely to be relevant to the question, so say '
        . 'that a component may exist outside it rather than naming one.]';
    }

    return implode("\n", $lines);
  }

  /**
   * Renders one catalog entry.
   *
   * @param array $entry
   *   The normalized entry.
   *
   * @return string
   *   The rendered line.
   */
  protected function entryLine(array $entry): string {
    $line = $entry['plugin'];
    if ($entry['label'] !== '') {
      $line .= ' — ' . $entry['label'];
    }

    $facts = array_filter([$entry['type'], $entry['provider']]);
    if ($facts !== []) {
      $line .= ' (' . implode(', ', $facts) . ')';
    }
    if ($entry['description'] !== '') {
      $line .= ': ' . $entry['description'];
    }

    return $line;
  }

  /**
   * Picks the entries worth describing.
   *
   * @param array $entries
   *   The normalized entries.
   * @param array $keywords
   *   The keywords taken from the question.
   * @param array $usedPlugins
   *   The plugin identifiers already present in the model.
   *
   * @return array
   *   The selected entries, most relevant first.
   */
  protected function select(array $entries, array $keywords, array $usedPlugins): array {
    $used = array_flip(array_filter($usedPlugins, 'is_string'));

    $inModel = [];
    $candidates = [];
    foreach ($entries as $index => $entry) {
      if (isset($used[$entry['plugin']])) {
        $inModel[] = $entry;
        continue;
      }
      $candidates[] = [
        'index' => $index,
        'score' => $this->score($entry, $keywords),
        'entry' => $entry,
      ];
    }

    // A stable sort keeps the catalog order of everything that scored the
    // same, which is the order the modeler chose to present them in.
    usort($candidates, static function (array $a, array $b): int {
      return $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index'];
    });

    $scored = array_column(
      array_slice($candidates, 0, self::MAX_SCORED_ENTRIES),
      'entry'
    );

    return array_merge($inModel, $scored);
  }

  /**
   * Scores one entry against the keywords of the question.
   *
   * @param array $entry
   *   The normalized entry.
   * @param array $keywords
   *   The keywords taken from the question.
   *
   * @return int
   *   The score, zero when nothing matched.
   */
  protected function score(array $entry, array $keywords): int {
    if ($keywords === []) {
      return 0;
    }

    $score = 0;
    foreach ($keywords as $keyword) {
      foreach (self::FIELD_WEIGHTS as $field => $weight) {
        if ($entry['haystack'][$field] !== '' && str_contains($entry['haystack'][$field], $keyword)) {
          $score += $weight;
        }
      }
    }

    return $score;
  }

  /**
   * Takes the words worth ranking against out of the question.
   *
   * @param string $prompt
   *   The question the user asked.
   *
   * @return array
   *   The keywords, lower case and unique.
   */
  protected function keywords(string $prompt): array {
    $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($prompt)) ?: [];
    $stopWords = array_flip(self::STOP_WORDS);

    $keywords = [];
    foreach ($words as $word) {
      if (mb_strlen($word) < self::MIN_KEYWORD_LENGTH || isset($stopWords[$word])) {
        continue;
      }
      $keywords[$word] = $word;
      if (count($keywords) >= self::MAX_KEYWORDS) {
        break;
      }
    }

    return array_values($keywords);
  }

  /**
   * Normalizes the catalog as sent by the browser.
   *
   * @param mixed $components
   *   The raw catalog.
   *
   * @return array
   *   The normalized entries. Each carries the rendered fields plus a
   *   "haystack" of the same fields in lower case, so that ranking does not
   *   have to lower case them once per keyword.
   */
  protected function normalize(mixed $components): array {
    if (!is_array($components)) {
      return [];
    }

    $normalized = [];
    $seen = [];
    foreach ($components as $component) {
      if (count($normalized) >= self::MAX_ACCEPTED_ENTRIES) {
        break;
      }
      if (!is_array($component)) {
        continue;
      }
      $plugin = $this->text($component['plugin'] ?? NULL, self::MAX_NAME_CHARACTERS);
      if ($plugin === '' || isset($seen[$plugin])) {
        continue;
      }
      $seen[$plugin] = TRUE;

      $entry = [
        'plugin' => $plugin,
        'label' => $this->text($component['label'] ?? NULL, self::MAX_NAME_CHARACTERS),
        'provider' => $this->text($component['provider'] ?? NULL, self::MAX_NAME_CHARACTERS),
        'description' => $this->text($component['description'] ?? NULL, self::MAX_DESCRIPTION_CHARACTERS),
        'type' => $this->componentType($component),
      ];
      $entry['haystack'] = [
        'plugin' => mb_strtolower($entry['plugin']),
        'label' => mb_strtolower($entry['label']),
        'provider' => mb_strtolower($entry['provider']),
        'description' => mb_strtolower($entry['description']),
      ];

      $normalized[] = $entry;
    }

    return $normalized;
  }

  /**
   * Renders the component type of one entry.
   *
   * Both the name and the number are given: the name is what the answer
   * should say to the user, the number is what the structured reply has to
   * carry so that the panel can place the component.
   *
   * @param array $component
   *   The raw component.
   *
   * @return string
   *   The rendered type, or an empty string when it is unknown.
   */
  protected function componentType(array $component): string {
    $componentType = $component['componentType'] ?? NULL;
    if (is_numeric($componentType) && isset(self::COMPONENT_TYPES[(int) $componentType])) {
      return self::COMPONENT_TYPES[(int) $componentType] . '/' . (int) $componentType;
    }

    return $this->text($component['type'] ?? NULL, self::MAX_NAME_CHARACTERS);
  }

  /**
   * Casts a value to a trimmed, length limited, single line string.
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
    if (!is_scalar($value)) {
      return '';
    }
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
    if (mb_strlen($value) > $limit) {
      $value = mb_substr($value, 0, $limit) . '...';
    }

    return $value;
  }

}
