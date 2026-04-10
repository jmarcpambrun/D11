<?php

namespace Drupal\openai\Utility;

use Drupal\Component\Utility\Unicode;

/**
 * A utility class for preparing strings when using OpenAI endpoints.
 *
 * @group openai
 */
class StringHelper {

  /**
   * Prepares text for prompt inputs.
   *
   * OpenAIs completion endpoint or any other prompt input API
   * performs worse with strings that contain HTML, certain
   * punctuations, whitespace, and newlines.
   *
   * This method will clean up a string before sending it to OpenAI.
   *
   * @param string $text
   *   The text to attach to a prompt.
   * @param array $removeHtmlElements
   *   An array of HTML elements to remove.
   * @param int $max_length
   *   The maximum length of the text to return. A lower limit
   *   will result in faster response from OpenAI and reduce
   *   API usage. A helpful rule of thumb is that one token generally
   *   corresponds to ~4 characters of text for common English text.
   *   This translates to roughly ¾ of a word (so 100 tokens ~= 75 words).
   *
   * @return string
   *   The prepared text.
   */
  public static function prepareText(string $text, array $removeHtmlElements = [], int $max_length = 10000): string {

  // 🛑 1. Guard ultra important
  if ($text === NULL || trim($text) === '') {
    return '';
  }

  // Never include the contents of the following tags.
  $removeHtmlElements += ['pre', 'code', 'script', 'iframe', 'drupal-media'];

  // 🧼 2. Normalisation UTF-8 propre (sans casser le contenu)
  if (!mb_check_encoding($text, 'UTF-8')) {
    $text = mb_convert_encoding($text, 'UTF-8');
  }

  // 🧱 3. Toujours encapsuler
  $text = '<div>' . $text . '</div>';

  $dom = new \DOMDocument('1.0', 'UTF-8');
  $dom->formatOutput = FALSE;
  $dom->preserveWhiteSpace = TRUE;

  // 🛠️ 4. Gestion robuste des erreurs libxml
  $previous = libxml_use_internal_errors(TRUE);

  try {
    // ✅ Chargement sécurisé (FINI le pipeline cassé)
    $loaded = $dom->loadHTML(
      '<?xml encoding="UTF-8">' . $text,
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    if (!$loaded) {
      return '';
    }
  }
  catch (\Throwable $e) {
    // 🧯 Sécurité ultime
    return '';
  }

  libxml_clear_errors();
  libxml_use_internal_errors($previous);

  $removeElements = [];

  // 🔍 5. Collecte sécurisée
  foreach ($removeHtmlElements as $htmlElement) {
    $tags = $dom->getElementsByTagName($htmlElement);

    foreach ($tags as $tag) {
      $removeElements[] = $tag;
    }
  }

  // ❌ 6. Suppression safe
  foreach ($removeElements as $removeElement) {
    if ($removeElement->parentNode) {
      $removeElement->parentNode->removeChild($removeElement);
    }
  }

  // 🧾 7. Extraction texte
  $text = $dom->saveHTML($dom->documentElement);

  if ($text === FALSE) {
    return '';
  }

  // 🧼 8. Nettoyage final robuste
  $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = strip_tags($text);
  $text = trim($text);

  // Normalisation whitespace
  $text = preg_replace('/\s+/u', ' ', $text);

  // Nettoyage caractères exotiques (option conservatrice)
  $text = preg_replace("/[^\p{L}\p{N}\s\.\?\!\,\']+/u", '', $text);

  // 🛑 Sécurité finale
  if ($text === NULL || $text === '') {
    return '';
  }

  return Unicode::truncate($text, $max_length, TRUE);
 }

}
