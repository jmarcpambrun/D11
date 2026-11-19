<?php

namespace Drupal\upgrade_status;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Format scan results for display or export.
 */
class ScanResultFormatter {

  use StringTranslationTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a \Drupal\upgrade_status\Controller\ScanResultFormatter.
   *
   * @param \Drupal\upgrade_status\ScanResultStorage $scanResultStorage
   *   The storage of the analysis results.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\upgrade_status\ProjectCollector $projectCollector
   *   The project collector.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory, for the site name.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service, for file names.
   * @param \Drupal\upgrade_status\ToolUpdateChecker $toolUpdateChecker
   *   The tool update checker, for the versions of the analysis tools.
   */
  public function __construct(
    protected ScanResultStorage $scanResultStorage,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
    RendererInterface $renderer,
    protected ProjectCollector $projectCollector,
    protected ConfigFactoryInterface $configFactory,
    protected TransliterationInterface $transliteration,
    protected ToolUpdateChecker $toolUpdateChecker,
  ) {
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->renderer = $renderer;
  }

  /**
   * The next steps of contributed projects that are best fixed on Drupal.org.
   */
  private const COLLABORATE_STEPS = [
    ProjectCollector::NEXT_UPDATE,
    ProjectCollector::NEXT_COLLABORATE,
  ];

  /**
   * Get scanning result for an extension.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   Drupal extension object.
   *
   * @return null|array
   *   Scan results array or null if no scan results are saved.
   */
  public function getRawResult(Extension $extension) {
    return $this->scanResultStorage->get($extension->getName());
  }

  /**
   * Formats the results of an extension, like on its result page.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   Drupal extension object.
   * @param bool $interactive
   *   TRUE for the result page, with the export menu and the link back to
   *   the report. FALSE for exports, which show the title instead.
   *
   * @return array
   *   Build array.
   */
  public function formatResult(Extension $extension, bool $interactive = TRUE) {
    $result = $this->getRawResult($extension);
    $info = $extension->info;
    $name = $extension->getName();
    $next_major = ProjectCollector::getDrupalCoreMajorVersion() + 1;
    $summary = $this->projectCollector->getProjectSummary($extension);

    $build = [
      '#theme' => 'upgrade_status_project_results',
      '#title' => $interactive ? NULL : $this->getTitle($extension),
      '#meta' => [
        $summary['type'],
        Markup::create('<span class="us-mono">' . Html::escape($name) . '</span>'),
      ],
      '#notes' => [],
      '#sections' => [],
    ];
    if ($interactive) {
      $build['#attached']['library'][] = 'upgrade_status/upgrade_status.admin';
      $build['#actions'] = [
        'export' => ReportBuilder::buildMenu($this->t('Export'), [
          'html' => $this->t('Export as HTML'),
          'markdown' => $this->t('Export as Markdown'),
          'codeclimate' => $this->t('Export as Code Climate JSON'),
          'checkstyle' => $this->t('Export as Checkstyle XML'),
          'csv' => $this->t('Export as CSV'),
        ], 'upgrade_status.export', 'down', ['project_machine_name' => $name], TRUE),
      ];
      $build['#back'] = [
        '#type' => 'link',
        '#title' => Markup::create(Icon::render('arrow-left', 16) . Html::escape((string) $this->t('Back to Drupal @version upgrade status', ['@version' => $next_major]))),
        '#url' => Url::fromRoute('upgrade_status.report'),
        '#attributes' => ['class' => ['us-back']],
      ];
    }

    // A newer release is the first thing to try.
    if (($info['upgrade_status_update'] ?? NULL) === ProjectCollector::UPDATE_AVAILABLE && !empty($info['upgrade_status_update_compatible'])) {
      $text = !empty($info['upgrade_status_update_security']) ?
        $this->t('@version is compatible with Drupal @next_major, and a security update is also available for the installed @installed. Update, then analyze again to see what is left.', [
          '@version' => $info['upgrade_status_update_version'],
          '@next_major' => $next_major,
          '@installed' => $info['version'] ?? '',
        ]) :
        $this->t('@version is compatible with Drupal @next_major. Update, then analyze again to see what is left.', [
          '@version' => $info['upgrade_status_update_version'],
          '@next_major' => $next_major,
        ]);
      $build['#callout'] = [
        'icon' => ['#markup' => Icon::render('check-circle', 28)],
        'text' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['us-callout__text']],
          'heading' => [
            '#markup' => '<h2 class="us-callout__heading">' . $this->t('Update to @project @version before fixing anything', [
              '@project' => $info['name'],
              '@version' => $info['upgrade_status_update_version'],
            ]) . '</h2>',
          ],
          'text' => ['#markup' => '<p>' . $text . '</p>'],
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['us-callout__actions']],
          'notes' => [
            '#type' => 'link',
            '#title' => $this->t('Release notes'),
            '#url' => Url::fromUri($info['upgrade_status_update_link']),
            '#attributes' => ['class' => ['button', 'button--primary']],
          ],
          'updates' => $interactive ? [
            '#type' => 'link',
            '#title' => $this->t('Available updates'),
            '#url' => Url::fromRoute('update.status'),
          ] : [],
        ],
      ];
    }
    elseif (!empty($summary['issues_url']) && in_array($info['upgrade_status_next'] ?? '', self::COLLABORATE_STEPS, TRUE)) {
      // Contributed projects without a compatible release are best fixed in
      // their Drupal.org issues, not only on this site.
      $build['#callout'] = [
        'icon' => ['#markup' => Icon::render('users-three', 28)],
        'text' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['us-callout__text']],
          'heading' => [
            '#markup' => '<h2 class="us-callout__heading">' . $this->t('Work on the fixes with the community') . '</h2>',
          ],
          'text' => [
            '#markup' => '<p>' . $this->getCommunityNote($info['name']) . '</p>',
          ],
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['us-callout__actions']],
          'issues' => [
            '#type' => 'link',
            '#title' => $this->t('Review issues'),
            '#url' => Url::fromUri($summary['issues_url']),
            '#attributes' => ['class' => ['button', 'button--primary']],
          ],
        ],
      ];
    }

    // This project was not analyzed yet or the results were removed.
    if (empty($result)) {
      $build['#notes'][] = [
        '#markup' => $this->t('No analysis results available. <a href="@url">Go to the Upgrade Status form</a>.', [
          // The link is absolute, so it also works in exported files.
          '@url' => Url::fromRoute('upgrade_status.report', [], ['absolute' => TRUE])->toString(),
        ]),
      ];
      return $build;
    }

    $build['#meta'][] = $this->t('Analyzed @date', ['@date' => $this->dateFormatter->format($result['date'])]);
    if ($this->toolUpdateChecker->isOtherThanInstalled($result['versions'] ?? NULL)) {
      $build['#notes'][] = ['#markup' => $this->getOtherVersionsNote($result['versions'])];
    }
    if (!empty($result['data']['totals']['upgrade_status_test_classes_skipped'])) {
      $build['#notes'][] = ['#markup' => $this->getSkippedTestClassesNote()];
    }

    $files = $this->getResultFiles($result);
    if (empty($result['data']['totals']['file_errors']) || empty($files)) {
      $build['#notes'][] = ['#markup' => $this->t('No known issues found.')];
      return $build;
    }

    // Files that only have messages for later versions are not counted with
    // the errors and warnings.
    $split = $result['data']['totals']['upgrade_status_split'] ?? [];
    $problem_files = 0;
    foreach ($files as $errors) {
      foreach ($errors['messages'] as $error) {
        if ($this->getCategory($error) !== 'ignore') {
          $problem_files++;
          break;
        }
      }
    }
    $build['#meta'][] = $problem_files ? $this->t('@errors, @warnings in @files', [
      '@errors' => $this->formatPlural($split['error'] ?? 0, '1 error', '@count errors'),
      '@warnings' => $this->formatPlural($split['warning'] ?? 0, '1 warning', '@count warnings'),
      '@files' => $this->formatPlural($problem_files, '1 file', '@count files'),
    ]) : $this->t('No problems found');

    // Group the messages by category, and then by file.
    $root = $extension->getPath() . '/';
    $groups = [];
    foreach ($files as $filepath => $errors) {
      foreach ($errors['messages'] as $error) {
        $groups[$this->getCategory($error)][$filepath][] = $error;
      }
    }
    $build['#path_note'] = ['#markup' => $this->t('File paths are relative to <code>@path</code>', ['@path' => $this->getDisplayPath(DRUPAL_ROOT . '/' . $root)])];

    $count = fn (string $key): int => array_sum(array_map('count', $groups[$key] ?? []));
    $categories = $this->getCategories($extension);
    $groups_info = [
      'rector' => [
        $categories['rector'][0],
        'wrench',
        'us-status--error',
        'error',
        $this->formatPlural($count('rector'), '1 error', '@count errors'),
        $this->t('drupal-rector can fix these, in @files', ['@files' => $this->listFiles($groups['rector'] ?? [], $root)]),
        $this->t('Avoid some manual work by using <a href="@drupal-rector">drupal-rector to fix issues automatically</a>.', ['@drupal-rector' => 'https://www.drupal.org/project/rector']),
      ],
      'now' => [
        $categories['now'][0],
        'x-circle',
        'us-status--error',
        'error',
        $this->formatPlural($count('now'), '1 error', '@count errors'),
        $this->t('APIs removed in Drupal @version, in @files', [
          '@version' => $next_major,
          '@files' => $this->listFiles($groups['now'] ?? [], $root),
        ]),
        $this->t('These are not covered by automation yet, so fix these by hand. You can also <a href="@drupal-rector">contribute to drupal-rector</a> to provide coverage.', ['@drupal-rector' => 'https://www.drupal.org/project/rector']),
      ],
      'requirements' => [
        $categories['requirements'][0],
        'warning',
        'us-status--warning',
        'warning',
        $this->formatPlural($count('requirements'), '1 warning', '@count warnings'),
        $this->t('In @files', ['@files' => $this->listFiles($groups['requirements'] ?? [], $root)]),
        $categories['requirements'][1],
      ],
      'contrib' => [
        $categories['contrib'][0],
        'warning',
        'us-status--warning',
        'warning',
        $this->formatPlural($count('contrib'), '1 warning', '@count warnings'),
        $this->t('In @files', ['@files' => $this->listFiles($groups['contrib'] ?? [], $root)]),
        $categories['contrib'][1],
      ],
      'uncategorized' => [
        $categories['uncategorized'][0],
        'warning',
        'us-status--warning',
        'warning',
        $this->formatPlural($count('uncategorized'), '1 warning', '@count warnings'),
        $this->t('In @files', ['@files' => $this->listFiles($groups['uncategorized'] ?? [], $root)]),
        $categories['uncategorized'][1],
      ],
      'later' => [
        $categories['later'][0],
        'clock',
        'us-status--neutral',
        'neutral',
        $this->formatPlural($count('later'), '1 warning', '@count warnings'),
        $this->getFixVersionNote($groups['later'] ?? [], ProjectCollector::isContributed($extension)),
        $categories['later'][1],
      ],
      'ignore' => [
        $categories['ignore'][0],
        'minus-circle',
        'us-status--neutral',
        'neutral',
        $this->formatPlural($count('ignore'), '1 notice', '@count notices'),
        $this->t('Removed in later versions, not required for Drupal @version', ['@version' => $next_major]),
        $categories['ignore'][1],
      ],
    ];

    $build['#tiles'] = [];
    $tiles = [
      ['now', ['rector', 'now'], 'x-circle', 'us-status--error', $this->t('Fix now')],
      ['requirements', ['requirements'], 'warning', 'us-status--warning', $categories['requirements'][0]],
      ['contrib', ['contrib'], 'warning', 'us-status--warning', $categories['contrib'][0]],
      ['uncategorized', ['uncategorized'], 'warning', 'us-status--warning', $categories['uncategorized'][0]],
      ['later', ['later'], 'clock', 'us-status--neutral', $categories['later'][0]],
      ['ignore', ['ignore'], 'minus-circle', 'us-status--neutral', $categories['ignore'][0]],
    ];
    $descriptions = [
      'now' => $this->t('Errors, removed in Drupal @version', ['@version' => $next_major]),
      'requirements' => $this->t('Warnings, info file and composer.json'),
      'contrib' => $this->t('Warnings, APIs of contrib code'),
      'uncategorized' => $this->t('Warnings without a Drupal version'),
      'later' => $this->t('Warnings, need a newer core'),
      'ignore' => $this->t('Removed in later versions'),
    ];
    foreach ($tiles as [$anchor, $keys, $icon, $class, $label]) {
      $description = $descriptions[$anchor];
      $tile_count = array_sum(array_map($count, $keys));
      // Fix now links to the automated fixes if there are only those.
      if ($anchor === 'now' && empty($groups['now']) && !empty($groups['rector'])) {
        $anchor = 'rector';
      }
      $build['#tiles'][] = [
        'icon' => Icon::render($icon, 28, NULL, $class),
        'count' => $tile_count,
        'label' => $tile_count ? [
          '#type' => 'link',
          '#title' => $label,
          '#url' => Url::fromUserInput('#us-group-' . $anchor),
        ] : ['#markup' => $label],
        'description' => $description,
      ];
    }

    foreach ($groups_info as $group_key => [$title, $icon, $icon_class, $variant, $pill, $summary_line, $description]) {
      if (empty($groups[$group_key])) {
        continue;
      }
      $section = [
        '#type' => 'details',
        '#id' => 'us-group-' . $group_key,
        '#title' => [
          '#theme' => 'upgrade_status_section_title',
          '#icon' => Icon::render($icon, 18, NULL, $icon_class),
          '#title' => $title,
          '#pill' => $pill,
          '#pill_variant' => $variant,
          '#summary' => $summary_line,
        ],
        '#open' => $group_key !== 'ignore',
        '#attributes' => ['class' => ['us-section', 'us-results-section', 'upgrade-status-project-result-group']],
        'description' => [
          '#markup' => '<p class="us-description">' . $description . '</p>',
        ],
        'files' => [],
      ];
      foreach ($groups[$group_key] as $filepath => $errors) {
        $section['files'][] = $this->buildFile($filepath, $errors, $root, $next_major, $interactive, $group_key === 'ignore');
      }
      $build['#sections'][$group_key] = $section;
    }

    return $build;
  }

  /**
   * Gets the title of an extension in the results.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension.
   *
   * @return \Drupal\Core\Render\Markup
   *   The name, and the version in a lighter style.
   */
  public function getTitle(Extension $extension): Markup {
    $title = Html::escape($extension->info['name']);
    if (!empty($extension->info['version'])) {
      $title .= ' <span class="us-title-version">' . Html::escape($extension->info['version']) . '</span>';
    }
    return Markup::create($title);
  }

  /**
   * Lists up to three files of a group, for its summary line.
   *
   * @param array $files
   *   The messages keyed by the file path.
   * @param string $root
   *   The path of the project relative to the Drupal root, with a trailing
   *   slash.
   *
   * @return string
   *   The file names.
   */
  protected function listFiles(array $files, string $root): string {
    $paths = array_map(fn ($path) => $this->getProjectPath($path, $root), array_keys($files));
    if (count($paths) > 3) {
      return (string) $this->t('@files and @count more', [
        '@files' => implode(', ', array_slice($paths, 0, 3)),
        '@count' => count($paths) - 3,
      ]);
    }
    return ReportBuilder::joinList($paths);
  }

  /**
   * Builds the messages of one file.
   *
   * @param string $filepath
   *   The absolute file path. It may be followed by more text, like
   *   " (in context of class X)".
   * @param array $errors
   *   The messages of the file.
   * @param string $root
   *   The path of the project relative to the Drupal root, with a trailing
   *   slash.
   * @param int $next_major
   *   The next major Drupal version.
   * @param bool $interactive
   *   TRUE for the result page. FALSE for exports, where the button to copy
   *   the path of the file is hidden until the script of the export shows it.
   * @param bool $notices
   *   (optional) Whether the messages are for later versions, so the count is
   *   not a number of problems.
   *
   * @return array
   *   The render array.
   */
  protected function buildFile(string $filepath, array $errors, string $root, int $next_major, bool $interactive = FALSE, bool $notices = FALSE): array {
    $path = $this->getProjectPath($filepath, $root);
    // Emphasize the file name, also before " (in context of ...)".
    preg_match('!^(.*/)?([^/]+?)( \(in context of .*)?$!', $path, $parts);
    $header = Icon::render('file-code', 16) . '<span class="us-mono"><span class="us-file__dir">' . Html::escape($parts[1] ?? '') . '</span><span class="us-file__name">' . Html::escape($parts[2] ?? $path) . '</span>' . Html::escape($parts[3] ?? '') . '</span>';
    if (count($errors) > 1) {
      $count = $notices ?
        $this->formatPlural(count($errors), '1 notice', '@count notices') :
        $this->formatPlural(count($errors), '1 problem', '@count problems');
      $header .= '<span class="us-file__count">' . $count . '</span>';
    }
    // The header shows the path relative to the project. The button copies
    // the path from the root of the codebase, to open the file in an editor.
    $copy_path = preg_replace('/ \(in context of .*$/', '', $this->getDisplayPath($filepath));
    $label = Html::escape((string) $this->t('Copy the path of @file', ['@file' => $parts[2] ?? $path]));
    // On the result page, core's js-show class hides the button without
    // JavaScript. An export has its own script, which shows the button.
    $class = $interactive ? 'us-file__copy js-show"' : 'us-file__copy us-file__copy--export" hidden';
    $header .= '<button type="button" class="' . $class . ' data-us-copy="' . Html::escape($copy_path) . '" title="' . Html::escape((string) $this->t('Copy path')) . '" aria-label="' . $label . '">' . Icon::render('copy', 16, NULL, 'us-icon--copy') . Icon::render('check-circle', 16, NULL, 'us-icon--copied') . '</button>';
    $items = [];
    foreach ($errors as $error) {
      [$message, $pills] = $this->formatMessage($error['message'], $next_major);
      $item = '<li class="us-finding"><span class="us-finding__line us-mono">' . (!empty($error['line']) ? $this->t('Line @line', ['@line' => $error['line']]) : '') . '</span><div class="us-finding__message">' . $message;
      if ($pills) {
        $item .= '<div class="us-finding__pills">' . implode('', $pills) . '</div>';
      }
      $items[] = $item . '</div></li>';
    }
    return [
      '#markup' => Markup::create('<div class="us-file"><h3 class="us-file__header">' . $header . '</h3><ul class="us-findings">' . implode('', $items) . '</ul></div>'),
    ];
  }

  /**
   * Gets the path of a file relative to its project.
   *
   * @param string $filepath
   *   The file path from the results, absolute or relative to the Drupal
   *   root.
   * @param string $root
   *   The path of the project relative to the Drupal root, with a trailing
   *   slash.
   *
   * @return string
   *   The path relative to the project, or to the Drupal root for files
   *   outside the project.
   */
  protected function getProjectPath(string $filepath, string $root): string {
    $path = str_starts_with($filepath, DRUPAL_ROOT . '/') ? substr($filepath, strlen(DRUPAL_ROOT) + 1) : $filepath;
    return str_starts_with($path, $root) ? substr($path, strlen($root)) : $this->getDisplayPath($filepath);
  }

  /**
   * Formats a message for the results page.
   *
   * The deprecation versions become pills. Names of classes, methods and
   * functions, and info file values become code. Links to drupal.org are
   * shortened. The rest of the message stays as the analyzer wrote it.
   *
   * @param string $message
   *   The message.
   * @param int $next_major
   *   The next major Drupal version, whose removals are shown in red.
   *
   * @return array
   *   The message as HTML, and the pills as HTML.
   */
  public function formatMessage(string $message, int $next_major): array {
    $pills = [];
    $message = trim(preg_replace('/\s+/', ' ', $message));
    // The versions are written in a few ways:
    // - "Deprecated in drupal:X and is removed from drupal:Y.", also for
    //   contributed projects, optionally with "and possibly even earlier in
    //   project", or without the removal.
    // - "The service is deprecated in drupal:X and is removed from drupal:Y."
    // - "...: in drupal:X and is removed from drupal:Y.", from PHPStan.
    // - "... is deprecated in drupal:X and an error will be thrown from
    //   drupal:Y." for errors instead of removals.
    $pattern = '/(?<lead>:?\s*\bDeprecated|(?<=deprecated)|:)\s*in (?<p1>[a-z_]+):(?<v1>\d[\w.-]*?)(?: and (?<verb>is removed from|an error will be thrown from) (?<p2>[a-z_]+):(?<v2>\d[\w.-]*?)(?<earlier> and possibly even earlier in (?<p3>\w+))?)?\.(?=\s|$)/';
    $message = preg_replace_callback($pattern, function (array $match) use (&$pills, $next_major): string {
      $version = fn (string $project, string $version): string => $project === 'drupal' ? $version : $project . ' ' . $version;
      $pills[] = '<span class="us-pill us-pill--neutral">' . $this->t('Deprecated in @version', ['@version' => $version($match['p1'], $match['v1'])]) . '</span>';
      if (!empty($match['v2'])) {
        $arguments = ['@version' => $version($match['p2'], $match['v2']), '@project' => $match['p3'] ?? ''];
        if ($match['verb'] === 'is removed from') {
          $label = empty($match['earlier']) ? $this->t('Removed in @version', $arguments) : $this->t('Removed in @version, maybe earlier in @project', $arguments);
        }
        else {
          $label = $this->t('Fails in @version', $arguments);
        }
        $next = $match['p2'] === 'drupal' && (int) $match['v2'] === $next_major;
        $pills[] = '<span class="us-pill us-pill--' . ($next ? 'error' : 'neutral') . '">' . $label . '</span>';
      }
      // A sentence that only had the versions is removed, otherwise it ends.
      if (str_contains($match['lead'], 'Deprecated')) {
        return str_starts_with($match['lead'], ':') ? '. ' : ' ';
      }
      return '.';
    }, $message);
    $message = trim($message);
    if (preg_match('/\s*\(?since symfony(?:\/[\w-]+)? (\d+\.\d+)\)?[:.,]?/i', $message, $match)) {
      $message = trim(str_replace($match[0], ' ', $message));
      $pills[] = '<span class="us-pill us-pill--neutral">' . $this->t('Deprecated in Symfony @version', ['@version' => $match[1]]) . '</span>';
    }
    $message = preg_replace('/\s{2,}/', ' ', $message);

    // Links and code are found in the plain text, then escaped piece by piece.
    $pattern = '!(?<link>https?://\S+?)(?=[.,;)]?(\s|$))|(?<tick>`[^`]+`)|(?<code>core_version_requirement: .+?(?= (to designate|is not))|\\\\?[A-Za-z_][\w]*(\\\\[A-Za-z_]\w*)+(::\$?\w+(\(\))?)?|\b[A-Za-z_]\w*::\$?\w+(\(\))?|\b[A-Za-z_]\w*\(\)|\'[^\'\s]+\'\s=>\s\'[^\']+\'|\'[a-z_]+ ?[a-z_]*\')!';
    $html = '';
    $offset = 0;
    preg_match_all($pattern, $message, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($matches as $found) {
      [$text, $position] = $found[0];
      $html .= Html::escape(substr($message, $offset, $position - $offset));
      if (!empty($found['link'][0]) && $found['link'][1] === $position) {
        $html .= '<a href="' . Html::escape($text) . '">' . Html::escape(preg_replace('!^https?://(www\.)?!', '', $text)) . '</a>';
      }
      elseif (!empty($found['tick'][0]) && $found['tick'][1] === $position) {
        $html .= '<code>' . Html::escape(trim($text, '`')) . '</code>';
      }
      else {
        $html .= '<code>' . Html::escape($text) . '</code>';
      }
      $offset = $position + strlen($text);
    }
    $html .= Html::escape(substr($message, $offset));
    return [Markup::create($html), $pills];
  }

  /**
   * Gets the line under the title of a document.
   *
   * It has the site, the date, and the versions of the analysis tools, which
   * tell what the results can be compared with.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The line, as plain text.
   */
  public function getDocumentMeta(): TranslatableMarkup {
    $site = (string) $this->configFactory->get('system.site')->get('name');
    $arguments = [
      '@site' => $site,
      '@date' => $this->dateFormatter->format($this->time->getRequestTime(), 'medium'),
      '@tools' => $this->toolUpdateChecker->formatVersions($this->toolUpdateChecker->getInstalledVersions()),
    ];
    return $site === '' ?
      $this->t('Made on @date. Analysis tools: @tools.', $arguments) :
      $this->t('For @site, made on @date. Analysis tools: @tools.', $arguments);
  }

  /**
   * Gets the note for results made with other versions of the analysis tools.
   *
   * @param array<string, string> $versions
   *   The versions that the results were made with.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The note.
   */
  protected function getOtherVersionsNote(array $versions): TranslatableMarkup {
    return $this->t('These results were made with other versions of the analysis tools: @tools. Analyze the project again for results of the installed versions.', [
      '@tools' => $this->toolUpdateChecker->formatVersions($versions),
    ]);
  }

  /**
   * Gets the name of an exported file, without the file extension.
   *
   * The name has the site name and the date, in ASCII letters, digits and
   * dashes, so it works on all systems and sorts by date.
   *
   * @param string $kind
   *   What the file has, like 'report' or 'results'.
   * @param string|null $project
   *   The machine name of the project, for the export of one project.
   *
   * @return string
   *   The file name, like upgrade-status-my-site-report-2026-11-11-1420.
   */
  public function getExportFilename(string $kind, ?string $project = NULL): string {
    $site = $this->transliteration->transliterate((string) $this->configFactory->get('system.site')->get('name'), 'en', '');
    $site = trim(substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($site)), 0, 40), '-');
    $date = $this->dateFormatter->format($this->time->getRequestTime(), 'custom', 'Y-m-d-Hi');
    // The name of a recipe is the name of its directory, with any characters.
    $project = is_null($project) ? NULL : trim(preg_replace('/[^A-Za-z0-9_.-]+/', '-', $project), '-');
    $parts = ['upgrade-status', $site, $kind, $project, $date];
    return implode('-', array_filter($parts, fn ($part) => $part !== NULL && $part !== ''));
  }

  /**
   * Builds a file download with the results of extensions.
   *
   * @param array $extensions
   *   Extensions keyed by 'custom' and 'contrib', and then by project name.
   * @param string $format
   *   Either 'html', 'markdown', 'csv', 'checkstyle' or 'codeclimate'. Other
   *   values give HTML.
   * @param string $filename
   *   The file name without the file extension.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response that downloads the file.
   */
  public function buildExportResponse(array $extensions, string $format, string $filename): Response {
    if ($format === 'markdown') {
      $response = new Response($this->formatMarkdownReport(array_merge(...array_values($extensions))));
      $response->headers->set('Content-Type', 'text/markdown; charset=utf-8');
      $filename .= '.md';
    }
    elseif ($format === 'checkstyle') {
      $response = new Response($this->formatCheckstyle(array_merge(...array_values($extensions))));
      $response->headers->set('Content-Type', 'application/xml; charset=utf-8');
      $filename .= '.xml';
    }
    elseif ($format === 'codeclimate') {
      $response = new Response($this->formatCodeClimate(array_merge(...array_values($extensions))));
      $response->headers->set('Content-Type', 'application/json; charset=utf-8');
      $filename .= '.json';
    }
    elseif ($format === 'csv') {
      // The byte order mark tells spreadsheet applications that the file is
      // UTF-8.
      $response = new Response("\xEF\xBB\xBF" . $this->formatCsvResults(array_merge(...array_values($extensions))));
      $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
      $filename .= '.csv';
    }
    else {
      $response = new Response($this->formatHtmlReport($extensions));
      $filename .= '.html';
    }
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

  /**
   * Formats the results of extensions as an HTML document.
   *
   * @param array $extensions
   *   Extensions keyed by 'custom' and 'contrib', and then by project name.
   *
   * @return string
   *   The HTML document, with the results of each extension like on the
   *   result page, without the links to export them.
   */
  public function formatHtmlReport(array $extensions): string {
    $build = [
      '#theme' => 'upgrade_status_html_export',
      '#meta' => $this->getDocumentMeta(),
      '#styles' => ReportBuilder::getStyles(),
      '#script' => ReportBuilder::getExportScript(),
      '#projects' => [],
    ];
    foreach ($extensions as $type => $list) {
      foreach ($list as $name => $extension) {
        $build['#projects'][$type][$name] = $this->formatResult($extension, FALSE);
      }
    }
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Formats the results of extensions as a Markdown document.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   The extensions, in the order to list them.
   *
   * @return string
   *   Markdown text with a title and the results of each extension.
   */
  public function formatMarkdownReport(array $extensions): string {
    $title = (string) $this->t('Upgrade Status results for Drupal @version', ['@version' => ProjectCollector::getDrupalCoreMajorVersion() + 1]);
    $meta = html_entity_decode((string) $this->getDocumentMeta(), ENT_QUOTES | ENT_HTML5);
    $legend = (string) $this->t('Errors are the problems to fix now. Warnings are in the other categories, except "After Drupal @version".', ['@version' => ProjectCollector::getDrupalCoreMajorVersion() + 1]);
    $markdown = '# ' . $title . "\n\n" . $meta . "\n\n" . $legend . "\n";
    foreach ($extensions as $extension) {
      $markdown .= "\n" . $this->formatMarkdownResult($extension);
    }
    return $markdown;
  }

  /**
   * Gets the note about test classes that were not checked.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The note, with the command to install PHPUnit.
   */
  protected function getSkippedTestClassesNote() {
    return $this->t('Test classes were not checked, because PHPUnit is not installed. To check test classes, install the Drupal development dependencies, for example with <code>composer require --dev drupal/core-dev</code>.');
  }

  /**
   * Formats the results of an extension as Markdown.
   *
   * Messages are grouped like in the user interface, and then by file. Lines
   * are not wrapped, so paths and class names stay whole for copying.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension.
   *
   * @return string
   *   Markdown text.
   */
  public function formatMarkdownResult(Extension $extension): string {
    $result = $this->getRawResult($extension);
    $info = $extension->info;
    $lines = [
      '## ' . $info['name'] . (!empty($info['version']) ? ' ' . $info['version'] : '') . ' (' . $extension->getName() . ')',
      '',
    ];
    $summary = [];
    if (($info['upgrade_status_type'] ?? NULL) === ProjectCollector::TYPE_CUSTOM) {
      $summary[] = (string) $this->t('Custom project.');
    }
    elseif (($info['upgrade_status_type'] ?? NULL) === ProjectCollector::TYPE_CONTRIB) {
      $summary[] = (string) $this->t('Contributed project.');
    }
    // The same advice as on the result page of the project.
    $project_summary = $this->projectCollector->getProjectSummary($extension);
    $community_note = '';
    if (!empty($project_summary['issues_url']) && in_array($info['upgrade_status_next'] ?? '', self::COLLABORATE_STEPS, TRUE)) {
      // The text is plain here, so names and URLs are not escaped.
      $community_note = html_entity_decode($this->getCommunityNote($info['name']) . ' ' . $this->t('Issues: @url', ['@url' => $project_summary['issues_url']]), ENT_QUOTES | ENT_HTML5);
    }

    // This project was not yet scanned or the scan results were removed.
    if (empty($result)) {
      $summary[] = (string) $this->t('No analysis results.');
      $lines[] = implode(' ', $summary);
      return implode("\n", $lines) . "\n";
    }

    $summary[] = (string) $this->t('Analyzed @date.', ['@date' => $this->dateFormatter->format($result['date'])]);
    if ($this->toolUpdateChecker->isOtherThanInstalled($result['versions'] ?? NULL)) {
      $summary[] = html_entity_decode((string) $this->getOtherVersionsNote($result['versions']), ENT_QUOTES | ENT_HTML5);
    }
    if (!empty($result['data']['totals']['upgrade_status_test_classes_skipped'])) {
      $summary[] = strip_tags(str_replace(['<code>', '</code>'], '`', (string) $this->getSkippedTestClassesNote()));
    }

    if (empty($result['data']['totals']['file_errors']) || !is_array($result['data']['files'])) {
      $summary[] = (string) $this->t('No known issues found.');
      $lines[] = implode(' ', $summary);
      return implode("\n", $lines) . "\n";
    }

    if (!empty($result['data']['totals']['upgrade_status_split']['error'])) {
      $summary[] = (string) $this->formatPlural($result['data']['totals']['upgrade_status_split']['error'], '@count error found.', '@count errors found.');
    }
    if (!empty($result['data']['totals']['upgrade_status_split']['warning'])) {
      $summary[] = (string) $this->formatPlural($result['data']['totals']['upgrade_status_split']['warning'], '@count warning found.', '@count warnings found.');
    }
    // Messages for later versions are not errors or warnings. The number is
    // on top of those, like on the report.
    $later = 0;
    foreach ($result['data']['files'] as $errors) {
      foreach ($errors['messages'] as $error) {
        $later += (int) ($this->getCategory($error) === 'ignore');
      }
    }
    if (empty($result['data']['totals']['upgrade_status_split']['error']) && empty($result['data']['totals']['upgrade_status_split']['warning'])) {
      $summary[] = (string) $this->t('No problems found.');
    }
    if ($later) {
      $summary[] = (string) $this->formatPlural($later, '+1 after Drupal @version.', '+@count after Drupal @version.', ['@version' => ProjectCollector::getDrupalCoreMajorVersion() + 1]);
    }
    $lines[] = implode(' ', $summary);
    if ($community_note) {
      array_push($lines, '', $community_note);
    }

    $groups = [];
    foreach ($result['data']['files'] as $filepath => $errors) {
      $short_path = $this->getDisplayPath($filepath);
      foreach ($errors['messages'] as $error) {
        $message = trim(preg_replace('/\s+/', ' ', $error['message']));
        if (!empty($error['line'])) {
          $message = implode(' ', [$this->t('Line @line:', ['@line' => $error['line']]), $message]);
        }
        $groups[$this->getCategory($error)][$short_path][] = $message;
      }
    }

    foreach ($this->getCategories($extension) as $group_key => [$title, $description]) {
      if (empty($groups[$group_key])) {
        continue;
      }
      array_push($lines, '', '### ' . $title, '', (string) $description, '');
      if ($group_key === 'later') {
        array_push($lines, (string) $this->getFixVersionNote($groups[$group_key], ProjectCollector::isContributed($extension)), '');
      }
      foreach ($groups[$group_key] as $short_path => $messages) {
        $lines[] = '- ' . $short_path;
        foreach ($messages as $message) {
          $lines[] = '  - ' . $message;
        }
      }
    }

    return implode("\n", $lines) . "\n";
  }

  /**
   * Formats the results of extensions as Checkstyle XML.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   The extensions, in the order to list them.
   *
   * @return string
   *   The XML document, with one file element per file with messages.
   */
  public function formatCheckstyle(array $extensions): string {
    $xml = new \SimpleXMLElement("<?xml version='1.0'?><checkstyle/>");
    foreach ($extensions as $extension) {
      foreach ($this->getResultFiles($this->getRawResult($extension)) as $filepath => $errors) {
        $file_xml = $xml->addChild('file');
        $file_xml->addAttribute('name', str_replace(DRUPAL_ROOT . '/', '', $filepath));
        foreach ($errors['messages'] as $error) {
          $severity = 'error';
          if (($error['upgrade_status_category'] ?? '') == 'ignore') {
            $severity = 'info';
          }
          elseif (($error['upgrade_status_category'] ?? '') == 'later') {
            $severity = 'warning';
          }
          $error_xml = $file_xml->addChild('error');
          $error_xml->addAttribute('line', (string) $error['line']);
          $error_xml->addAttribute('message', $error['message']);
          $error_xml->addAttribute('severity', $severity);
        }
      }
    }
    return $xml->asXML();
  }

  /**
   * Formats the results of extensions as Code Climate issues JSON.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   The extensions, in the order to list them.
   *
   * @return string
   *   The JSON list of issues.
   */
  public function formatCodeClimate(array $extensions): string {
    $report = [];
    foreach ($extensions as $extension) {
      foreach ($this->getResultFiles($this->getRawResult($extension)) as $filepath => $errors) {
        foreach ($errors['messages'] as $error) {
          $category = $error['upgrade_status_category'] ?? '';
          $severity = 'major';
          // We downgrade to 'info' severity, if:
          // - It has the ignore/later category, as these issues shouldn't be
          //   fixed now.
          // - It is not an error, but something unable to detect.
          if (
            in_array($category, ['ignore', 'later'], TRUE) ||
            str_contains($error['message'], 'Whether the library is deprecated cannot be checked.') ||
            // Results that were stored before the text changed have the earlier
            // text of the same message.
            str_contains($error['message'], 'Cannot decide if it is deprecated or not.') ||
            str_contains($error['message'], 'Cannot check deprecated library use.')
          ) {
            $severity = 'info';
          }
          // We downgrade to 'minor' severity, if the category has no Drupal
          // version, because we might not need to fix it now.
          elseif (in_array($category, ['uncategorized', 'requirements', 'contrib'], TRUE)) {
            $severity = 'minor';
          }
          $report[] = [
            'type' => 'issue',
            'check_name' => $error['analyzer'] ?? 'upgrade_status',
            'categories' => ['Compatibility'],
            'description' => $extension->getName() . ' - ' . $error['message'],
            'fingerprint' => hash('sha256', implode([$filepath, $error['line'], $error['message']])),
            'severity' => $severity,
            'location' => [
              'path' => str_replace(DRUPAL_ROOT . '/', '', $filepath),
              'lines' => [
                'begin' => $error['line'] ?: 0,
              ],
            ],
          ];
        }
      }
    }
    return json_encode($report, JSON_PRETTY_PRINT);
  }

  /**
   * Formats the results of extensions as CSV, with one row per message.
   *
   * Each row starts with the project columns of ::formatCsvProjects(), so the
   * rows can be filtered and sorted in a spreadsheet. A project without
   * messages has one row, with empty message columns.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   Projects from ProjectCollector::collectProjects(), in the order to list
   *   them.
   *
   * @return string
   *   The CSV text, with a header row.
   */
  public function formatCsvResults(array $extensions): string {
    $header = [
      ...$this->getCsvProjectHeader(),
      $this->t('Category'),
      $this->t('File'),
      $this->t('Line'),
      $this->t('Message'),
    ];
    $rows = [$header];
    foreach ($extensions as $extension) {
      $titles = array_map(static fn (array $category) => $category[0], $this->getCategories($extension));
      $project = $this->getCsvProjectRow($extension);
      $result = $this->getRawResult($extension);
      $messages = [];
      foreach ($this->getResultFiles($result) as $filepath => $errors) {
        foreach ($errors['messages'] as $error) {
          $messages[] = [
            ...$project,
            $titles[$this->getCategory($error)],
            $this->getDisplayPath($filepath),
            $error['line'] ?? '',
            trim(preg_replace('/\s+/', ' ', $error['message'])),
          ];
        }
      }
      array_push($rows, ...($messages ?: [[...$project, '', '', '', '']]));
    }
    return $this->toCsv($rows);
  }

  /**
   * Formats projects as CSV, with one row per project.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   Projects from ProjectCollector::collectProjects(), in the order to list
   *   them.
   *
   * @return string
   *   The CSV text, with a header row. After the project columns, the number
   *   of problems in each category is listed. These are empty for a project
   *   that was not scanned.
   */
  public function formatCsvProjects(array $extensions): string {
    $titles = $this->getCategoryTitles();
    $rows = [[...$this->getCsvProjectHeader(), ...array_values($titles)]];
    foreach ($extensions as $extension) {
      $counts = array_fill_keys(array_keys($titles), '');
      if ($result = $this->getRawResult($extension)) {
        $counts = array_fill_keys(array_keys($titles), 0);
        foreach ($this->getResultFiles($result) as $errors) {
          foreach ($errors['messages'] as $error) {
            $counts[$this->getCategory($error)]++;
          }
        }
      }
      $rows[] = [...$this->getCsvProjectRow($extension), ...array_values($counts)];
    }
    return $this->toCsv($rows);
  }

  /**
   * Gets the files with messages from scan results.
   *
   * @param array|null $result
   *   The scan results, or NULL if there are none.
   *
   * @return array
   *   The messages of each file, keyed by the file path.
   */
  protected function getResultFiles(?array $result): array {
    return is_array($result['data']['files'] ?? NULL) ? $result['data']['files'] : [];
  }

  /**
   * Gets the header of the project columns in CSV exports.
   *
   * @return array
   *   The column labels, the same as on the report page where possible.
   */
  protected function getCsvProjectHeader(): array {
    $next_major = ProjectCollector::getDrupalCoreMajorVersion() + 1;
    return [
      $this->t('Group'),
      $this->t('Project'),
      $this->t('Machine name'),
      $this->t('Type'),
      $this->t('Status'),
      $this->t('Local version'),
      $this->t('Local @next_major-ready', ['@next_major' => $next_major]),
      $this->t('Analysis date'),
      $this->t('Problems'),
      $this->t('Drupal.org version'),
      $this->t('Drupal.org @next_major-ready', ['@next_major' => $next_major]),
      $this->t('Drupal.org issues'),
    ];
  }

  /**
   * Gets the project columns of a project in CSV exports.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   A project from ProjectCollector::collectProjects().
   *
   * @return array
   *   The values for the columns of ::getCsvProjectHeader(). The scan date and
   *   the number of problems are empty if the project was not scanned.
   */
  protected function getCsvProjectRow(Extension $extension): array {
    $summary = $this->projectCollector->getProjectSummary($extension);
    $result = $this->getRawResult($extension);
    $next_steps = $this->projectCollector->getNextStepInfo();
    return [
      $next_steps[$extension->info['upgrade_status_next'] ?? ''][0] ?? '',
      $extension->info['name'],
      $extension->getName(),
      $summary['type'],
      $summary['status'],
      $summary['version'],
      $summary['ready'],
      !empty($result['date']) ? $this->dateFormatter->format($result['date'], 'custom', 'Y-m-d H:i:s') : '',
      $summary['result_count'] ?? '',
      $summary['update_version'],
      $summary['update_ready'],
      $summary['issues_url'] ?? '',
    ];
  }

  /**
   * Converts rows to CSV text.
   *
   * @param array $rows
   *   The rows, each an array of cell values.
   *
   * @return string
   *   The CSV text.
   */
  protected function toCsv(array $rows): string {
    $handle = fopen('php://memory', 'r+');
    foreach ($rows as $row) {
      $cells = [];
      foreach ($row as $cell) {
        $cell = (string) $cell;
        // Spreadsheet applications run cells that start with these characters
        // as formulas. A leading quote keeps them as text.
        if ($cell !== '' && str_contains("=+-@\t\r", $cell[0])) {
          $cell = "'" . $cell;
        }
        $cells[] = $cell;
      }
      fputcsv($handle, $cells, ',', '"', '');
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return $csv;
  }

  /**
   * Gets the categories of messages, in the order of the results.
   *
   * All formats of the results take the titles from here. The descriptions
   * are plain text. The result page has descriptions with links for the
   * first two categories.
   *
   * @param \Drupal\Core\Extension\Extension|null $extension
   *   (optional) The project of the results. The title and the description
   *   of the messages with a fix for a newer Drupal core version depend on
   *   whether it is a contributed project. Without a project, the title is
   *   for both kinds of projects.
   *
   * @return array
   *   The title and the description of each category, keyed by the categories
   *   of ::getCategory().
   */
  protected function getCategories(?Extension $extension = NULL): array {
    $next_major = ProjectCollector::getDrupalCoreMajorVersion() + 1;
    // The fix of these messages only works from a newer Drupal core version.
    // What to do first is different for a contributed project and for a
    // custom project, so the title and the description are too.
    if (!$extension) {
      $later = [
        $this->t('Update core or raise core requirement and fix'),
        $this->t('These are removed in Drupal @version. The fixes only work from a newer Drupal core version.', ['@version' => $next_major]),
      ];
    }
    elseif (ProjectCollector::isContributed($extension)) {
      $later = [
        $this->t('Raise core requirement and fix'),
        $this->t('These are removed in Drupal @version. Fixing these now makes the project incompatible with older Drupal core versions that are still supported.', ['@version' => $next_major]),
      ];
    }
    else {
      $later = [
        $this->t('Update core and fix'),
        $this->t('These are removed in Drupal @version. The fixes do not work on the Drupal core version of this site yet.', ['@version' => $next_major]),
      ];
    }
    return [
      'rector' => [
        $this->t('Fix now with rector'),
        $this->t('drupal-rector can fix these automatically. Install it with `composer require --dev palantirnet/drupal-rector`, copy `vendor/palantirnet/drupal-rector/rector.php` next to the composer.json file of the site, then run `vendor/bin/rector process @path`. See https://www.drupal.org/project/rector.', [
          '@path' => $extension ? rtrim($this->getDisplayPath(DRUPAL_ROOT . '/' . $extension->getPath()), '/') : $this->t('with the path of the project'),
        ]),
      ],
      'now' => [
        $this->t('Fix now manually'),
        $this->t('drupal-rector does not fix these yet. Fix these manually.'),
      ],
      'requirements' => [
        $this->t('Update requirements'),
        $this->t('Requirements and dependencies in info files, composer.json files and recipes that do not work with Drupal @version. Update these once the code is compatible.', ['@version' => $next_major]),
      ],
      'contrib' => [
        $this->t('Consider contrib deprecations'),
        $this->t('Deprecated APIs of contributed modules and themes. These are removed in a release of that module or theme, which may or may not be required for Drupal @version.', ['@version' => $next_major]),
      ],
      'uncategorized' => [
        $this->t('Check manually'),
        $this->t('Messages without a Drupal version, like parse errors and code that could not be checked. These may not be about Drupal @version at all. A class or function that was not found usually means that a dependency of the project is not installed.', ['@version' => $next_major]),
      ],
      'later' => $later,
      'ignore' => [
        $this->t('After Drupal @version', ['@version' => $next_major]),
        $this->t('These are removed in later versions, and are not required to fix for Drupal @version.', ['@version' => $next_major]),
      ],
    ];
  }

  /**
   * Gets the advice for a contributed project without a compatible release.
   *
   * @param string $name
   *   The name of the project.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The advice, the same on the result page and in the Markdown results.
   */
  protected function getCommunityNote(string $name) {
    return $this->t('@project is a contributed project without a compatible release yet. Look for an issue or merge request with a fix first. Try the fix on this site, adjust it if needed, and post your feedback in the issue, so the fix gets into a release.', ['@project' => $name]);
  }

  /**
   * Gets the note about the Drupal core version that fixes work from.
   *
   * @param array $files
   *   Messages of the 'later' category, keyed by file path.
   * @param bool $contrib
   *   Whether the project is a contributed project.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The note, with the newest Drupal version that a message has as the
   *   version of the deprecation. The replacement of a deprecated API is in
   *   Drupal core from that version.
   */
  protected function getFixVersionNote(array $files, bool $contrib) {
    $version = NULL;
    foreach ($files as $messages) {
      foreach ($messages as $error) {
        // The messages are arrays in the results, and text in the Markdown.
        $text = is_array($error) ? ($error['message'] ?? '') : (string) $error;
        if (preg_match('!deprecated (?:in|as of) drupal[ :](\d+\.\d+)!i', $text, $found) && ($version === NULL || version_compare($found[1], $version) > 0)) {
          $version = $found[1];
        }
      }
    }
    if ($version !== NULL) {
      return $contrib ?
        $this->t('The fixes work from Drupal @version. Only fix these if the project can stop supporting older Drupal core versions, for example on a new branch, and raise the core requirement with the fix. Otherwise leave these for later.', ['@version' => $version]) :
        $this->t('The fixes work from Drupal @version. Update Drupal core first.', ['@version' => $version]);
    }
    return $contrib ?
      $this->t('Only fix these if the project can stop supporting older Drupal core versions, for example on a new branch, and raise the core requirement with the fix. Otherwise leave these for later.') :
      $this->t('Update Drupal core first.');
  }

  /**
   * Gets the titles of the categories of messages.
   *
   * @return array
   *   The titles, the same as in the results, keyed by the categories of
   *   ::getCategory(), in the order of the results.
   */
  protected function getCategoryTitles(): array {
    return array_map(static fn (array $category) => $category[0], $this->getCategories());
  }

  /**
   * Gets the group of a message in the results.
   *
   * @param array $error
   *   A message from the scan results.
   *
   * @return string
   *   One of 'rector', 'now', 'requirements', 'contrib', 'uncategorized',
   *   'later' or 'ignore'.
   */
  protected function getCategory(array $error): string {
    if (empty($error['upgrade_status_category'])) {
      return 'uncategorized';
    }
    if (in_array($error['upgrade_status_category'], ['safe', 'old'])) {
      return 'now';
    }
    return $error['upgrade_status_category'];
  }

  /**
   * Gets the path of a file to show in the results.
   *
   * @param string $filepath
   *   The absolute path of a file. It may be followed by more text, like
   *   " (in context of class X)".
   *
   * @return string
   *   The path relative to the Drupal root. If this is a composer setup, then
   *   the webroot is in a web/ directory, so that is added back in for easy
   *   path copy-pasting.
   */
  public function getDisplayPath(string $filepath): string {
    $short_path = str_replace(DRUPAL_ROOT . '/', '', $filepath);
    if (preg_match('!/web$!', DRUPAL_ROOT)) {
      // The files of recipes outside the webroot start with ../ already.
      $short_path = str_starts_with($short_path, '../') ? substr($short_path, 3) : 'web/' . $short_path;
    }
    return $short_path;
  }

}
