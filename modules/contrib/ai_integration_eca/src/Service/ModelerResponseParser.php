<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Splits an assistant reply into prose and a structured envelope.
 *
 * The envelope is carried in a fenced JSON code block rather than in a
 * provider native structured output mode, because the site may be running any
 * provider and only some of them offer one. The cost of that choice is that
 * the block arrives as untrusted text in the middle of an answer, so every
 * step here assumes the worst: no block, a truncated block, valid JSON of the
 * wrong shape, several blocks, or prose that continues after the block.
 *
 * When a future provider agnostic structured output mode becomes available,
 * this service is the only thing that needs replacing: the controller and the
 * panel only ever see the parsed result.
 */
class ModelerResponseParser implements ModelerResponseParserInterface {

  /**
   * Maximum number of suggestions accepted from one reply.
   *
   * A panel full of cards is not advice, it is a catalog. Five is about as
   * many as a user will read before scrolling.
   */
  public const MAX_SUGGESTIONS = 5;

  /**
   * The keys that mark a decoded code block as an envelope.
   *
   * A block is only treated as an envelope, and therefore only removed from
   * the prose, when it carries at least one of these. That leaves any other
   * JSON the assistant may quote, for instance an example configuration,
   * where the user can see it.
   *
   * Both keys are independently optional: a reply may carry prose alone,
   * suggestions alone, a plan alone, or both lists together.
   */
  protected const ENVELOPE_KEYS = ['suggestions', 'operations'];

  /**
   * The component type constants the modeler API defines.
   *
   * The values are the modeler_api component types: 1 = start, 2 = subprocess,
   * 3 = swimlane, 4 = element, 5 = link, 6 = gateway, 7 = annotation.
   */
  protected const COMPONENT_TYPES = [1, 2, 3, 4, 5, 6, 7];

  /**
   * Maximum number of characters accepted for a plugin identifier.
   */
  protected const MAX_PLUGIN_CHARACTERS = 255;

  /**
   * Maximum number of characters kept for a suggestion label.
   */
  protected const MAX_LABEL_CHARACTERS = 128;

  /**
   * Maximum number of characters kept for a suggestion reason.
   */
  protected const MAX_REASON_CHARACTERS = 400;

  /**
   * Matches a fenced code block and captures its language and its body.
   *
   * The closing fence is matched without requiring the newline before it, so
   * that a reply cut short by a token limit still yields what it managed to
   * produce rather than nothing at all.
   */
  protected const FENCE_PATTERN = '/```[ \t]*([a-zA-Z0-9_+-]*)[ \t]*\r?\n(.*?)```/s';

  /**
   * The plan validator.
   *
   * @var \Drupal\ai_integration_eca\Service\ModelerPlanValidatorInterface
   */
  protected ModelerPlanValidatorInterface $planValidator;

  /**
   * Constructs a ModelerResponseParser object.
   *
   * @param \Drupal\ai_integration_eca\Service\ModelerPlanValidatorInterface $planValidator
   *   The plan validator.
   */
  public function __construct(ModelerPlanValidatorInterface $planValidator) {
    $this->planValidator = $planValidator;
  }

  /**
   * {@inheritdoc}
   */
  public function parse(string $reply, array $context = []): array {
    $envelopes = $this->findEnvelopes($reply);

    // Cut the envelopes out from the back, so that the offsets of the ones
    // still to be removed stay valid.
    $prose = $reply;
    foreach (array_reverse($envelopes) as $envelope) {
      $prose = substr_replace($prose, '', $envelope['offset'], $envelope['length']);
    }

    $last = end($envelopes);
    $plan = $this->planValidator->validate(
      $last === FALSE ? NULL : ($last['data']['operations'] ?? NULL),
      $context
    );

    return [
      'reply' => $this->tidy($prose),
      'suggestions' => $last === FALSE
        ? []
        : $this->normalizeSuggestions($last['data']['suggestions'] ?? NULL),
      'operations' => $plan['operations'],
      'operationsReason' => $plan['reason'],
    ];
  }

  /**
   * Finds the fenced code blocks that carry an envelope.
   *
   * @param string $reply
   *   The raw reply.
   *
   * @return array
   *   A list of arrays with the "offset", "length" and "data" keys, in the
   *   order the blocks appear in the reply.
   */
  protected function findEnvelopes(string $reply): array {
    $matches = [];
    $found = preg_match_all(
      self::FENCE_PATTERN,
      $reply,
      $matches,
      PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );
    if ($found === FALSE || $found === 0) {
      return [];
    }

    $envelopes = [];
    foreach ($matches as $match) {
      $language = mb_strtolower(trim($match[1][0]));
      // An unlabeled fence is accepted because not every provider labels the
      // one it was asked for, but a fence labeled as anything other than JSON
      // was meant as an illustration and is left alone.
      if ($language !== '' && $language !== 'json') {
        continue;
      }
      $decoded = json_decode(trim($match[2][0]), TRUE);
      if (!is_array($decoded) || array_intersect_key($decoded, array_flip(self::ENVELOPE_KEYS)) === []) {
        continue;
      }

      $envelopes[] = [
        'offset' => $match[0][1],
        'length' => strlen($match[0][0]),
        'data' => $decoded,
      ];
    }

    return $envelopes;
  }

  /**
   * Validates the suggestions of an envelope.
   *
   * An entry that does not describe a component the modeler could act on is
   * dropped on its own. Failing the whole reply over one bad entry would cost
   * the user the answer as well as the suggestions.
   *
   * @param mixed $suggestions
   *   The raw suggestions from the decoded envelope.
   *
   * @return array
   *   The validated suggestions.
   */
  protected function normalizeSuggestions(mixed $suggestions): array {
    if (!is_array($suggestions)) {
      return [];
    }

    $normalized = [];
    $seen = [];
    foreach ($suggestions as $suggestion) {
      if (count($normalized) >= self::MAX_SUGGESTIONS) {
        break;
      }
      if (!is_array($suggestion)) {
        continue;
      }

      $plugin = $suggestion['plugin'] ?? NULL;
      if (!is_string($plugin)) {
        continue;
      }
      $plugin = trim($plugin);
      if ($plugin === '' || mb_strlen($plugin) > self::MAX_PLUGIN_CHARACTERS) {
        continue;
      }

      $componentType = $this->componentType($suggestion['componentType'] ?? NULL);
      if ($componentType === NULL) {
        continue;
      }

      // The same component proposed twice is a mistake on the provider's
      // side, not two pieces of advice.
      $key = $plugin . '|' . $componentType;
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;

      $normalized[] = [
        'plugin' => $plugin,
        'componentType' => $componentType,
        'label' => $this->text($suggestion['label'] ?? NULL, self::MAX_LABEL_CHARACTERS),
        'reason' => $this->text($suggestion['reason'] ?? NULL, self::MAX_REASON_CHARACTERS),
      ];
    }

    return $normalized;
  }

  /**
   * Validates a component type.
   *
   * JSON produced by a language model quotes numbers often enough to be worth
   * tolerating, so a numeric string that names a whole number is accepted as
   * well as a real integer.
   *
   * @param mixed $componentType
   *   The raw component type.
   *
   * @return int|null
   *   The component type, or NULL when it is not one the modeler knows.
   */
  protected function componentType(mixed $componentType): ?int {
    if (is_bool($componentType) || !is_scalar($componentType) || !is_numeric($componentType)) {
      return NULL;
    }
    $number = (float) $componentType;
    if ($number !== floor($number)) {
      return NULL;
    }
    $value = (int) $number;

    return in_array($value, self::COMPONENT_TYPES, TRUE) ? $value : NULL;
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
    if (!is_string($value)) {
      return '';
    }
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

    return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
  }

  /**
   * Tidies the prose left over once the envelopes have been removed.
   *
   * @param string $prose
   *   The prose to tidy.
   *
   * @return string
   *   The tidied prose.
   */
  protected function tidy(string $prose): string {
    // Removing a block from between two paragraphs leaves the blank lines on
    // both sides of it behind, which shows up as a gap in the chat log. A
    // reply that is not valid UTF-8 makes preg_replace() return NULL, in
    // which case the untidied prose is still better than nothing.
    $tidied = preg_replace('/\R{3,}/', "\n\n", $prose);

    return trim($tidied ?? $prose);
  }

}
