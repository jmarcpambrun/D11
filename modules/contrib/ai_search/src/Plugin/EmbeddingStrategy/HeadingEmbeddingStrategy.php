<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_search\Attribute\EmbeddingStrategy;
use Drupal\search_api\IndexInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Plugin implementation of the heading-based embedding strategy.
 *
 * Splits body content on the configured ATX-style Markdown heading levels
 * (H2 by default) produced by the HTML-to-Markdown conversion pipeline. Each
 * heading section becomes its own chunk with its heading lineage prepended as
 * a breadcrumb prefix, ensuring every chunk is self-contained.
 *
 * Content appearing before the first boundary heading (title, teaser, meta
 * details) is emitted as its own preamble chunk.
 *
 * Sections too large for one chunk are packed by Markdown block (paragraph,
 * list, table, code fence) instead of being cut mid-block. Oversized tables
 * are split by row with the header row repeated.
 *
 * Falls back to overlap chunking (the parent strategy) when content lacks the
 * required heading structure.
 *
 * Extends LongChunkEmbeddingBase (rather than EmbeddingBase directly, as on
 * 2.0.x) so that large heading-heavy documents benefit from the same
 * batched/long-running indexing support as the Enriched Embedding Strategy.
 *
 * @internal
 *   Chunking behavior may change; this class is not an extension point.
 */
#[EmbeddingStrategy(
  id: 'heading_chunks',
  label: new TranslatableMarkup('Heading-Based Embedding Strategy'),
  description: new TranslatableMarkup('Splits content on headings (H2 by default), producing semantically coherent chunks aligned with document structure. Falls back to overlap chunking for content without sufficient heading structure.'),
)]
class HeadingEmbeddingStrategy extends LongChunkEmbeddingBase {

  /**
   * Heading levels that may be used as chunk boundaries.
   *
   * @var int[]
   */
  protected const ALLOWED_HEADING_LEVELS = [2, 3, 4];

  /**
   * The heading levels used as chunk boundaries, in ascending order.
   *
   * @var int[]
   */
  protected array $headingLevels = [2];

  /**
   * Lazily-created CommonMark parser used for heading detection.
   *
   * @var \League\CommonMark\Parser\MarkdownParser|null
   */
  protected ?MarkdownParser $markdownParser = NULL;

  /**
   * {@inheritdoc}
   */
  public function init(string $embedding_engine, string $chat_model, array $configuration): void {
    parent::init($embedding_engine, $chat_model, $configuration);
    $levels = static::normalizeHeadingLevels($configuration['heading_levels'] ?? []);
    if ($levels) {
      $this->headingLevels = $levels;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfigurationValues(): array {
    return parent::getDefaultConfigurationValues() + ['heading_levels' => [2]];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationSubform(array $configuration): array {
    if (empty($configuration)) {
      $configuration = $this->getDefaultConfigurationValues();
    }

    $form['heading_strategy_warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      'content' => [
        '#markup' => $this->t('<strong>This strategy assumes well-structured content.</strong> It works best when body fields use consistent headings to divide topics. For content without clear heading structure, this strategy automatically falls back to the standard overlap approach. Consider using the Enriched Embedding Strategy if your content structure is unpredictable.'),
      ],
    ];

    $options = [];
    foreach (static::ALLOWED_HEADING_LEVELS as $level) {
      $options[$level] = $this->t('H@level', ['@level' => $level]);
    }
    $form['heading_levels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Heading levels to split on'),
      '#options' => $options,
      '#default_value' => static::normalizeHeadingLevels($configuration['heading_levels'] ?? []) ?: [2],
      '#description' => $this->t('Each chunk is prefixed with its parent headings, e.g. "## Section > ### Subsection".'),
      '#required' => TRUE,
      '#element_validate' => [[static::class, 'validateHeadingLevels']],
    ];

    $form += parent::getConfigurationSubform($configuration);
    return $form;
  }

  /**
   * Element validate callback storing heading levels as a list of integers.
   *
   * @param array $element
   *   The checkboxes element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateHeadingLevels(array &$element, FormStateInterface $form_state): void {
    $form_state->setValueForElement($element, static::normalizeHeadingLevels($element['#value'] ?? []));
  }

  /**
   * Normalizes heading levels to a sorted list of allowed integers.
   *
   * @param mixed $levels
   *   A list of levels, or a checkboxes value keyed by level.
   *
   * @return int[]
   *   The allowed levels, in ascending order.
   */
  protected static function normalizeHeadingLevels(mixed $levels): array {
    if (!is_array($levels)) {
      return [];
    }
    $levels = array_map('intval', array_filter($levels));
    $levels = array_values(array_unique(array_intersect($levels, static::ALLOWED_HEADING_LEVELS)));
    sort($levels);
    return $levels;
  }

  /**
   * {@inheritdoc}
   */
  protected function getChunks(string $title, string $main_content, string $contextual_content, bool $title_in_contextual = FALSE, ?IndexInterface $index = NULL): array {
    // Mirror the parent's title-exclusion logic before using $title.
    $exclude_title = FALSE;
    if ($index !== NULL) {
      $index_config = $this->configFactory->get('ai_search.index.' . $index->id())->getRawData();
      $exclude_title = $index_config['exclude_title'] ?? FALSE;
    }
    if ($title_in_contextual || $exclude_title) {
      $title = '';
    }

    // Strip top-level H1 headings — the entity title is prepended by
    // prepareChunkText(), so an H1 in the body (e.g. rendered page title)
    // would otherwise duplicate it in the preamble chunk. Using the
    // CommonMark block AST (rather than a line regex) means a "# " that
    // merely appears inside a fenced code block or blockquote is correctly
    // left alone, since it is not a top-level Heading node there.
    $main_content_lines = explode("\n", $main_content);
    $h1_line_numbers = [];
    foreach ($this->getTopLevelHeadings($main_content) as $heading) {
      if ($heading['level'] === 1) {
        for ($line = $heading['start']; $line <= $heading['end']; $line++) {
          $h1_line_numbers[$line] = TRUE;
        }
      }
    }
    $content_lines = array_values(array_diff_key($main_content_lines, $h1_line_numbers));
    $content = trim(implode("\n", $content_lines));

    // Split into preamble (before the first boundary heading) and body.
    $preamble = '';
    $body = $content;
    $first_boundary = NULL;
    foreach ($this->getTopLevelHeadings($content) as $heading) {
      if (in_array($heading['level'], $this->headingLevels, TRUE)) {
        $first_boundary = $heading;
        break;
      }
    }
    if ($first_boundary !== NULL) {
      $lines = explode("\n", $content);
      $preamble = trim(implode("\n", array_slice($lines, 0, $first_boundary['start'])));
      $body = trim(implode("\n", array_slice($lines, $first_boundary['start'])));
    }

    $body_headings = $this->getTopLevelHeadings($body);

    // Fall back to overlap chunking when heading structure is insufficient.
    if ($this->countBoundaries($body_headings) < 2) {
      return parent::getChunks($title, $main_content, $contextual_content, $title_in_contextual, $index);
    }

    $chunks = [];

    $sections = $this->buildSections($body, $body_headings);
    if ($preamble !== '') {
      array_unshift($sections, ['prefix' => '', 'body' => $preamble]);
    }

    foreach ($sections as $section) {
      foreach ($this->packSection($title, $section['prefix'], $section['body'], $contextual_content) as $section_text) {
        $chunks[] = $this->prepareChunkText($title, $section_text, $contextual_content);
      }
    }

    return array_filter($chunks);
  }

  /**
   * Splits a section into chunk texts that fit the chunk size.
   *
   * @param string $title
   *   The title prepended to each chunk.
   * @param string $prefix
   *   The heading lineage, or an empty string for the preamble.
   * @param string $body
   *   The section body.
   * @param string $contextual_content
   *   The contextual content appended to each chunk.
   *
   * @return string[]
   *   Section texts (prefix and body) to pass to prepareChunkText().
   */
  protected function packSection(string $title, string $prefix, string $body, string $contextual_content): array {
    $with_prefix = fn (string $text) => $prefix === '' ? $text : $prefix . "\n\n" . $text;

    if ($this->tokenizer->countTokens($this->prepareChunkText($title, $with_prefix($body), $contextual_content)) <= $this->chunkSize) {
      return [$with_prefix($body)];
    }

    // Body budget: what is left after the title, prefix and contextual
    // content, capped by the main content share.
    $overhead = $this->tokenizer->countTokens($this->prepareChunkText($title, $with_prefix(''), $contextual_content));
    $max_main = (int) ($this->chunkSize * (1 - $this->contextualContentMaxPercentage / 100));
    $budget = max(1, min($max_main, $this->chunkSize - $overhead));

    return array_map($with_prefix, $this->packBlocks($body, $budget));
  }

  /**
   * Packs top-level Markdown blocks into pieces within a token budget.
   *
   * @param string $text
   *   The Markdown text.
   * @param int $budget
   *   The maximum number of tokens per piece.
   *
   * @return string[]
   *   The pieces.
   */
  protected function packBlocks(string $text, int $budget): array {
    $pieces = [];
    $current = '';
    foreach ($this->getTopLevelBlocks($text) as $block) {
      if ($this->tokenizer->countTokens($block['text']) > $budget) {
        if ($current !== '') {
          $pieces[] = $current;
          $current = '';
        }
        // Last resort for a single oversized block.
        $split = $block['table']
          ? $this->splitTable($block['text'], $budget)
          : $this->textChunker->chunkText($block['text'], $budget, $this->chunkMinOverlap);
        array_push($pieces, ...$split);
        continue;
      }
      $candidate = $current === '' ? $block['text'] : $current . "\n\n" . $block['text'];
      if ($current !== '' && $this->tokenizer->countTokens($candidate) > $budget) {
        $pieces[] = $current;
        $candidate = $block['text'];
      }
      $current = $candidate;
    }
    if ($current !== '') {
      $pieces[] = $current;
    }
    return $pieces;
  }

  /**
   * Splits a Markdown table by row, repeating the header in each piece.
   *
   * @param string $table
   *   The Markdown table.
   * @param int $budget
   *   The maximum number of tokens per piece.
   *
   * @return string[]
   *   The pieces.
   */
  protected function splitTable(string $table, int $budget): array {
    $lines = explode("\n", trim($table));
    $header = implode("\n", array_slice($lines, 0, 2));
    $pieces = [];
    $current = $header;
    foreach (array_slice($lines, 2) as $row) {
      $candidate = $current . "\n" . $row;
      if ($this->tokenizer->countTokens($candidate) <= $budget) {
        $current = $candidate;
        continue;
      }
      if ($current !== $header) {
        $pieces[] = $current;
      }
      $current = $header . "\n" . $row;
      if ($this->tokenizer->countTokens($current) > $budget) {
        // A single row too large even on its own.
        array_push($pieces, ...$this->textChunker->chunkText($current, $budget, $this->chunkMinOverlap));
        $current = $header;
      }
    }
    if ($current !== $header) {
      $pieces[] = $current;
    }
    return $pieces;
  }

  /**
   * Count heading boundaries at the configured levels.
   *
   * @param array<array{level: int, start: int, end: int}> $headings
   *   Top-level headings found in the body text (after preamble extraction).
   *
   * @return int
   *   Number of boundary headings found.
   */
  protected function countBoundaries(array $headings): int {
    return count(array_filter(
      $headings,
      fn (array $heading) => in_array($heading['level'], $this->headingLevels, TRUE),
    ));
  }

  /**
   * Split body into sections delimited by the configured heading levels.
   *
   * Headings at other levels remain inside the section body. Each section's
   * prefix is its heading lineage, e.g. "## Section > ### Subsection".
   *
   * @param string $body
   *   The body text starting from the first boundary heading.
   * @param array<array{level: int, start: int, end: int}> $headings
   *   Top-level headings found in $body, in document order.
   *
   * @return array<array{prefix: string, body: string}>
   *   Each entry has a 'prefix' and a 'body'.
   */
  protected function buildSections(string $body, array $headings): array {
    $lines = explode("\n", $body);
    $boundaries = array_values(array_filter(
      $headings,
      fn (array $heading) => in_array($heading['level'], $this->headingLevels, TRUE),
    ));
    $sections = [];
    $lineage = [];
    $count = count($boundaries);

    foreach ($boundaries as $i => $heading) {
      // Drop the lineage at this level and deeper.
      $lineage = array_filter($lineage, fn (int $level) => $level < $heading['level'], ARRAY_FILTER_USE_KEY);
      $lineage[$heading['level']] = trim($lines[$heading['start']]);

      $start = $heading['end'] + 1;
      $end = ($i + 1 < $count) ? $boundaries[$i + 1]['start'] - 1 : count($lines) - 1;
      $content = trim(implode("\n", array_slice($lines, $start, max(0, $end - $start + 1))));
      if ($content !== '') {
        $sections[] = ['prefix' => implode(' > ', $lineage), 'body' => $content];
      }
    }

    return $sections;
  }

  /**
   * Get the top-level ATX headings found in Markdown content.
   *
   * Only headings that are direct children of the document (not nested
   * inside a blockquote, list item, or fenced/indented code block) are
   * returned, matching the previous regex-based behavior while correctly
   * ignoring lines that merely look like headings inside code fences.
   *
   * @param string $content
   *   The Markdown content to parse.
   *
   * @return array<array{level: int, start: int, end: int}>
   *   Headings in document order. 'start'/'end' are 0-indexed line numbers
   *   suitable for indexing directly into explode("\n", $content) — Heading
   *   nodes report 1-indexed line numbers, so the conversion happens here
   *   once rather than at every call site.
   */
  protected function getTopLevelHeadings(string $content): array {
    $document = $this->getMarkdownParser()->parse($content);
    $headings = [];
    foreach ($document->children() as $node) {
      if ($node instanceof Heading) {
        $headings[] = [
          'level' => $node->getLevel(),
          'start' => $node->getStartLine() - 1,
          'end' => $node->getEndLine() - 1,
        ];
      }
    }
    return $headings;
  }

  /**
   * Gets the top-level Markdown blocks of the content.
   *
   * @param string $content
   *   The Markdown content.
   *
   * @return array<array{text: string, table: bool}>
   *   Blocks in document order, as their original source text.
   */
  protected function getTopLevelBlocks(string $content): array {
    $lines = explode("\n", $content);
    $blocks = [];
    foreach ($this->getMarkdownParser()->parse($content)->children() as $node) {
      $start = $node->getStartLine();
      $end = $node->getEndLine();
      if ($start === NULL || $end === NULL) {
        continue;
      }
      $text = trim(implode("\n", array_slice($lines, $start - 1, $end - $start + 1)));
      if ($text !== '') {
        $blocks[] = ['text' => $text, 'table' => $node instanceof Table];
      }
    }
    return $blocks;
  }

  /**
   * Get the CommonMark parser, creating it on first use.
   *
   * @return \League\CommonMark\Parser\MarkdownParser
   *   The parser, configured with just the core CommonMark block/inline
   *   rules — no HTML rendering is needed since we only read the AST.
   */
  protected function getMarkdownParser(): MarkdownParser {
    if ($this->markdownParser === NULL) {
      $environment = new Environment();
      $environment->addExtension(new CommonMarkCoreExtension());
      $environment->addExtension(new TableExtension());
      $this->markdownParser = new MarkdownParser($environment);
    }
    return $this->markdownParser;
  }

}
