<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Theme\ThemeCommonElements;
use Twig\Error\SyntaxError;
use Twig\Source;
use Twig\Token;

/**
 * The template deprecation analyzer.
 *
 * Drupal only reports these deprecations when a template is rendered, and
 * some not at all:
 * - A theme hook with a 'deprecated' key in hook_theme(). Templates that
 *   override it, render arrays that use it and preprocess functions for it
 *   are not reported, but break when the theme hook is removed.
 * - Template variables in the 'deprecations' variable of a theme hook, set in
 *   hook_theme(), in the initial preprocess callback or in
 *   hook_preprocess_HOOK(). Only templates that are rendered report them.
 * - Theme suggestions in the '__DEPRECATED' key of the suggestions, set in
 *   hook_theme_suggestions_HOOK() or hook_theme_suggestions_HOOK_alter().
 *   Only templates that are rendered with the suggestion report them.
 * - Templates with the Twig deprecated tag. A template that overrides them
 *   doesn't have the tag, and templates that include them only report it
 *   when rendered.
 * - Templates and components of deprecated or obsolete extensions, like the
 *   "@claro" Twig namespace.
 * - Single-directory components with a deprecated or obsolete status, which
 *   are not reported at all.
 * - The #item_attributes property of image formatter render arrays, which is
 *   only reported when the image is rendered.
 */
final class TemplateDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The extensions of the template and PHP files to check.
   */
  private const FILE_EXTENSIONS = ['twig', 'php', 'module', 'inc', 'install', 'theme', 'profile'];

  /**
   * The message for #item_attributes on image formatter render arrays.
   */
  private const ITEM_ATTRIBUTES = 'The #item_attributes property of an image formatter render array is used. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use #attributes instead. See https://www.drupal.org/node/3554585.';

  /**
   * Data about deprecated templates of the site, built on first use.
   *
   * @var array{hooks: array<string, array{string, string}>, templates: array<string, string>, variables: array<string, array<string, string>>, tagged: array<string, array{string, string}>, components: array<string, array{string, string}>}|null
   */
  private ?array $data = NULL;

  /**
   * Deprecations from hook implementations, per theme hook, built on first use.
   *
   * @var array<string, array{variables: array<string, array{string, string}>, suggestions: array<string, array{string, bool, string}>}>
   */
  private array $hookDeprecations = [];

  /**
   * Constructs a new template deprecation analyzer.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   The theme extension list.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The Twig environment.
   * @param \Drupal\upgrade_status\DeprecatedExtensionDescriber $deprecatedExtensionDescriber
   *   The deprecated extension describer.
   * @param \Drupal\upgrade_status\FileFinder $fileFinder
   *   The file finder.
   * @param \Drupal\Component\Plugin\PluginManagerInterface|null $componentPluginManager
   *   The single-directory component plugin manager, if there is one.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ThemeExtensionList $themeExtensionList,
    private readonly TwigEnvironment $twig,
    private readonly DeprecatedExtensionDescriber $deprecatedExtensionDescriber,
    private readonly FileFinder $fileFinder,
    private readonly ?PluginManagerInterface $componentPluginManager = NULL,
  ) {}

  /**
   * Analyzes the templates and PHP code of a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   A list of deprecation messages.
   */
  public function analyze(Extension $extension): array {
    $project_dir = DRUPAL_ROOT . '/' . $extension->getPath();
    $data = $this->getData($project_dir);
    $deprecations = [];
    foreach ($this->getFiles($project_dir) as $file) {
      $error_path = str_replace(DRUPAL_ROOT . '/', '', $file);
      $code = file_get_contents($file);
      $messages = str_ends_with($file, '.twig') ?
        $this->analyzeTemplate($file, $code, $data, $project_dir) :
        $this->analyzePhp($code, $data, $project_dir);
      foreach ($messages as [$message, $line]) {
        $deprecations[] = new DeprecationMessage($message, $error_path, $line, 'TemplateDeprecationAnalyzer');
      }
    }
    return $deprecations;
  }

  /**
   * Finds deprecated templates that a Twig template overrides or uses.
   *
   * @param string $file
   *   The path of the template.
   * @param string $code
   *   The template code.
   * @param array $data
   *   The data from ::getData().
   * @param string $project_dir
   *   The directory of the project.
   *
   * @return array<array{string, int}>
   *   The messages and their lines.
   */
  private function analyzeTemplate(string $file, string $code, array $data, string $project_dir): array {
    $messages = [];
    $name = basename($file);

    // A template file overrides the theme hook with the same name, and its
    // suggestions, like node--article.html.twig for node.html.twig.
    if (str_ends_with($name, '.html.twig')) {
      $template = explode('--', substr($name, 0, -10))[0];
      if (isset($data['templates'][$template])) {
        $hook = $data['templates'][$template];
        if (isset($data['hooks'][$hook])) {
          $messages[] = ["The $name template overrides the $hook theme hook. {$data['hooks'][$hook][0]}", 1];
        }
        $hook_deprecations = $this->getHookDeprecations($hook, $project_dir);
        $variables = ($data['variables'][$hook] ?? []) + $hook_deprecations['variables'];
        // A template for a suggestion has the name of the suggestion, like
        // node--view--frontpage.html.twig for node__view__frontpage.
        $suggestion = strtr(substr($name, 0, -10), '-', '_');
        foreach ($hook_deprecations['suggestions'] as $deprecated => [$message, $prefix]) {
          if ($prefix ? str_starts_with($suggestion, $deprecated) && $suggestion !== $deprecated : $suggestion === $deprecated) {
            $messages[] = ["The $name template is for the $suggestion theme suggestion. $message", 1];
            break;
          }
        }
      }
      if (isset($data['tagged'][$name]) && $data['tagged'][$name][1] !== $file) {
        $messages[] = ["The $name template overrides a deprecated template. {$data['tagged'][$name][0]}", 1];
      }
    }

    try {
      $stream = $this->twig->tokenize(new Source($code, $name, $file));
    }
    catch (SyntaxError) {
      // The Twig deprecation analyzer reports syntax errors.
      return $messages;
    }
    $tokens = [];
    while (!$stream->isEOF()) {
      $tokens[] = $stream->next();
    }

    // Template names, component IDs and Twig namespaces are strings, like
    // {% include '@claro/details.html.twig' %} or include('mercury:card').
    foreach ($tokens as $token) {
      if ($token->test(Token::STRING_TYPE) && ($message = $this->describeReference((string) $token->getValue(), $data, $project_dir))) {
        $messages[] = [$message, $token->getLine()];
      }
    }

    // Deprecated variables of the theme hook that this template overrides.
    if (isset($hook) && !empty($variables)) {
      $found = [];
      foreach ($this->getVariableUses($tokens) as [$variable, $line]) {
        if (isset($variables[$variable]) && !isset($found[$variable][$line])) {
          $found[$variable][$line] = TRUE;
          $message = $variables[$variable];
          $messages[] = ["The $variable variable of the $hook theme hook is used. $message", $line];
        }
      }
    }
    return $messages;
  }

  /**
   * Finds the variables that a template reads.
   *
   * @param \Twig\Token[] $tokens
   *   The tokens of the template.
   *
   * @return array<array{string, int}>
   *   The variable names and their lines.
   */
  private function getVariableUses(array $tokens): array {
    $uses = [];
    $in_tag = FALSE;
    foreach ($tokens as $index => $token) {
      if ($token->test(Token::VAR_START_TYPE) || $token->test(Token::BLOCK_START_TYPE)) {
        $in_tag = TRUE;
        continue;
      }
      if ($token->test(Token::VAR_END_TYPE) || $token->test(Token::BLOCK_END_TYPE)) {
        $in_tag = FALSE;
        continue;
      }
      if (!$in_tag || !$token->test(Token::NAME_TYPE)) {
        continue;
      }
      $previous = $tokens[$index - 1] ?? NULL;
      $next = $tokens[$index + 1] ?? NULL;
      // Skip tag names, attributes, filters, tests, functions, named
      // arguments, hash keys and names that a tag defines.
      if ($previous === NULL || $previous->test(Token::BLOCK_START_TYPE)
        || $previous->test(Token::PUNCTUATION_TYPE, ['.', '|'])
        || $previous->test(Token::OPERATOR_TYPE, ['.', '?.', '|', 'is', 'is not'])
        || $previous->test(Token::NAME_TYPE, ['set', 'for', 'block', 'macro', 'as', 'import'])
        || $previous->test(Token::PUNCTUATION_TYPE, ',') && $this->isLoopTarget($tokens, $index)
        || ($next !== NULL && ($next->test(Token::PUNCTUATION_TYPE, ['(', ':']) || $next->test(Token::OPERATOR_TYPE, '=')))) {
        continue;
      }
      $uses[] = [(string) $token->getValue(), $token->getLine()];
    }
    return $uses;
  }

  /**
   * Checks whether a name is the second target of a for loop.
   *
   * @param \Twig\Token[] $tokens
   *   The tokens of the template.
   * @param int $index
   *   The index of the name, which follows a comma.
   *
   * @return bool
   *   TRUE for "value" in {% for key, value in items %}.
   */
  private function isLoopTarget(array $tokens, int $index): bool {
    return isset($tokens[$index - 3]) && $tokens[$index - 3]->test(Token::NAME_TYPE, 'for');
  }

  /**
   * Finds deprecated theme hooks and components that PHP code uses.
   *
   * @param string $code
   *   The PHP code.
   * @param array $data
   *   The data from ::getData().
   * @param string $project_dir
   *   The directory of the project.
   *
   * @return array<array{string, int}>
   *   The messages and their lines.
   */
  private function analyzePhp(string $code, array $data, string $project_dir): array {
    $messages = [];
    $patterns = [
      // Render arrays, like '#theme' => 'block_content_add_list__custom'.
      'theme' => '/[\'"]#theme[\'"]\s*=>\s*[\'"]([a-z0-9_]+)[\'"]/',
      // Preprocess and suggestion functions, and Hook attributes for them.
      'function' => '/\bfunction\s+\w+?_(?:preprocess|theme_suggestions)_([a-z0-9_]+?)(?:_alter)?\s*\(/',
      'attribute' => '/#\[\s*\\\\?(?:[\w\\\\]*\\\\)?Hook\(\s*[\'"](?:preprocess|theme_suggestions)_([a-z0-9_]+?)(?:_alter)?[\'"]/',
      // Component render elements, like '#component' => 'mercury:card'.
      'component' => '/[\'"]#component[\'"]\s*=>\s*[\'"]([a-z0-9_]+:[a-z0-9_-]+)[\'"]/',
    ];
    foreach ($patterns as $type => $pattern) {
      preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
      foreach ($matches[1] as [$value, $offset]) {
        $line = substr_count($code, "\n", 0, $offset) + 1;
        if ($type === 'component') {
          $message = $this->describeReference($value, $data, $project_dir);
        }
        else {
          $hook = $this->getDeprecatedHook($value, $data);
          $message = $hook === NULL ? NULL : "The $hook theme hook is used. {$data['hooks'][$hook][0]}";
        }
        if ($message !== NULL) {
          $messages[] = [$message, $line];
        }
      }
    }
    foreach ($this->findItemAttributes($code) as $line) {
      $messages[] = [self::ITEM_ATTRIBUTES, $line];
    }
    return $messages;
  }

  /**
   * Finds the #item_attributes property of image formatter render arrays.
   *
   * Other theme hooks can have an item_attributes variable too, so only files
   * that mention an image formatter are checked.
   *
   * @param string $code
   *   The PHP code.
   *
   * @return int[]
   *   The lines that use the property.
   */
  private function findItemAttributes(string $code): array {
    if (!str_contains($code, '#item_attributes') || !preg_match('/image_?formatter/i', $code)) {
      return [];
    }
    try {
      $tokens = \PhpToken::tokenize($code);
    }
    catch (\ParseError $e) {
      return [];
    }
    $lines = [];
    foreach ($tokens as $token) {
      if ($token->is(T_CONSTANT_ENCAPSED_STRING) && in_array($token->text, ["'#item_attributes'", '"#item_attributes"'], TRUE)) {
        $lines[$token->line] = $token->line;
      }
    }
    return array_values($lines);
  }

  /**
   * Finds the deprecated theme hook of a theme hook name or suggestion.
   *
   * @param string $name
   *   A theme hook name, like node or node__article.
   * @param array $data
   *   The data from ::getData().
   *
   * @return string|null
   *   The deprecated theme hook, or NULL.
   */
  private function getDeprecatedHook(string $name, array $data): ?string {
    foreach ($data['hooks'] as $hook => $info) {
      if ($name === $hook || str_starts_with($name, $hook . '__')) {
        return $hook;
      }
    }
    return NULL;
  }

  /**
   * Describes a string in a template that refers to something deprecated.
   *
   * @param string $value
   *   The string, like a template name or a component ID.
   * @param array $data
   *   The data from ::getData().
   * @param string $project_dir
   *   The directory of the project.
   *
   * @return string|null
   *   The message, or NULL if the string doesn't refer to anything deprecated.
   */
  private function describeReference(string $value, array $data, string $project_dir): ?string {
    if (isset($data['components'][$value])) {
      return "The $value component is used. {$data['components'][$value][0]}";
    }
    if (preg_match('/^@([a-z0-9_]+)\//', $value, $matches) || preg_match('/^([a-z0-9_]+):[a-z0-9_-]+$/', $value, $matches)) {
      if ($description = $this->deprecatedExtensionDescriber->describe($matches[1], $project_dir)) {
        $kind = $value[0] === '@' ? 'template' : 'component';
        return "The $value $kind is from $description";
      }
    }
    if (str_ends_with($value, '.html.twig')) {
      $name = basename($value);
      $template = substr($name, 0, -10);
      if (isset($data['templates'][$template], $data['hooks'][$data['templates'][$template]])) {
        $hook = $data['templates'][$template];
        return "The $name template of the $hook theme hook is used. {$data['hooks'][$hook][0]}";
      }
      if (isset($data['tagged'][$name])) {
        return "The $name template is used. {$data['tagged'][$name][0]}";
      }
    }
    return NULL;
  }

  /**
   * Collects the deprecated theme hooks, templates and components.
   *
   * @param string $project_dir
   *   The directory of the project. Its own deprecations are left out, because
   *   a project can use what it deprecates.
   *
   * @return array
   *   Deprecated theme hooks with their message and directory, the theme hook
   *   of each template name, deprecated variables of theme hooks, templates
   *   with the deprecated tag, and deprecated components.
   */
  private function getData(string $project_dir): array {
    if ($this->data === NULL) {
      $this->data = [
        'hooks' => [],
        'templates' => [],
        'variables' => [],
        'tagged' => [],
        'components' => [],
      ];
      $this->collectThemeHooks();
      $this->collectTaggedTemplates();
      $this->collectComponents();
    }
    $outside_project = static fn (array $item): bool => !str_starts_with($item[1] . '/', $project_dir . '/');
    return [
      'hooks' => array_filter($this->data['hooks'], $outside_project),
      'tagged' => array_filter($this->data['tagged'], $outside_project),
      'components' => array_filter($this->data['components'], $outside_project),
    ] + $this->data;
  }

  /**
   * Collects deprecated theme hooks and template variables.
   */
  private function collectThemeHooks(): void {
    $definitions = [];
    if (class_exists(ThemeCommonElements::class)) {
      $definitions[] = [ThemeCommonElements::commonElements(), DRUPAL_ROOT . '/core'];
    }
    // Drupal 10 has a function instead.
    elseif (function_exists($function = 'drupal_common_theme')) {
      $definitions[] = [$function(), DRUPAL_ROOT . '/core'];
    }
    $this->moduleHandler->invokeAllWith('theme', function (callable $hook, string $module) use (&$definitions): void {
      $path = $this->moduleExtensionList->getPath($module);
      try {
        $definitions[] = [$hook([], 'module', $module, $path), DRUPAL_ROOT . '/' . $path];
      }
      catch (\Throwable) {
        // A broken hook_theme() would also break the theme registry.
      }
    });

    foreach ($definitions as [$hooks, $directory]) {
      foreach (is_array($hooks) ? $hooks : [] as $hook => $info) {
        if (!is_array($info)) {
          continue;
        }
        $hook = (string) $hook;
        $template = is_string($info['template'] ?? NULL) ? basename($info['template']) : strtr($hook, '_', '-');
        $this->data['templates'][$template] ??= $hook;
        if (is_string($info['deprecated'] ?? NULL)) {
          $this->data['hooks'][$hook] = [$this->formatMessage($info['deprecated']), $directory];
        }
        $variables = $info['variables']['deprecations'] ?? [];
        $variables += $this->findVariableDeprecations($this->getSource($info['initial preprocess'] ?? NULL)[0] ?? '');
        foreach (is_array($variables) ? $variables : [] as $variable => $message) {
          if (is_string($message)) {
            $this->data['variables'][$hook][(string) $variable] = $this->formatMessage($message);
          }
        }
      }
    }
  }

  /**
   * Collects deprecations that hook implementations set for a theme hook.
   *
   * Some deprecations are not in hook_theme() or the initial preprocess
   * callback. For example, the Views module deprecates the 'view' variable of
   * node templates in hook_preprocess_node(), and the node__view__* theme
   * suggestions in hook_theme_suggestions_node_alter().
   *
   * @param string $hook
   *   The theme hook, like node.
   * @param string $project_dir
   *   The directory of the project. Its own deprecations are left out, because
   *   a project can use what it deprecates.
   *
   * @return array{variables: array<string, string>, suggestions: array<string, array{string, bool}>}
   *   The messages of deprecated variables, keyed by variable name, and the
   *   messages of deprecated theme suggestions, keyed by suggestion, with
   *   whether the suggestion is a prefix, like node__view__ for
   *   node__view__frontpage.
   */
  private function getHookDeprecations(string $hook, string $project_dir): array {
    if (!isset($this->hookDeprecations[$hook])) {
      $deprecations = ['variables' => [], 'suggestions' => []];
      foreach (['preprocess_' . $hook, 'theme_suggestions_' . $hook, 'theme_suggestions_' . $hook . '_alter'] as $implemented_hook) {
        $preprocess = str_starts_with($implemented_hook, 'preprocess_');
        try {
          $this->moduleHandler->invokeAllWith($implemented_hook, function (callable $callback) use (&$deprecations, $hook, $preprocess): void {
            [$code, $file] = $this->getSource($callback) ?? ['', ''];
            if ($preprocess) {
              foreach ($this->findVariableDeprecations($code) as $variable => $message) {
                $deprecations['variables'][$variable] ??= [$this->formatMessage($message), $file];
              }
              return;
            }
            foreach ($this->findSuggestionDeprecations($code) as $suggestion => [$message, $prefix]) {
              $message = $this->formatSuggestionMessage($message, $hook);
              $deprecations['suggestions'][$suggestion] ??= [$message, $prefix, $file];
            }
          });
        }
        catch (\Throwable) {
          // A broken hook implementation would also break rendering.
        }
      }
      $this->hookDeprecations[$hook] = $deprecations;
    }
    $outside_project = static fn (array $item): bool => !str_starts_with(end($item) . '/', $project_dir . '/');
    return [
      'variables' => array_map(static fn (array $item): string => $item[0], array_filter($this->hookDeprecations[$hook]['variables'], $outside_project)),
      'suggestions' => array_map(static fn (array $item): array => [$item[0], $item[1]], array_filter($this->hookDeprecations[$hook]['suggestions'], $outside_project)),
    ];
  }

  /**
   * Finds the source code of a callback.
   *
   * @param mixed $callback
   *   The callback, like 'Drupal\node\Hook\NodeThemeHooks:preprocessNode', a
   *   function name, an array callable or a closure.
   *
   * @return array{string, string}|null
   *   The code of the callback and the path of its file, or NULL.
   */
  private function getSource(mixed $callback): ?array {
    // Drupal 11.1 calls procedural hook implementations through
    // \Drupal\Core\Extension\ProceduralCall::__call().
    if (is_array($callback) && is_string($callback[1] ?? NULL) && function_exists($callback[1]) && !method_exists($callback[0] ?? '', $callback[1])) {
      $callback = $callback[1];
    }
    try {
      if (is_string($callback) && str_contains($callback, ':')) {
        $reflection = new \ReflectionMethod(...explode(':', str_replace('::', ':', $callback), 2));
      }
      elseif (is_array($callback) && count($callback) === 2 && is_string($callback[1] ?? NULL) && (is_object($callback[0] ?? NULL) || is_string($callback[0] ?? NULL))) {
        $reflection = new \ReflectionMethod($callback[0], $callback[1]);
      }
      elseif ($callback instanceof \Closure || (is_string($callback) && function_exists($callback))) {
        $reflection = new \ReflectionFunction($callback);
      }
      else {
        return NULL;
      }
    }
    catch (\ReflectionException) {
      return NULL;
    }
    $file = $reflection->getFileName();
    if (!$file || !is_file($file)) {
      return NULL;
    }
    $lines = array_slice(file($file), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1);
    return [implode('', $lines), $file];
  }

  /**
   * Finds the variables that preprocess code deprecates.
   *
   * @param string $code
   *   The code of a preprocess callback.
   *
   * @return array<string, string>
   *   The deprecation messages, keyed by variable name.
   */
  private function findVariableDeprecations(string $code): array {
    preg_match_all('/\$variables\[[\'"]deprecations[\'"]\]\[[\'"](\w+)[\'"]\]\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\')/', $code, $matches, PREG_SET_ORDER);
    $deprecations = [];
    foreach ($matches as $match) {
      $deprecations[$match[1]] = stripslashes($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
    }
    return $deprecations;
  }

  /**
   * Finds the theme suggestions that suggestion hook code deprecates.
   *
   * Core deprecates suggestions like this:
   * @code
   * $suggestions['__DEPRECATED']['node__view__' . $id] = 'Theme suggestion
   *   node__view__' . $id . ' is deprecated in drupal:11.3.0 and is removed
   *   from drupal:13.0.0. See https://www.drupal.org/node/3541462';
   * @endcode
   *
   * @param string $code
   *   The code of a theme suggestion hook implementation.
   *
   * @return array<string, array{string, bool}>
   *   The deprecation messages and whether the suggestion is a prefix, keyed
   *   by suggestion. A suggestion followed by more code, like
   *   'node__view__' . $id, is a prefix.
   */
  private function findSuggestionDeprecations(string $code): array {
    preg_match_all('/\$\w+\[[\'"]__DEPRECATED[\'"]\]\[\s*([\'"])([a-z0-9_]+)\1((?:[^\[\]]|\[[^\[\]]*\])*)\]\s*=\s*([^;]+);/', $code, $matches, PREG_SET_ORDER);
    $deprecations = [];
    foreach ($matches as $match) {
      // The message can be built from strings and variables. The strings are
      // enough to find the versions and the link.
      preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"/', $match[4], $strings, PREG_SET_ORDER);
      $message = implode('', array_map(static fn (array $string): string => stripslashes($string[2] ?? $string[1]), $strings));
      $deprecations[$match[2]] ??= [$message, trim($match[3]) !== ''];
    }
    return $deprecations;
  }

  /**
   * Formats the deprecation message of a theme suggestion.
   *
   * @param string $message
   *   A message like "Theme suggestion node__view__frontpage is deprecated in
   *   drupal:11.3.0 and is removed from drupal:13.0.0. See
   *   https://www.drupal.org/node/3541462".
   * @param string $hook
   *   The theme hook of the suggestion.
   *
   * @return string
   *   The message with the versions, what to do and the link.
   */
  private function formatSuggestionMessage(string $message, string $hook): string {
    $message = $this->formatMessage($message);
    // Core doesn't say what to do instead.
    if (preg_match('/^(Deprecated (?:in|as of) drupal:\S+ and is removed from drupal:\S+\.)\s*(See \S+)?$/', $message, $matches)) {
      $message = trim("$matches[1] Use another theme suggestion, or add one with hook_theme_suggestions_{$hook}_alter(). " . ($matches[2] ?? ''));
    }
    return $message;
  }

  /**
   * Collects templates that have the Twig deprecated tag.
   */
  private function collectTaggedTemplates(): void {
    foreach ($this->moduleExtensionList->getList() + $this->themeExtensionList->getList() as $extension) {
      $templates = DRUPAL_ROOT . '/' . $extension->getPath() . '/templates';
      foreach (is_dir($templates) ? $this->getFiles($templates, ['twig']) : [] as $file) {
        if (preg_match('/\{%-?\s*deprecated\s+(["\'])(.*?)\1/s', file_get_contents($file), $matches)) {
          $this->data['tagged'][basename($file)] = [$this->formatMessage(stripslashes($matches[2])), $file];
        }
      }
    }
  }

  /**
   * Collects single-directory components with a deprecated or obsolete status.
   */
  private function collectComponents(): void {
    if ($this->componentPluginManager === NULL) {
      return;
    }
    try {
      $definitions = $this->componentPluginManager->getDefinitions();
    }
    catch (\Throwable) {
      return;
    }
    foreach ($definitions as $id => $definition) {
      $status = $definition['status'] ?? '';
      if (in_array($status, ['deprecated', 'obsolete'], TRUE)) {
        $path = $definition['path'] ?? '';
        $directory = str_starts_with($path, '/') ? $path : DRUPAL_ROOT . '/' . $path;
        $message = "The component is $status and can be removed. Use another component.";
        $this->data['components'][(string) $id] = [$message, $directory];
      }
    }
  }

  /**
   * Formats a deprecation message, so the version numbers are categorized.
   *
   * @param string $message
   *   A message like "'teaser' is deprecated in drupal:11.1.0 and is removed
   *   in drupal:12.0.0. Use 'view_mode' instead.".
   *
   * @return string
   *   The message, like "Deprecated in drupal:11.1.0 and is removed from
   *   drupal:12.0.0. Use 'view_mode' instead.".
   */
  private function formatMessage(string $message): string {
    $message = trim($message);
    if (preg_match('/\bis deprecated ((?:in|as of) drupal:.*)$/is', $message, $matches)) {
      $message = 'Deprecated ' . $matches[1];
    }
    return preg_replace('/\bis removed in drupal:/', 'is removed from drupal:', $message);
  }

  /**
   * Finds the template and PHP files in a directory.
   *
   * Test fixtures and code of other projects are skipped.
   *
   * @param string $path
   *   The directory to search.
   * @param string[] $extensions
   *   The file extensions to find.
   *
   * @return string[]
   *   The paths of the files.
   */
  private function getFiles(string $path, array $extensions = self::FILE_EXTENSIONS): array {
    $files = [];
    $ignore_directories = array_merge(
      ['config', 'css', 'dist', 'js', 'libraries', 'node_modules', 'vendor'],
      Settings::get('file_scan_ignore_directories', ['bower_components', 'node_modules']),
    );
    // Hidden files and directories, like .git, are not analyzed.
    foreach ($this->fileFinder->getPaths($path) as $file) {
      $entry = basename($file);
      if ($this->fileFinder->isDirectory($file)) {
        if (!in_array($entry, $ignore_directories, TRUE) && !preg_match('~/tests/fixtures$~', $file)) {
          $files = array_merge($files, $this->getFiles($file, $extensions));
        }
      }
      elseif (in_array(pathinfo($entry, PATHINFO_EXTENSION), $extensions, TRUE) && !str_ends_with($entry, '.api.php')) {
        $files[] = $file;
      }
    }
    return $files;
  }

}
