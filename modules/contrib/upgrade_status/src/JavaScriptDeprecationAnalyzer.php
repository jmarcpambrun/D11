<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;

// cspell:ignore blacklist
/**
 * The JavaScript deprecation analyzer.
 *
 * Core only reports these deprecations in the browser, so PHPStan can't find
 * them. Some of them are also set from PHP, Twig or YAML. Drupal 12 removes
 * them without a message:
 * - The dialogClass dialog option is ignored. jQuery UI only reads it in its
 *   backward compatibility mode, which core does not turn on.
 * - jQuery handlers of the dialog events only get the event.
 * - Triggering the dialogButtonsChange jQuery event does not update the
 *   buttons.
 * - The data-autocomplete-first-character-blacklist attribute and the
 *   firstCharacterBlacklist option of autocomplete fields are ignored.
 * - Drupal.contextual.views and Drupal.contextual.regionViews are removed.
 */
final class JavaScriptDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The message for each use of the dialogClass option.
   */
  private const OPTION_MESSAGE = 'The dialogClass dialog option is used. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Drupal 12 ignores it, so the dialog does not get the class. Use the ui-dialog key of the classes option instead. See https://www.drupal.org/node/3440844.';

  /**
   * Finds the option as a key or a property, also inside a JSON string.
   */
  private const OPTION_PATTERN = '/(?<![\w$-])dialogClass\\\\?[\'"]?\s*:|[\w$\])]\.dialogClass(?![\w$])|\[[\'"]dialogClass[\'"]\]/';

  /**
   * The message for each jQuery handler of a dialog event.
   */
  private const EVENT_MESSAGE = 'A jQuery handler of a dialog event is used. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Drupal 12 only passes the event to the handler, without the dialog, the element and the settings. Use addEventListener(), and read event.dialog, event.target and event.settings instead. See https://www.drupal.org/node/3422670.';

  /**
   * Finds dialog events in jQuery's on() and one(), also as object keys.
   *
   * Native listeners from addEventListener() are not matched.
   */
  private const EVENT_PATTERN = '/\.one?\(\s*[\'"`][^\'"`]*(?<![\w-])dialog:(?:before|after)(?:create|close)\b|[\'"]dialog:(?:before|after)(?:create|close)[\'"]\s*:/';

  /**
   * The message for each jQuery trigger of the dialogButtonsChange event.
   */
  private const BUTTONS_MESSAGE = 'The dialogButtonsChange jQuery event is triggered. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Drupal 12 does not update the dialog buttons for it. Dispatch a dialogButtonsChange CustomEvent on the dialog element instead. See https://www.drupal.org/node/3464202.';

  /**
   * Finds jQuery triggers of the dialogButtonsChange event.
   */
  private const BUTTONS_PATTERN = '/\.trigger(?:Handler)?\(\s*[\'"`]dialogButtonsChange\b/';

  /**
   * The message for each use of the old autocomplete attribute.
   */
  private const AUTOCOMPLETE_ATTRIBUTE_MESSAGE = 'The data-autocomplete-first-character-blacklist attribute is used. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Drupal 12 ignores it. Use the data-autocomplete-first-character-denylist attribute instead. See https://www.drupal.org/node/3472016.';

  /**
   * Finds the old autocomplete attribute.
   */
  private const AUTOCOMPLETE_ATTRIBUTE_PATTERN = '/(?<![\w-])data-autocomplete-first-character-blacklist(?![\w-])/';

  /**
   * The message for each use of the old autocomplete option.
   */
  private const AUTOCOMPLETE_OPTION_MESSAGE = 'The firstCharacterBlacklist autocomplete option is used. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Drupal 12 ignores it. Use firstCharacterDenyList instead. See https://www.drupal.org/node/3472016.';

  /**
   * Finds the old autocomplete option.
   */
  private const AUTOCOMPLETE_OPTION_PATTERN = '/(?<![\w$])firstCharacterBlacklist(?![\w$])/';

  /**
   * The message for each use of the removed contextual properties.
   */
  private const CONTEXTUAL_MESSAGE = 'Drupal.contextual.views or Drupal.contextual.regionViews is used. Deprecated in drupal:9.4.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/project/drupal/issues/3442530.';

  /**
   * Finds the removed contextual properties.
   */
  private const CONTEXTUAL_PATTERN = '/(?<![\w$.])Drupal\.contextual\.(?:views|regionViews)(?![\w$])/';

  /**
   * The checks for each kind of file, as patterns keyed by message.
   *
   * PHP strings use the same patterns as Twig and YAML files.
   */
  private const CHECKS = [
    'js' => [
      self::OPTION_MESSAGE => self::OPTION_PATTERN,
      self::EVENT_MESSAGE => self::EVENT_PATTERN,
      self::BUTTONS_MESSAGE => self::BUTTONS_PATTERN,
      self::AUTOCOMPLETE_ATTRIBUTE_MESSAGE => self::AUTOCOMPLETE_ATTRIBUTE_PATTERN,
      self::AUTOCOMPLETE_OPTION_MESSAGE => self::AUTOCOMPLETE_OPTION_PATTERN,
      self::CONTEXTUAL_MESSAGE => self::CONTEXTUAL_PATTERN,
    ],
    'other' => [
      self::OPTION_MESSAGE => self::OPTION_PATTERN,
      self::AUTOCOMPLETE_ATTRIBUTE_MESSAGE => self::AUTOCOMPLETE_ATTRIBUTE_PATTERN,
    ],
  ];

  /**
   * Constructs a new JavaScript deprecation analyzer.
   *
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * Analyzes usages of deprecated JavaScript features in an extension.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array {
    $deprecations = [];
    foreach ($this->getFiles(DRUPAL_ROOT . '/' . $extension->getPath()) as $file) {
      $code = file_get_contents($file);
      if (!preg_match('/dialogClass|dialog:|dialogButtonsChange|first-character-blacklist|firstCharacterBlacklist|contextual\./', $code)) {
        continue;
      }
      $lines = match (pathinfo($file, PATHINFO_EXTENSION)) {
        'js' => $this->findAllLines($this->removeJavaScriptComments($code), self::CHECKS['js']),
        'twig' => $this->findAllLines(static::replaceTwigComments($code, $this->keepNewLines(...)), self::CHECKS['other']),
        'yml' => $this->findAllLines(preg_replace('/^\s*#.*$/m', '', $code) ?? $code, self::CHECKS['other']),
        default => $this->findPhpLines($code),
      };
      foreach ($lines as $message => $message_lines) {
        foreach ($message_lines as $line) {
          $deprecations[] = new DeprecationMessage($message, str_replace(DRUPAL_ROOT . '/', '', $file), $line, 'JavaScriptDeprecationAnalyzer');
        }
      }
    }
    return $deprecations;
  }

  /**
   * Finds the lines of code with each deprecation.
   *
   * @param string $code
   *   The code, without comments.
   * @param array<string, string> $checks
   *   The patterns, keyed by message.
   *
   * @return array<string, list<int>>
   *   The line numbers, keyed by message.
   */
  private function findAllLines(string $code, array $checks): array {
    return array_map(fn (string $pattern): array => $this->findLines($code, $pattern), $checks);
  }

  /**
   * Finds the lines of code that match a pattern.
   *
   * @param string $code
   *   The code, without comments.
   * @param string $pattern
   *   The regular expression.
   *
   * @return list<int>
   *   The line numbers where the matches start.
   */
  private function findLines(string $code, string $pattern): array {
    $lines = [];
    if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
      foreach ($matches[0] as [, $offset]) {
        $line = substr_count($code, "\n", 0, $offset) + 1;
        $lines[$line] = $line;
      }
    }
    return array_values($lines);
  }

  /**
   * Finds the lines of a PHP file with each deprecation.
   *
   * Only strings are checked, so comments and variables are not reported. The
   * dialogClass option is also found as a string on its own, like an array
   * key.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return array<string, list<int>>
   *   The line numbers, keyed by message.
   */
  private function findPhpLines(string $code): array {
    $lines = array_fill_keys(array_keys(self::CHECKS['other']), []);
    foreach (token_get_all($code) as $token) {
      if (!is_array($token) || !in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], TRUE)) {
        continue;
      }
      $is_option_key = in_array($token[1], ["'dialogClass'", '"dialogClass"'], TRUE);
      foreach (self::CHECKS['other'] as $message => $pattern) {
        if (preg_match($pattern, $token[1]) || ($message === self::OPTION_MESSAGE && $is_option_key)) {
          $lines[$message][$token[2]] = $token[2];
        }
      }
    }
    return array_map('array_values', $lines);
  }

  /**
   * Removes the comments from JavaScript code, and keeps the line numbers.
   *
   * @param string $code
   *   The code.
   *
   * @return string
   *   The code without comments.
   */
  private function removeJavaScriptComments(string $code): string {
    return static::replaceCommentsAndStrings($code, fn (array $match): string => $match[0][0] === '/' ? $this->keepNewLines($match) : $match[0]);
  }

  /**
   * Replaces the comments and strings in JavaScript code.
   *
   * Regular expression literals are not recognized. A comment start without
   * an end after it is code, not a comment.
   *
   * @param string $code
   *   The JavaScript code.
   * @param callable $replace
   *   Gets the match of a comment or a string, and returns the replacement.
   *
   * @return string
   *   The code with the replacements.
   */
  public static function replaceCommentsAndStrings(string $code, callable $replace): string {
    // For a comment start without an end, the pattern reads to the end of the
    // code and then goes on from the next character. Without the PCRE JIT,
    // code with many of those takes very long. No comment can end after the
    // last comment end, so the starts from there on are hidden from the
    // pattern, and put back after. The result is the same.
    $hidden = 0;
    if (!str_contains($code, "\0")) {
      $last = strrpos($code, '*/');
      $rest = $last === FALSE ? 0 : max(0, $last - 1);
      $code = substr($code, 0, $rest) . str_replace('/*', "/\0*", substr($code, $rest), $hidden);
    }
    $code = preg_replace_callback(
      '~//[^\n]*|/\*.*?\*/|"(?:\\\\.|[^"\\\\\n])*"|\'(?:\\\\.|[^\'\\\\\n])*\'|`(?:\\\\.|[^`\\\\])*`~s',
      $replace,
      $code,
    ) ?? $code;
    // The end of a comment can take the slash of a hidden start, so only the
    // added character is removed.
    return $hidden ? str_replace("\0", '', $code) : $code;
  }

  /**
   * Replaces the comments in Twig code.
   *
   * @param string $code
   *   The Twig code.
   * @param callable $replace
   *   Gets the match of a comment, and returns the replacement.
   *
   * @return string
   *   The code with the replacements.
   */
  public static function replaceTwigComments(string $code, callable $replace): string {
    // For a comment start without an end, the pattern reads to the end of the
    // code and then goes on from the next character. Without the PCRE JIT,
    // code with many of those takes very long. No comment can end after the
    // last comment end, so the pattern only gets the code up to there. The
    // result is the same.
    $last = strrpos($code, '#}');
    if ($last === FALSE) {
      return $code;
    }
    $rest = substr($code, $last + 2);
    $code = substr($code, 0, $last + 2);
    return (preg_replace_callback('/\{#.*?#\}/s', $replace, $code) ?? $code) . $rest;
  }

  /**
   * Replaces a regular expression match with only its new lines.
   *
   * @param array $match
   *   The match.
   *
   * @return string
   *   The new lines in the match.
   */
  private function keepNewLines(array $match): string {
    return str_repeat("\n", substr_count($match[0], "\n"));
  }

  /**
   * Finds the files that can use these JavaScript features under a path.
   *
   * Test modules are included, but not test classes, which only look at the
   * output. Minified files and third party code are also skipped, because
   * jQuery UI's own code has the dialogClass option.
   *
   * @param string $path
   *   Base path to find the files in.
   *
   * @return list<string>
   *   A list of paths to the files found under the base path.
   */
  private function getFiles(string $path): array {
    $files = [];
    $ignore_directories = array_merge(
      ['dist', 'libraries', 'vendor'],
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    foreach ($this->fileFinder->getPaths($path) as $item) {
      if ($this->fileFinder->isDirectory($item)) {
        if (!in_array(basename($item), $ignore_directories, TRUE) && !preg_match('~/tests/(src|fixtures)$~', $item)) {
          $files = array_merge($files, $this->getFiles($item));
        }
      }
      elseif (preg_match('/\.(php|module|theme|profile|inc|install|js|twig|yml)$/', $item) && !str_ends_with($item, '.min.js')) {
        $files[] = $item;
      }
    }
    return $files;
  }

}
