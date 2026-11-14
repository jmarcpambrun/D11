<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor;

/**
 * Normalizes the mixed-shape tag array of an AI provider request event.
 *
 * \Drupal\ai\Event\AiProviderRequestBaseEvent stores tags in an array that can
 * hold two different shapes at the same time:
 *
 * - The AI module seeds it as a plain LIST of tag strings, because
 *   ProviderProxy feeds it from AiProviderClientBase::setTag(), which appends
 *   (`$this->tags[] = $tag`).
 * - AiProviderRequestBaseEvent::setTag() — used by this module's SetTagAction —
 *   instead writes `$this->tags[$tag] = $value`, so the tag name becomes an
 *   array KEY and the value is the caller's payload.
 *
 * A request that already carried tags and then had one added through an ECA
 * action therefore ends up with a mixed array such as
 * `[0 => 'a', 1 => 'b', 'my_tag' => TRUE]`, where neither `array_values()` nor
 * `array_keys()` alone recovers the full tag set.
 *
 * This class resolves the tag name per entry, matching the union that the
 * module's read side already implements in
 * \Drupal\ai_eca_interceptor\Plugin\ECA\Condition\HasTagCondition: a tag name
 * is either a string VALUE or a string KEY.
 *
 * PRECEDENCE: a string VALUE wins over an integer KEY. See ::normalize() for
 * the one input shape where the two compete and why this order is the better
 * default. Do not flip it without reading that note.
 *
 * @see \Drupal\ai\Event\AiProviderRequestBaseEvent::setTag()
 * @see \Drupal\ai_eca_interceptor\Plugin\ECA\Condition\HasTagCondition
 */
final class TagNormalizer {

  /**
   * Normalizes event tags into a re-indexed list of unique tag names.
   *
   * The result is suitable for passing straight back to an AI provider
   * operation method as its `$tags` argument.
   *
   * Order is preserved: entries are emitted in the order PHP iterates the
   * source array, so the original list tags come before tags that were added
   * later through AiProviderRequestBaseEvent::setTag(). Duplicates are dropped,
   * keeping the first occurrence.
   *
   * On an integer key with a string value the string VALUE wins — that is a
   * deliberate, reasoned choice, not an oversight. See the inline note on the
   * final branch below for the case it gives up and why giving up that one is
   * cheaper than the alternative.
   *
   * @param array<array-key, mixed> $tags
   *   The raw tag array as returned by
   *   \Drupal\ai\Event\AiProviderRequestBaseEvent::getTags().
   *
   * @return list<string>
   *   A re-indexed list of unique, non-empty tag names.
   */
  public static function normalize(array $tags): array {
    $normalized = [];
    // Tracks the next index a plain list entry would occupy. The AI module
    // builds the list portion with `$tags[] = $tag`, so those entries always
    // sit at contiguous integer keys counting up from zero.
    $listPosition = 0;
    foreach ($tags as $key => $value) {
      $isListPosition = $key === $listPosition;
      if ($isListPosition) {
        $listPosition++;
      }
      if (is_string($key)) {
        // setTag() stored the tag name as the array key.
        $name = $key;
      }
      elseif (is_string($value)) {
        // A list entry: the tag name is the value.
        //
        // PRECEDENCE, and it matters. This test comes BEFORE the integer-key
        // recovery below, so on an integer key carrying a string value the
        // VALUE wins and the key is discarded. That is deliberate.
        //
        // Two mutually exclusive origins produce that exact shape, and nothing
        // in the array distinguishes them:
        //
        // 1. A HOLEY LIST. array_filter() over a list of tag strings preserves
        //    keys, so `['a', 'b', 'c']` minus the middle entry is
        //    `[0 => 'a', 2 => 'c']`. Entry `2 => 'c'` is a plain tag named
        //    'c' that merely lost its contiguous position.
        // 2. setTag() WITH AN ALL-NUMERIC NAME AND A STRING PAYLOAD.
        //    AiProviderRequestBaseEvent::setTag(string $tag, mixed $value)
        //    accepts any value type, so setTag('7', 'production') writes
        //    `[7 => 'production']` — here the tag name is the key, '7', and
        //    'production' is only its payload.
        //
        // Preferring the value gets case 1 right and case 2 wrong (it returns
        // 'production' and loses the name '7'). Preferring the key would
        // simply swap which one breaks — and it would break the commoner one:
        // a holey list would normalize to '2', a tag name that never existed.
        // Case 2 is also unreachable from this module, because SetTagAction
        // always passes TRUE as the value; only third-party code calling
        // setTag() directly with an all-numeric name AND a string payload can
        // reach it. So the cheaper sacrifice is case 2, and this ordering is
        // the one that makes it.
        //
        // Do not "fix" this by flipping the branches. It is not a bug to be
        // repaired, it is an undecidable input; the choice is recorded and
        // pinned by TagNormalizerTest.
        $name = $value;
      }
      elseif ($isListPosition) {
        // A contiguous list position holding a non-string value. The AI
        // module's setTag() is typed `string`, so this cannot be a tag name —
        // it is garbage and gets dropped.
        $name = NULL;
      }
      else {
        // A non-contiguous integer key whose value is NOT a string (the branch
        // above already claimed the string-value case). This is setTag()
        // called with an all-numeric tag name such as '7': PHP casts such a
        // string to an integer array key, destroying its type. Recover the
        // name from the key and restore it as a string, so the returned list
        // never leaks an integer to the provider.
        //
        // This is the shape SetTagAction actually produces for a numeric tag,
        // since it always passes TRUE as the value.
        $name = (string) $key;
      }
      if ($name === NULL || $name === '') {
        continue;
      }
      // De-duplicate without using the name as an array key: PHP would cast an
      // all-numeric name back to an integer, and array_keys() would then hand
      // out int 7 instead of the string '7'.
      if (in_array($name, $normalized, TRUE)) {
        continue;
      }
      $normalized[] = $name;
    }
    return $normalized;
  }

}
