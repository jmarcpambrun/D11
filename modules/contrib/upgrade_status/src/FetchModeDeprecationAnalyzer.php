<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;

/**
 * The fetch mode deprecation analyzer.
 *
 * Drupal 11.2 deprecated integer fetch modes, like \PDO::FETCH_ASSOC, in the
 * database API, and Drupal 12 removes them. Core only reports this when a
 * query runs, and PHPStan can't find it, because the type of the argument
 * still allows an integer.
 */
final class FetchModeDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The message for each fetch mode, with the constant and its replacement.
   */
  private const MESSAGE = '\PDO::%s is passed as a fetch mode. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use %s instead. See https://www.drupal.org/node/3488338.';

  /**
   * The fetch modes and the cases of the FetchAs enum that replace them.
   */
  private const REPLACEMENTS = [
    'FETCH_ASSOC' => '\Drupal\Core\Database\Statement\FetchAs::Associative',
    'FETCH_CLASS' => '\Drupal\Core\Database\Statement\FetchAs::ClassObject',
    'FETCH_COLUMN' => '\Drupal\Core\Database\Statement\FetchAs::Column',
    'FETCH_NUM' => '\Drupal\Core\Database\Statement\FetchAs::List',
    'FETCH_OBJ' => '\Drupal\Core\Database\Statement\FetchAs::Object',
  ];

  /**
   * The methods that take a fetch mode.
   */
  private const METHODS = ['fetch', 'fetchAll', 'fetchAllAssoc', 'setFetchMode'];

  /**
   * Constructs a new fetch mode deprecation analyzer.
   *
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * Analyzes integer fetch modes in an extension.
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
      if (!str_contains($code, 'FETCH_')) {
        continue;
      }
      foreach ($this->findFetchModes($code) as [$constant, $line]) {
        $message = sprintf(self::MESSAGE, $constant, self::REPLACEMENTS[$constant] ?? 'a case of the \Drupal\Core\Database\Statement\FetchAs enum');
        $deprecations[] = new DeprecationMessage($message, str_replace(DRUPAL_ROOT . '/', '', $file), $line, 'FetchModeDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Finds \PDO::FETCH_* constants passed as fetch modes.
   *
   * They are reported as arguments of the fetch methods, and as the value of
   * the 'fetch' query option. Files that open their own PDO connection are
   * skipped, and so are lines that also use the FetchAs enum, which are
   * likely to support older Drupal versions.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return array
   *   A list of constant names and line numbers.
   */
  private function findFetchModes(string $code): array {
    try {
      $tokens = array_values(array_filter(
        \PhpToken::tokenize($code),
        static fn (\PhpToken $token): bool => !$token->isIgnorable(),
      ));
    }
    catch (\ParseError $e) {
      return [];
    }

    $results = [];
    $calls = [];
    $lines = explode("\n", $code);
    foreach ($tokens as $index => $token) {
      if ($token->is(T_NEW) && $this->isPdo($tokens[$index + 1] ?? NULL)) {
        return [];
      }
      if ($token->text === '(') {
        $previous = $tokens[$index - 1] ?? NULL;
        $calls[] = $previous && $previous->is(T_STRING) ? strtolower($previous->text) : '';
      }
      elseif ($token->text === ')') {
        array_pop($calls);
      }
      elseif ($this->isPdo($token) && ($tokens[$index + 1] ?? NULL)?->is(T_DOUBLE_COLON)) {
        $constant = $tokens[$index + 2] ?? NULL;
        if (!$constant || !$constant->is(T_STRING) || !str_starts_with($constant->text, 'FETCH_') || str_starts_with($constant->text, 'FETCH_ORI_')) {
          continue;
        }
        $is_option = ($tokens[$index - 1] ?? NULL)?->is(T_DOUBLE_ARROW)
          && preg_match('/^[\'"]fetch[\'"]$/', $tokens[$index - 2]->text ?? '');
        if ((in_array(end($calls), array_map('strtolower', self::METHODS), TRUE) || $is_option) && !str_contains($lines[$token->line - 1], 'FetchAs')) {
          $results[] = [$constant->text, $token->line];
        }
      }
    }
    return $results;
  }

  /**
   * Checks whether a token is the name of the PDO class.
   *
   * @param \PhpToken|null $token
   *   The token.
   *
   * @return bool
   *   TRUE if the token names the PDO class.
   */
  private function isPdo(?\PhpToken $token): bool {
    return $token !== NULL && in_array($token->text, ['PDO', '\PDO'], TRUE);
  }

  /**
   * Finds the PHP files in a directory.
   *
   * Test fixtures and code of other projects are skipped.
   *
   * @param string $path
   *   The directory to search.
   *
   * @return string[]
   *   The paths of the PHP files.
   */
  private function getFiles(string $path): array {
    $files = [];
    $ignore_directories = array_merge(
      ['dist', 'libraries', 'node_modules', 'vendor'],
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    foreach ($this->fileFinder->getPaths($path) as $item) {
      if ($this->fileFinder->isDirectory($item)) {
        if (!in_array(basename($item), $ignore_directories, TRUE) && !preg_match('~/tests/fixtures$~', $item)) {
          $files = array_merge($files, $this->getFiles($item));
        }
      }
      elseif (preg_match('/\.(php|module|theme|profile|inc|install|engine)$/', $item)) {
        $files[] = $item;
      }
    }
    return $files;
  }

}
