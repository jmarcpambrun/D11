<?php

declare(strict_types=1);

namespace Drupal\eca_gitlab_api\Service;

/**
 * Parses GitLab comment bodies for project-defined slash commands.
 *
 * Line-anchored at column 0, case-sensitive command name, word-boundary
 * required, double-quoted multi-word args, first match wins. Stateless and
 * dependency-free.
 */
final class SlashCommandParser {

  /**
   * Parses a comment body for a slash command.
   *
   * @param string $body
   *   The comment body.
   * @param array<string, array{label: string, args: array<int, array{name: string, required?: bool}>}> $vocabulary
   *   The slash-command vocabulary.
   */
  public function parse(string $body, array $vocabulary): ?SlashCommandMatch {
    if ($body === '' || $vocabulary === []) {
      return NULL;
    }
    $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

    foreach ($lines as $line) {
      if ($line === '' || $line[0] !== '/') {
        continue;
      }
      $match = $this->parseLine($line, $vocabulary);
      if ($match !== NULL) {
        return $match;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  private function parseLine(string $line, array $vocabulary): ?SlashCommandMatch {
    $line = rtrim($line);

    // Word chars plus ':' and '-' so namespaced commands like /ai:needsWork,
    // /priority::high, /needs-review match. '.' excluded so /triage. matches
    // /triage.
    if (!preg_match('/^\/(\w[\w:-]*)(.*)$/', $line, $m)) {
      return NULL;
    }
    $name = $m[1];
    $rest = $m[2];

    if (!isset($vocabulary[$name])) {
      return NULL;
    }
    $argsSpec = $vocabulary[$name]['args'] ?? [];

    $values = $this->tokenizeArgs($rest);

    $args = [];
    foreach ($argsSpec as $i => $spec) {
      $value = $values[$i] ?? '';
      if ($value === '') {
        if (!empty($spec['required'])) {
          return NULL;
        }
        continue;
      }
      $args[$spec['name']] = $value;
    }
    return new SlashCommandMatch($name, $args);
  }

  /**
   * Tokenizes a slash-command argument string.
   *
   * @return list<string>
   *   Parsed argument values, in positional order.
   */
  private function tokenizeArgs(string $rest): array {
    $rest = trim($rest);
    if ($rest === '') {
      return [];
    }
    $rest = preg_replace('/^[^\w"]+/', '', $rest) ?? '';
    if ($rest === '') {
      return [];
    }
    $out = [];
    if (preg_match_all('/"([^"]*)"|(\S+)/', $rest, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $m) {
        $out[] = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
      }
    }
    return $out;
  }

}
