<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;

/**
 * The $_SESSION deprecation analyzer.
 *
 * Drupal 11.2 deprecated storing values directly in $_SESSION, and Drupal 12
 * does not support it. Core only reports this at the end of a request that
 * stored a value, and PHPStan can't find it, because $_SESSION is a plain
 * array.
 */
final class SessionDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The message for a $_SESSION key written in the code.
   */
  private const MESSAGE = '%s is used directly. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use the get(), set() and remove() methods of $request->getSession() instead. See https://www.drupal.org/node/3518527.';

  /**
   * The message for a $_SESSION key that is computed at runtime.
   */
  private const MESSAGE_DYNAMIC = 'A custom key of $_SESSION is used directly. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use the get(), set() and remove() methods of $request->getSession() instead. See https://www.drupal.org/node/3518527.';

  /**
   * The keys of the session bags in core, which stay allowed.
   *
   * These are the storage keys of the attribute bag, the flash bag and the
   * metadata bag. \Drupal\Core\Session\SessionManager::save() allows them.
   */
  private const ALLOWED_KEYS = ['_sf2_attributes', '_symfony_flashes', '_sf2_meta'];

  /**
   * Constructs a new $_SESSION deprecation analyzer.
   *
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   */
  public function __construct(
    private readonly FileFinder $fileFinder,
  ) {}

  /**
   * Analyzes $_SESSION keys in an extension.
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
      if (!str_contains($code, '$_SESSION')) {
        continue;
      }
      foreach ($this->findSessionKeys($code) as [$message, $line]) {
        $deprecations[] = new DeprecationMessage($message, str_replace(DRUPAL_ROOT . '/', '', $file), $line, 'SessionDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Finds custom keys of $_SESSION.
   *
   * Reads and writes of a key are reported, once for each line and key.
   * Uses of the whole $_SESSION array, and the keys of the core session bags,
   * are not reported.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return array
   *   A list of messages and line numbers.
   */
  private function findSessionKeys(string $code): array {
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
    foreach ($tokens as $index => $token) {
      if (!$token->is(T_VARIABLE) || $token->text !== '$_SESSION' || ($tokens[$index + 1] ?? NULL)?->text !== '[') {
        continue;
      }
      $key = $tokens[$index + 2] ?? NULL;
      if ($key && $key->is(T_CONSTANT_ENCAPSED_STRING) && ($tokens[$index + 3] ?? NULL)?->text === ']') {
        if (in_array(substr($key->text, 1, -1), self::ALLOWED_KEYS, TRUE)) {
          continue;
        }
        $message = sprintf(self::MESSAGE, '$_SESSION[' . $key->text . ']');
      }
      else {
        $message = self::MESSAGE_DYNAMIC;
      }
      $results[$token->line . ':' . $message] = [$message, $token->line];
    }
    return array_values($results);
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
