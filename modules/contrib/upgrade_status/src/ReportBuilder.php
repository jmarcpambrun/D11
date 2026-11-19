<?php

namespace Drupal\upgrade_status;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Builds the parts of the report page.
 *
 * The report page and the HTML format of the report command use the same
 * summary, environment checks and project tables. The page adds the form
 * controls and the links to other pages of the site.
 */
final class ReportBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a new report builder.
   *
   * @param \Drupal\upgrade_status\ProjectCollector $projectCollector
   *   The project collector.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\upgrade_status\ScanResultFormatter $resultFormatter
   *   The scan result formatter.
   * @param \Drupal\upgrade_status\ToolUpdateChecker $toolUpdateChecker
   *   The tool update checker.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly ProjectCollector $projectCollector,
    private readonly StateInterface $state,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly RendererInterface $renderer,
    private readonly ScanResultFormatter $resultFormatter,
    private readonly ToolUpdateChecker $toolUpdateChecker,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Gets the header of the project tables.
   *
   * @return array
   *   The header cells, keyed by the column.
   */
  public function getProjectHeader(): array {
    $next_major = ProjectCollector::getDrupalCoreMajorVersion() + 1;
    return [
      'project'  => ['data' => $this->t('Project'), 'class' => 'project-label'],
      'type'     => ['data' => $this->t('Type'), 'class' => 'type-label'],
      'status'   => ['data' => $this->t('Status'), 'class' => 'status-label'],
      'version'  => ['data' => $this->t('Local version'), 'class' => 'version-label'],
      'ready'    => [
        'data' => $this->t('Local @next_major-ready', ['@next_major' => $next_major]),
        'class' => 'ready-label',
      ],
      'result'   => ['data' => $this->t('Local analysis'), 'class' => 'scan-info'],
      'updatev'  => ['data' => $this->t('Drupal.org version'), 'class' => 'updatev-info'],
      'update9'  => [
        'data' => $this->t('Drupal.org @next_major-ready', ['@next_major' => $next_major]),
        'class' => 'update9-info',
      ],
      'issues'   => ['data' => $this->t('Drupal.org issues'), 'class' => 'issue-info'],
    ];
  }

  /**
   * Builds the rows of a project table.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   Projects from ProjectCollector::collectProjects(), keyed by name.
   * @param array $step_label
   *   The information about the next step of these projects, from
   *   ProjectCollector::getNextStepInfo().
   *
   * @return array
   *   Rows keyed by the project name, in the format of the options of a
   *   tableselect element.
   */
  public function buildProjectRows(array $projects, array $step_label): array {
    $rows = [];
    foreach ($projects as $name => $extension) {
      $option = [
        '#attributes' => ['class' => 'project-' . $name . ' ' . $step_label[2]],
      ];
      $label = $extension->info['name'] . ' (' . $extension->getName() . ')';
      $option['project'] = [
        'data' => [
          'label' => ['#plain_text' => $label],
        ],
        'class' => 'project-label',
      ];
      $summary = $this->projectCollector->getProjectSummary($extension);
      // The version comes from the info file of the project, which is not
      // trusted to have markup.
      foreach (['type', 'status', 'version'] as $column) {
        $option[$column] = [
          'data' => [
            'label' => ['#plain_text' => $summary[$column]],
          ],
        ];
      }
      $option['ready'] = [
        'class' => 'status-info ' . (!empty($extension->info['upgrade_status_next_major_compatible']) ? 'status-info-compatible' : 'status-info-incompatible'),
        'data' => [
          'label' => [
            '#type' => 'markup',
            '#markup' => $summary['ready'],
          ],
        ],
      ];

      $option['result'] = [
        'data' => [
          'label' => [
            '#type' => 'markup',
            '#markup' => $summary['result_problems'],
          ],
          'later' => $summary['result_later'] ? ['#markup' => '<span class="us-qualifier">' . $summary['result_later'] . '</span>'] : [],
        ],
        'class' => 'scan-result',
      ];

      // Start of drupal.org data columns.
      if (!empty($summary['update_url'])) {
        $option['updatev'] = [
          'data' => [
            'link' => [
              '#type' => 'link',
              '#title' => $summary['update_version'],
              '#url' => Url::fromUri($summary['update_url']),
            ],
          ],
        ];
      }
      else {
        $option['updatev'] = [
          'data' => [
            'label' => ['#plain_text' => $summary['update_version']],
          ],
        ];
      }
      $option['update9'] = [
        'class' => 'status-info ' . $summary['update_ready_class'],
        'data' => [
          'label' => [
            '#type' => 'markup',
            '#markup' => $summary['update_ready'],
          ],
        ],
      ];
      $option['issues'] = [
        'data' => [
          'label' => empty($summary['issues_url']) ? ['#markup' => $this->t('Not applicable')] : [
            '#type' => 'link',
            '#title' => $this->t('Issues', [], ['context' => 'Drupal.org issues']),
            '#url' => Url::fromUri($summary['issues_url']),
          ],
        ],
      ];
      $rows[$name] = $option;
    }
    return $rows;
  }

  /**
   * The groups of projects in the order of the workflow.
   */
  public const SECTION_ORDER = [
    ProjectCollector::NEXT_SCAN,
    ProjectCollector::NEXT_REMOVE,
    ProjectCollector::NEXT_UPDATE,
    ProjectCollector::NEXT_COLLABORATE,
    ProjectCollector::NEXT_RECTOR,
    ProjectCollector::NEXT_MANUAL,
    ProjectCollector::NEXT_REQUIREMENTS,
    ProjectCollector::NEXT_RELAX,
  ];

  /**
   * The pill variants of the groups of projects.
   */
  public const SECTION_VARIANTS = [
    ProjectCollector::NEXT_SCAN => 'neutral',
    ProjectCollector::NEXT_REMOVE => 'warning',
    ProjectCollector::NEXT_UPDATE => 'warning',
    ProjectCollector::NEXT_COLLABORATE => 'warning',
    ProjectCollector::NEXT_RECTOR => 'error',
    ProjectCollector::NEXT_MANUAL => 'error',
    ProjectCollector::NEXT_REQUIREMENTS => 'warning',
    ProjectCollector::NEXT_RELAX => 'ok',
  ];

  /**
   * Joins items into one list, like "a, b, and c".
   *
   * @param array $items
   *   The items.
   *
   * @return string
   *   The list, as plain text.
   */
  public static function joinList(array $items): string {
    $items = array_values(array_map('strval', $items));
    $last = array_pop($items);
    if (empty($items)) {
      return (string) $last;
    }
    // The items are plain text, so they are put in after the translation,
    // to not escape them twice when the list is used in another string.
    if (count($items) === 1) {
      $pattern = (string) t('@first and @last', ['@first' => '{first}', '@last' => '{last}']);
      return strtr($pattern, ['{first}' => $items[0], '{last}' => $last]);
    }
    $pattern = (string) t('@list, and @last', ['@list' => '{list}', '@last' => '{last}']);
    return strtr($pattern, ['{list}' => implode(', ', $items), '{last}' => $last]);
  }

  /**
   * Builds a header cell with a label for assistive technology only.
   *
   * @param string|\Stringable $label
   *   The label.
   * @param string $class
   *   The class of the cell.
   *
   * @return array
   *   The header cell.
   */
  public static function hiddenHeader(string|\Stringable $label, string $class): array {
    return [
      'data' => ['#markup' => '<span class="visually-hidden">' . Html::escape((string) $label) . '</span>'],
      'class' => [$class],
    ];
  }

  /**
   * Builds a menu button.
   *
   * The menu is a details element. JavaScript adds the menu behavior.
   *
   * @param string|\Stringable $label
   *   The button label.
   * @param array $links
   *   Link labels keyed by the format, for links to $route. Buttons can be
   *   added as children instead.
   * @param string|null $route
   *   The route of the links, with a format parameter.
   * @param string $direction
   *   Either 'down', or 'up' to open above the button.
   * @param array $route_parameters
   *   More route parameters.
   * @param bool $small
   *   Whether to show a small button, like next to page titles.
   *
   * @return array
   *   The render array.
   */
  public static function buildMenu(string|\Stringable $label, array $links, ?string $route, string $direction = 'down', array $route_parameters = [], bool $small = FALSE): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['us-menu-wrapper']],
      '#prefix' => Markup::create('<details class="us-menu' . ($direction === 'up' ? ' us-menu--up' : '') . '" data-us-menu><summary class="button' . ($small ? ' button--small' : '') . '" aria-haspopup="menu">' . Icon::render('download-simple', 16) . '<span>' . Html::escape((string) $label) . '</span>' . Icon::render($direction === 'up' ? 'caret-up' : 'caret-down', 14) . '</summary><div class="us-menu__items" role="menu">'),
      '#suffix' => Markup::create('</div></details>'),
    ];
    foreach ($links as $format => $link_label) {
      $build[$format] = [
        '#type' => 'link',
        '#title' => $link_label,
        '#url' => Url::fromRoute($route, ['format' => $format] + $route_parameters),
        '#attributes' => ['role' => 'menuitem'],
      ];
    }
    return $build;
  }

  /**
   * Builds the title of a section, for the summary of a details element.
   *
   * @param string $step
   *   The next step of the projects in the section, or an empty string.
   * @param string|\Stringable $title
   *   The title.
   * @param string|\Stringable $pill
   *   The count in the pill.
   * @param string|\Stringable|array|null $summary
   *   The line that summarizes the section.
   * @param string|null $variant
   *   The pill variant. Defaults to the variant of the step.
   * @param \Drupal\Core\Render\Markup|null $icon
   *   An icon to show instead of the color square of the step.
   *
   * @return array
   *   The render array.
   */
  public function buildSectionTitle(string $step, string|\Stringable $title, string|\Stringable $pill, string|\Stringable|array|null $summary, ?string $variant = NULL, ?Markup $icon = NULL): array {
    return [
      '#theme' => 'upgrade_status_section_title',
      '#step' => $step,
      '#icon' => $icon,
      '#title' => $title,
      '#pill' => $pill,
      '#pill_variant' => $variant ?? (self::SECTION_VARIANTS[$step] ?? 'neutral'),
      '#summary' => $summary,
    ];
  }

  /**
   * Builds the summary card of the projects.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   Projects from ProjectCollector::collectProjects(), keyed by name.
   * @param bool|null $environment_status
   *   The status of the environment, for the verdict.
   *
   * @return array
   *   Render array.
   */
  public function buildSummaryCard(array $projects, ?bool $environment_status): array {
    $next_major = ProjectCollector::getDrupalCoreMajorVersion() + 1;
    $labels = $this->projectCollector->getNextStepInfo();
    $counts = array_fill_keys(self::SECTION_ORDER, 0);
    $not_analyzed = array_fill_keys(self::SECTION_ORDER, 0);
    foreach ($projects as $name => $project) {
      $step = $project->info['upgrade_status_next'];
      $counts[$step]++;
      if ($step !== ProjectCollector::NEXT_SCAN && !$this->projectCollector->getResultTotals($name)) {
        $not_analyzed[$step]++;
      }
    }
    $total = count($projects);
    $compatible = $counts[ProjectCollector::NEXT_RELAX];
    $percent = $total ? (int) round($compatible / $total * 100) : 100;

    if ($compatible < $total) {
      $heading = $this->t('Not ready for Drupal @version yet', ['@version' => $next_major]);
    }
    elseif ($environment_status === FALSE) {
      $heading = $this->t('Projects are ready for Drupal @version, the environment is not yet', ['@version' => $next_major]);
    }
    else {
      $heading = $this->t('Ready for Drupal @version', ['@version' => $next_major]);
    }
    $sentence = (string) $this->t('@compatible of @total projects are compatible.', [
      '@compatible' => $compatible,
      '@total' => $total,
    ]);
    $parts = [];
    if ($counts[ProjectCollector::NEXT_REMOVE]) {
      $parts[] = $this->formatPlural($counts[ProjectCollector::NEXT_REMOVE], '1 is uninstalled and can be removed', '@count are uninstalled and can be removed');
    }
    $fix = $counts[ProjectCollector::NEXT_UPDATE] + $counts[ProjectCollector::NEXT_COLLABORATE] + $counts[ProjectCollector::NEXT_RECTOR] + $counts[ProjectCollector::NEXT_MANUAL] + $counts[ProjectCollector::NEXT_REQUIREMENTS];
    if ($fix) {
      $parts[] = $this->formatPlural($fix, '1 needs an update or code fixes', '@count need an update or code fixes');
    }
    if ($counts[ProjectCollector::NEXT_SCAN]) {
      $parts[] = $this->formatPlural($counts[ProjectCollector::NEXT_SCAN], '1 is not analyzed yet', '@count are not analyzed yet');
    }
    if ($parts) {
      $sentence .= ' ' . $this->formatPlural($total - $compatible, 'Of the 1 remaining, @list.', 'Of the @count remaining, @list.', ['@list' => self::joinList($parts)]);
    }

    $segments = $bar = [];
    $phases = [
      [$this->t('Gather data'), [ProjectCollector::NEXT_SCAN]],
      [$this->t('Fix incompatibilities'), array_slice(self::SECTION_ORDER, 1, 6)],
      [$this->t('Done'), [ProjectCollector::NEXT_RELAX]],
    ];
    $legend = [];
    foreach ($phases as [$phase, $steps]) {
      $items = [];
      foreach ($steps as $step) {
        if (!$counts[$step]) {
          continue;
        }
        $segments[] = [
          'step' => $step,
          'count' => $counts[$step],
          'not_analyzed' => $not_analyzed[$step] ? round($not_analyzed[$step] / $counts[$step] * 100) : 0,
        ];
        $bar[] = $labels[$step][0] . ': ' . $counts[$step];
        $items[] = [
          'step' => $step,
          'anchor' => 'edit-' . $step,
          'count' => $counts[$step],
          'title' => $labels[$step][0],
          'note' => $not_analyzed[$step] ? $this->formatPlural($not_analyzed[$step], '1 not analyzed', '@count not analyzed') : NULL,
        ];
      }
      if ($items) {
        $legend[] = ['label' => $phase, 'items' => $items];
      }
    }

    return [
      '#theme' => 'upgrade_status_summary',
      '#heading' => $heading,
      '#sentence' => $sentence,
      '#percent' => $percent,
      '#bar_label' => $this->t('Projects by next step: @list', ['@list' => implode(', ', $bar)]),
      '#segments' => $segments,
      '#legend' => $legend,
    ];
  }

  /**
   * Gets the notes about what makes the report less accurate.
   *
   * Older versions of Upgrade Status and PHPStan can miss deprecations, and
   * results made with other versions than the installed ones may be outdated.
   * Update recommendations need the versions of contributed projects, and how
   * to add them depends on how the projects were installed.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects to check for versions, keyed by name.
   * @param \Drupal\Core\Extension\Extension[] $all_projects
   *   All projects, to check whether Composer Deploy and Git Deploy are
   *   installed.
   *
   * @return array
   *   Notes keyed by 'tools', 'results', 'updates', 'composer', 'git' and
   *   'other'. Each note is an array with the number of projects that the
   *   note is about, or NULL for the tools and the update data, and the note,
   *   which may contain HTML.
   */
  public function getAccuracyNotes(array $projects, array $all_projects): array {
    $notes = [];
    if ($updates = $this->toolUpdateChecker->getUpdates()) {
      $notes['tools'] = [NULL, $this->toolUpdateChecker->formatUpdates($updates)];
    }
    $other_versions = [];
    foreach ($projects as $name => $extension) {
      $versions = $this->projectCollector->getResultTotals($name)['versions'] ?? NULL;
      if ($this->toolUpdateChecker->isOtherThanInstalled($versions)) {
        $other_versions[] = $extension->info['name'] ?? $name;
      }
    }
    if ($count = count($other_versions)) {
      // A few projects are named, more would make the note too long.
      $notes['results'] = [$count, $count <= 5 ?
        $this->formatPlural(
          $count,
          '1 project was analyzed with other versions of the analysis tools than the installed ones, so the results may be outdated. Analyze the project again: @projects.',
          '@count projects were analyzed with other versions of the analysis tools than the installed ones, so the results may be outdated. Analyze these projects again: @projects.',
          ['@projects' => self::joinList($other_versions)],
        ) :
        $this->formatPlural(
          $count,
          '1 project was analyzed with other versions of the analysis tools than the installed ones, so the results may be outdated. Analyze the project again.',
          '@count projects were analyzed with other versions of the analysis tools than the installed ones, so the results may be outdated. Analyze these projects again.',
        ),
      ];
    }
    if ($this->isUpdateDataOutdated()) {
      $last = $this->state->get('update.last_check') ?: 0;
      $notes['updates'] = [NULL, $last ?
        $this->t('Available updates for all projects were last checked @time ago, so update recommendations may be outdated.', ['@time' => $this->dateFormatter->formatTimeDiffSince($last)]) :
        $this->t('Available updates for all projects were never checked, so there are no update recommendations.'),
      ];
    }
    $without_version = $this->projectCollector->getContribProjectsWithoutVersion($projects);
    $links = [
      ':composer_deploy' => 'https://www.drupal.org/project/composer_deploy',
      ':git_deploy' => 'https://www.drupal.org/project/git_deploy',
    ];
    if ($without_version['composer'] && empty($all_projects['composer_deploy']->status)) {
      $count = count($without_version['composer']);
      $note = $this->formatPlural(
        $count,
        '1 contributed project installed with Composer has no version information, so update recommendations may be wrong. Install <a href=":composer_deploy">Composer Deploy</a> to add it.',
        '@count contributed projects installed with Composer have no version information, so update recommendations may be wrong. Install <a href=":composer_deploy">Composer Deploy</a> to add it.',
        $links
      );
      $notes['composer'] = [$count, $note];
    }
    if ($without_version['git'] && empty($all_projects['git_deploy']->status)) {
      $count = count($without_version['git']);
      $note = $this->formatPlural(
        $count,
        '1 contributed project checked out with Git has no version information, so update recommendations may be wrong. Install <a href=":git_deploy">Git Deploy</a> to add it.',
        '@count contributed projects checked out with Git have no version information, so update recommendations may be wrong. Install <a href=":git_deploy">Git Deploy</a> to add it.',
        $links
      );
      $notes['git'] = [$count, $note];
    }
    if ($without_version['other']) {
      $count = count($without_version['other']);
      $note = $this->formatPlural(
        $count,
        '1 contributed project was not installed with Composer or Git, so the version is unknown and updates cannot be checked. Install the project with Composer, like <code>composer require drupal/@example</code>.',
        '@count contributed projects were not installed with Composer or Git, so the versions are unknown and updates cannot be checked. Install these projects with Composer, like <code>composer require drupal/@example</code>.',
        ['@example' => reset($without_version['other'])->info['project']]
      );
      $notes['other'] = [$count, $note];
    }
    return $notes;
  }

  /**
   * Checks whether the data of available updates is missing or outdated.
   *
   * @return bool
   *   TRUE if available updates were never checked, or more than a day ago.
   */
  public function isUpdateDataOutdated(): bool {
    $last = $this->state->get('update.last_check') ?: 0;
    return !$last || $this->time->getRequestTime() - $last > 86400;
  }

  /**
   * Builds the card about what makes the report less accurate.
   *
   * The card is above the summary card, because the summary depends on the
   * analysis and the update data too. It is yellow when there is something to
   * fix: newer analysis tools, available updates that were never checked or
   * checked more than a day ago, or project versions that cannot be checked.
   * Otherwise it only shows when available updates were checked.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects to check for versions, keyed by name.
   * @param \Drupal\Core\Extension\Extension[] $all_projects
   *   All projects, to check whether Composer Deploy and Git Deploy are
   *   installed.
   * @param array|null $destination
   *   For the report page, the destination to return to after checking for
   *   available updates. NULL for a document, which has no links to act on
   *   the site.
   *
   * @return array
   *   Render array.
   */
  public function buildAccuracyCard(array $projects, array $all_projects, ?array $destination): array {
    $notes = $this->getAccuracyNotes($projects, $all_projects);
    $warning = Icon::render('warning', 16, $this->t('To fix'), 'us-status--warning');
    $items = [];

    if (isset($notes['tools'])) {
      $updates = $this->toolUpdateChecker->getUpdates();
      $rows = [];
      foreach ($updates as $package => [$installed, $available]) {
        [$label, $url] = ToolUpdateChecker::TOOLS[$package] ?? [$package, NULL];
        $rows[] = [
          ['data' => $label, 'class' => ['us-tool-name']],
          ['data' => $installed, 'class' => ['us-mono', 'us-version']],
          ['data' => ['#markup' => Icon::render('arrow-right', 14)], 'class' => ['us-col-arrow']],
          ['data' => $available, 'class' => ['us-mono', 'us-version--new']],
          ['data' => $url ? Link::fromTextAndUrl($this->t('Release notes'), Url::fromUri($url))->toRenderable() : ''],
        ];
      }
      $command = $this->toolUpdateChecker->getUpdateCommand($updates);
      $items['tools'] = [
        'icon' => ['#markup' => $warning],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['us-notice__item-body']],
          'text' => [
            '#markup' => '<p>' . $this->t('Newer analysis tools are available. Newer releases detect problems more accurately. Update the tools, then analyze your projects again.') . '</p>',
          ],
          'table' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Tool'),
              $this->t('Installed'),
              ['data' => $this->t('Updates to'), 'class' => ['us-col-arrow']],
              $this->t('Available'),
              $this->t('Release notes'),
            ],
            '#rows' => $rows,
            '#attributes' => ['class' => ['us-tool-table']],
            '#prefix' => '<div class="us-table-wrap">',
            '#suffix' => '</div>',
          ],
          'command' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['us-command']],
            'code' => ['#markup' => '<code data-us-command>' . Html::escape($command) . '</code>'],
            'copy' => is_null($destination) ? [] : [
              '#type' => 'html_tag',
              '#tag' => 'button',
              '#value' => Markup::create(Icon::render('copy', 16) . Html::escape((string) $this->t('Copy command'))),
              '#attributes' => [
                'type' => 'button',
                'class' => ['button', 'button--small', 'js-show'],
                'data-us-copy' => '',
              ],
            ],
          ],
        ],
      ];
    }
    if (isset($notes['results'])) {
      $items['results'] = [
        'icon' => ['#markup' => $warning],
        'body' => ['#markup' => '<p class="us-notice__item-body">' . $notes['results'][1] . '</p>'],
      ];
    }
    // Missing versions only affect the update recommendations, so they get
    // the info icon. Updates to make get the warning icon.
    foreach (['composer', 'git', 'other'] as $key) {
      if (isset($notes[$key])) {
        $items[$key] = [
          'icon' => ['#markup' => Icon::render('info', 16, $this->t('Note'), 'us-status--neutral')],
          'body' => ['#markup' => '<p class="us-notice__item-body">' . $notes[$key][1] . '</p>'],
        ];
      }
    }

    // When available updates were checked, as a note if it is outdated.
    $last = $this->state->get('update.last_check') ?: 0;
    $text = isset($notes['updates']) ? $notes['updates'][1] : $this->t('Available updates for all projects were checked @time ago.', ['@time' => $this->dateFormatter->formatTimeDiffSince($last)]);
    $items['updates'] = [
      'icon' => ['#markup' => isset($notes['updates']) ? $warning : Icon::render('clock-counter-clockwise', 16)],
      'body' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['us-notice__item-body']],
        'text' => ['#markup' => $text . (is_null($destination) ? '' : ' ')],
        'link' => is_null($destination) ? [] : [
          '#type' => 'link',
          '#title' => $this->t('Check manually'),
          '#url' => Url::fromRoute('update.manual_status', [], ['query' => $destination]),
        ],
      ],
    ];

    // The versions that new results are made with. Other versions can find
    // other problems, so a report is only comparable with the same versions.
    // An export has them next to its date already.
    if (!is_null($destination)) {
      $items['versions'] = [
        'icon' => ['#markup' => Icon::render('wrench', 16)],
        'body' => [
          '#markup' => '<p class="us-notice__item-body">' . $this->getVersionsLine() . '</p>',
        ],
      ];
    }

    $list = [];
    foreach ($items as $key => $item) {
      $list[$key] = [
        '#type' => 'html_tag',
        '#tag' => 'li',
        '#attributes' => ['class' => ['us-notice__item']],
        'content' => $item,
      ];
    }
    $count = count($notes);
    return [
      '#type' => 'html_tag',
      '#tag' => 'section',
      '#attributes' => [
        'id' => 'us-accuracy',
        'class' => ['us-notice', $count ? 'us-notice--warning' : 'us-notice--quiet'],
        'aria-labelledby' => 'us-accuracy-title',
      ],
      'icon' => ['#markup' => Icon::render($count ? 'warning' : 'info', 22, NULL, 'us-notice__icon')],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['us-notice__body']],
        'heading' => [
          '#markup' => Markup::create('<h2 class="us-notice__heading" id="us-accuracy-title">' . Html::escape((string) $this->t('Report accuracy')) . ($count ? ' <span class="us-pill us-pill--warning">' . Html::escape((string) $this->formatPlural($count, '1 to fix', '@count to fix')) . '</span>' : '') . '</h2>'),
        ],
        'description' => $count ? [
          '#markup' => '<p class="us-notice__description">' . $this->t('These make the analysis or the update recommendations less accurate.') . '</p>',
        ] : [],
        'items' => [
          '#type' => 'html_tag',
          '#tag' => 'ul',
          '#attributes' => ['class' => ['us-notice__items']],
        ] + $list,
      ],
    ];
  }

  /**
   * Builds the section of environment checks.
   *
   * @param array $environment
   *   The environment from EnvironmentChecker::getChecks().
   *
   * @return array
   *   A details element with the description and the table of checks.
   */
  public function buildEnvironmentSection(array $environment): array {
    $severities = [
      'error' => [0, 'x-circle', 'us-status--error', $this->t('Not met')],
      'warning' => [1, 'warning', 'us-status--warning', $this->t('Check manually')],
      '' => [2, 'warning', 'us-status--warning', $this->t('Check manually')],
      'success' => [3, 'check-circle', 'us-status--ok', $this->t('Met')],
    ];
    $checks = $environment['checks'];
    usort($checks, fn ($a, $b) => $severities[$a['severity'] ?? ''][0] <=> $severities[$b['severity'] ?? ''][0]);
    $counts = ['error' => 0, 'warning' => 0, 'success' => 0];
    foreach ($checks as $check) {
      $counts[$check['severity'] === 'success' || $check['severity'] === 'error' ? $check['severity'] : 'warning']++;
    }

    $summary = [];
    foreach ([
      'error' => [
        'x-circle',
        'us-status--error',
        $this->formatPlural($counts['error'], '1 requirement not met', '@count requirements not met'),
      ],
      'warning' => [
        'warning',
        'us-status--warning',
        $this->formatPlural($counts['warning'], '1 to check manually', '@count to check manually'),
      ],
      'success' => [
        'check-circle',
        'us-status--ok',
        $this->formatPlural($counts['success'], '1 requirement met', '@count requirements met'),
      ],
    ] as $key => [$icon, $class, $text]) {
      if ($counts[$key]) {
        $summary[$key] = ['#markup' => Markup::create('<span class="us-section-summary__item">' . Icon::render($icon, 16, NULL, $class) . Html::escape((string) $text) . '</span>')];
      }
    }

    $build = [
      '#type' => 'details',
      '#id' => 'edit-environment',
      '#title' => $this->buildSectionTitle(
        '',
        $this->t('Drupal core and hosting environment'),
        $counts['error'] ? $this->formatPlural($counts['error'], '1 error', '@count errors') : ($checks ? $this->t('All met') : ''),
        $summary ?: NULL,
        $counts['error'] ? 'error' : 'ok',
      ),
      '#open' => TRUE,
      '#attributes' => ['class' => ['us-section', 'upgrade-status-of-environment']],
      'description' => [
        '#markup' => '<p class="us-description">' . $environment['description'] . '</p>',
      ],
    ];
    if (empty($checks)) {
      return $build;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'icon' => self::hiddenHeader($this->t('Status'), 'us-env-status'),
        'requirement' => $this->t('Requirement'),
        'expected' => $this->t('Needed for Drupal @version', ['@version' => ProjectCollector::getDrupalCoreMajorVersion() + 1]),
        'status' => $this->t('This site'),
      ],
      '#attributes' => ['class' => ['us-table']],
      '#prefix' => '<div class="us-table-wrap">',
      '#suffix' => '</div>',
      '#rows' => [],
    ];
    foreach ($checks as $check) {
      [, $icon, $class, $label] = $severities[$check['severity'] ?? ''];
      $status = ['#markup' => $check['status']];
      if (!empty($check['items'])) {
        $status = [
          '#theme' => 'item_list',
          '#items' => array_map(fn ($item) => ['#markup' => $item], $check['items']),
          '#attributes' => ['class' => ['us-plain-list']],
        ];
      }
      elseif (!empty($check['url'])) {
        $status = [
          '#type' => 'link',
          '#title' => $check['status'],
          '#url' => Url::fromUri($check['url']),
        ];
      }
      $build['table']['#rows'][] = [
        'class' => !empty($check['severity']) ? ['color-' . $check['severity']] : [],
        'data' => [
          'icon' => ['data' => ['#markup' => Icon::render($icon, 18, $label, $class)], 'class' => ['us-env-status']],
          'requirement' => [
            'data' => [
              'name' => ['#markup' => '<strong>' . ($check['name'] ?? $check['requirement']) . '</strong>'],
              'qualifier' => !empty($check['qualifier']) ? ['#markup' => '<span class="us-qualifier">' . $check['qualifier'] . '</span>'] : [],
              'help' => !empty($check['help']) ? ['#markup' => '<span class="us-qualifier">' . $check['help'] . '</span>'] : [],
            ],
          ],
          'expected' => ['data' => ['#markup' => $check['expected'] ?? '']],
          'status' => [
            'data' => $status,
            'class' => $check['severity'] === 'error' ? ['us-error-value'] : [],
          ],
        ],
      ];
    }
    return $build;
  }

  /**
   * Renders the report as an HTML document, like the report page.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   Projects from ProjectCollector::collectProjects(), keyed by name. The
   *   summary counts these.
   * @param string[] $next_steps
   *   The next steps of the groups of projects to list.
   * @param array|null $environment
   *   The environment from EnvironmentChecker::getChecks(), to list the checks,
   *   or NULL to leave them out. The summary uses the status in both cases.
   * @param bool|null $environment_status
   *   The status of the environment, for the summary.
   * @param \Drupal\Core\Extension\Extension[] $results
   *   Projects to add the full results of, keyed by name.
   * @param \Drupal\Core\Extension\Extension[]|null $all_projects
   *   All projects, to check whether Composer Deploy and Git Deploy are
   *   installed, or NULL if $projects has all of them.
   *
   * @return string
   *   The HTML document, without form controls and links to the site.
   */
  public function renderReport(array $projects, array $next_steps, ?array $environment, ?bool $environment_status, array $results = [], ?array $all_projects = NULL): string {
    $build = [
      '#theme' => 'upgrade_status_html_report',
      '#title' => $this->t('Upgrade Status report for Drupal @version', ['@version' => ProjectCollector::getDrupalCoreMajorVersion() + 1]),
      '#meta' => $this->resultFormatter->getDocumentMeta(),
      '#styles' => self::getStyles(),
      '#script' => self::getExportScript(),
      '#accuracy' => $this->buildAccuracyCard($projects, $all_projects ?? $projects, NULL),
      '#summary' => $this->buildSummaryCard($projects, $environment_status),
      '#environment' => NULL,
      '#groups' => [],
      '#results' => [],
    ];
    if (!is_null($environment)) {
      $build['#environment'] = $this->buildEnvironmentSection($environment);
      foreach ($environment['warnings'] as $key => $warning) {
        $build['#environment']['warnings'][$key] = [
          '#markup' => '<p class="us-description">' . $warning . '</p>',
          '#weight' => -1,
        ];
      }
    }
    $step_labels = $this->projectCollector->getNextStepInfo();
    foreach (array_intersect(self::SECTION_ORDER, $next_steps) as $next_step) {
      $list = array_filter($projects, fn (Extension $project) => $project->info['upgrade_status_next'] == $next_step);
      if (empty($list)) {
        continue;
      }
      $rows = [];
      foreach ($this->buildProjectRows($list, $step_labels[$next_step]) as $row) {
        $attributes = $row['#attributes'];
        unset($row['#attributes']);
        $rows[] = ['class' => explode(' ', 'us-cat--' . $next_step . ' ' . $attributes['class']), 'data' => $row];
      }
      $build['#groups'][$next_step] = [
        '#type' => 'details',
        '#id' => 'edit-' . $next_step,
        '#title' => $this->buildSectionTitle($next_step, $step_labels[$next_step][0], $this->formatPlural(count($list), '1 project', '@count projects'), $this->getSectionSummary($next_step, $list)),
        '#open' => TRUE,
        '#attributes' => ['class' => ['us-section']],
        'description' => ['#markup' => '<p class="us-description">' . $step_labels[$next_step][1] . '</p>'],
        'table' => [
          '#type' => 'table',
          '#header' => $this->getProjectHeader(),
          '#rows' => $rows,
          '#attributes' => ['class' => ['us-table']],
          '#prefix' => '<div class="us-table-wrap">',
          '#suffix' => '</div>',
        ],
      ];
    }
    foreach ($results as $name => $extension) {
      $build['#results'][$name] = $this->resultFormatter->formatResult($extension, FALSE);
    }
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Gets the module stylesheet, for documents that inline it.
   *
   * @return \Drupal\Core\Render\Markup
   *   The CSS.
   */
  public static function getStyles(): Markup {
    return Markup::create((string) file_get_contents(dirname(__DIR__) . '/css/upgrade_status.admin.theme.css'));
  }

  /**
   * Gets the script of the HTML exports, to put in the document.
   *
   * @return array
   *   An array with these keys:
   *   - code: The code, as markup for a script element.
   *   - hash: The SHA-256 hash of the code in Base64, for the Content
   *     Security Policy of the document. The policy allows no other script.
   */
  public static function getExportScript(): array {
    $code = (string) file_get_contents(dirname(__DIR__) . '/js/upgrade_status.export.js');
    return [
      'code' => Markup::create($code),
      'hash' => base64_encode(hash('sha256', $code, TRUE)),
    ];
  }

  /**
   * The groups of projects in the Markdown report.
   *
   * They are keyed by the name used in the --groups option of the report
   * command.
   */
  public const MARKDOWN_GROUPS = [
    'remove' => ProjectCollector::NEXT_REMOVE,
    'update' => ProjectCollector::NEXT_UPDATE,
    'scan' => ProjectCollector::NEXT_SCAN,
    'collaborate' => ProjectCollector::NEXT_COLLABORATE,
    'fix-with-rector' => ProjectCollector::NEXT_RECTOR,
    'fix-manually' => ProjectCollector::NEXT_MANUAL,
    'update-requirements' => ProjectCollector::NEXT_REQUIREMENTS,
    'compatible' => ProjectCollector::NEXT_RELAX,
  ];

  /**
   * Gets the groups of projects, by what to do next with them.
   *
   * @return array
   *   Arrays keyed by the name of the group in the --groups option. Each array
   *   has the next step from ProjectCollector, a title and what to do. The
   *   title and the description are the same as on the report page, and the
   *   commands to run are added.
   */
  public function getMarkdownGroups(): array {
    $next_steps = $this->projectCollector->getNextStepInfo();
    $see_problems = $this->t('See the problems with `upgrade-status:analyze --skip-existing` and the project name.');
    $commands = [
      ProjectCollector::NEXT_SCAN => $this->t('Analyze them with `upgrade-status:analyze` and the project name.'),
      ProjectCollector::NEXT_REMOVE => $this->t('Run `composer remove drupal/` with the project name, or delete the directory of a custom project.'),
      ProjectCollector::NEXT_RECTOR => $see_problems,
      ProjectCollector::NEXT_MANUAL => $see_problems,
      ProjectCollector::NEXT_REQUIREMENTS => $see_problems,
    ];
    $groups = [];
    foreach (self::MARKDOWN_GROUPS as $name => $next_step) {
      $description = $this->toMarkdown($next_steps[$next_step][1]);
      if (isset($commands[$next_step])) {
        $description .= ' ' . $commands[$next_step];
      }
      $groups[$name] = [$next_step, $next_steps[$next_step][0], $description];
    }
    return $groups;
  }

  /**
   * Formats the report as Markdown, like the report command.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects to count and list, keyed by name.
   * @param \Drupal\Core\Extension\Extension[] $all_projects
   *   All projects, to check whether Composer Deploy and Git Deploy are
   *   installed.
   * @param array $environment
   *   The environment from EnvironmentChecker::getChecks().
   * @param string[] $selected
   *   The sections to show: 'environment' and the names of the groups.
   *
   * @return string
   *   The Markdown text.
   */
  public function formatMarkdownReport(array $projects, array $all_projects, array $environment, array $selected): string {
    $groups = $this->getMarkdownGroups();
    $lines = [
      '# ' . $this->t('Upgrade Status report for Drupal @version', ['@version' => ProjectCollector::getDrupalCoreMajorVersion() + 1]),
      '',
      $this->toMarkdown($this->resultFormatter->getDocumentMeta()),
      '',
      ...$this->formatMarkdownAccuracy($projects, $all_projects),
      '',
      '## ' . $this->t('Summary'),
      '',
      (string) $this->t('The groups below are in the order to work on them. After a fix, run `upgrade-status:analyze` with the project name, without `--skip-existing`. Exit code 0 means no problems are left for Drupal @version.', ['@version' => ProjectCollector::getDrupalCoreMajorVersion() + 1]),
      '',
    ];

    // The environment is counted as one project, like on the report page.
    if (is_null($environment['status'])) {
      $lines[] = '- ' . $this->toMarkdown($environment['description']);
    }
    else {
      $lines[] = '- ' . ($environment['status'] ? $this->t('Environment checks passed') : $this->t('Environment is incompatible')) . '.';
    }
    $counts = [];
    foreach ($projects as $project) {
      $counts[$project->info['upgrade_status_next']] = ($counts[$project->info['upgrade_status_next']] ?? 0) + 1;
    }
    foreach ($groups as [$next_step, $title]) {
      if (!empty($counts[$next_step])) {
        $lines[] = '- ' . $this->formatPlural($counts[$next_step], '@type: 1 project', '@type: @count projects', ['@type' => $title]);
      }
    }
    $done_count = ($counts[ProjectCollector::NEXT_RELAX] ?? 0) + (int) $environment['status'];
    $lines[] = '- ' . $this->t('@percent% done, counting the environment as one project.', ['@percent' => round($done_count / (count($projects) + 1) * 100)]);

    if (in_array('environment', $selected)) {
      array_push($lines, '', '## ' . $this->t('Drupal core and hosting environment'), '', $this->toMarkdown($environment['description']));
      foreach ($environment['warnings'] as $warning) {
        array_push($lines, '', $this->toMarkdown($warning));
      }
      if (!empty($environment['checks'])) {
        $lines[] = '';
      }
      $labels = [
        'success' => $this->t('Passed'),
        'warning' => $this->t('Warning'),
        'error' => $this->t('Failed'),
      ];
      foreach ($environment['checks'] as $check) {
        $requirement = $this->toMarkdown($check['requirement']);
        // How to meet a requirement is only useful if it is not met.
        if ($check['severity'] !== 'success' && !empty($check['help'])) {
          $requirement .= ' ' . $this->toMarkdown($check['help']);
        }
        $status = $this->toMarkdown($check['status']);
        if (!empty($check['url'])) {
          $status = '[' . $status . '](' . $check['url'] . ')';
        }
        $lines[] = '- ' . ($labels[$check['severity'] ?? ''] ?? $this->t('Not checked')) . ': ' . $requirement;
        $items = !empty($check['items']) ? array_map($this->toMarkdown(...), $check['items']) : [$status];
        foreach ($items as $item) {
          $lines[] = '  - ' . $item;
        }
      }
    }

    foreach ($groups as $name => [$next_step, $title, $description]) {
      if (!in_array($name, $selected) || empty($counts[$next_step])) {
        continue;
      }
      array_push($lines, '', '## ' . $title, '', (string) $description, '');
      foreach ($projects as $project_name => $project) {
        if ($project->info['upgrade_status_next'] == $next_step) {
          $lines[] = '- ' . $this->formatMarkdownProject($project_name, $project, $next_step == ProjectCollector::NEXT_COLLABORATE);
        }
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Gets the line with the installed versions of the analysis tools.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The line, as plain text.
   */
  protected function getVersionsLine(): TranslatableMarkup {
    return $this->t('Analysis tools: @tools.', [
      '@tools' => $this->toolUpdateChecker->formatVersions($this->toolUpdateChecker->getInstalledVersions()),
    ]);
  }

  /**
   * Formats the report accuracy section as Markdown, like the card.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects to check for versions, keyed by name.
   * @param \Drupal\Core\Extension\Extension[] $all_projects
   *   All projects, to check whether Composer Deploy and Git Deploy are
   *   installed.
   *
   * @return string[]
   *   The lines of the section.
   */
  private function formatMarkdownAccuracy(array $projects, array $all_projects): array {
    $notes = $this->getAccuracyNotes($projects, $all_projects);
    $lines = ['## ' . $this->t('Report accuracy'), ''];
    if ($notes) {
      $to_fix = $this->formatPlural(count($notes), '1 to fix', '@count to fix');
      $description = $this->t('These make the analysis or the update recommendations less accurate.');
      array_push($lines, $to_fix . '. ' . $description, '');
    }

    if (isset($notes['tools'])) {
      $updates = $this->toolUpdateChecker->getUpdates();
      $lines[] = '- ' . $this->t('Newer analysis tools are available. Newer releases detect problems more accurately. Update the tools, then analyze your projects again.');
      foreach ($updates as $package => [$installed, $available]) {
        [$label, $url] = ToolUpdateChecker::TOOLS[$package] ?? [$package, NULL];
        $line = '  - ' . $label . ' ' . $installed . ' → ' . $available;
        if ($url) {
          $release_notes = $this->t('Release notes');
          $line .= ' ([' . $release_notes . '](' . $url . '))';
        }
        $lines[] = $line;
      }
      $lines[] = '  - `' . $this->toolUpdateChecker->getUpdateCommand($updates) . '`';
    }
    foreach (['results', 'composer', 'git', 'other'] as $key) {
      if (isset($notes[$key])) {
        $lines[] = '- ' . $this->toMarkdown($notes[$key][1]);
      }
    }
    $lines[] = '- ' . (isset($notes['updates']) ?
      $this->toMarkdown($notes['updates'][1]) :
      $this->t('Available updates for all projects were checked @time ago.', ['@time' => $this->dateFormatter->formatTimeDiffSince($this->state->get('update.last_check'))]));
    return $lines;
  }

  /**
   * Formats a project as one line of Markdown.
   *
   * @param string $name
   *   The project name.
   * @param \Drupal\Core\Extension\Extension $project
   *   The project.
   * @param bool $with_issues
   *   Whether to link to the Drupal.org issues of a contributed project.
   *
   * @return string
   *   The name, type and status, and the local and Drupal.org versions.
   */
  public function formatMarkdownProject(string $name, $project, bool $with_issues): string {
    $summary = $this->projectCollector->getProjectSummary($project);
    $update = $summary['update_url'] ? '[' . $summary['update_version'] . '](' . $summary['update_url'] . ')' : $summary['update_version'];
    // Labels like "Unavailable" are not repeated.
    if ((string) $summary['update_version'] !== (string) $summary['update_ready']) {
      $update .= ', ' . $summary['update_ready'];
    }
    $parts = [
      $project->info['name'] . ' (' . $name . '): ' . $summary['type'] . ', ' . $summary['status'] . '.',
      sprintf('%s: %s, %s.', $this->t('Local version'), $summary['version'], $summary['ready']),
      sprintf('%s: %s.', $this->t('Local analysis'), $summary['result']),
      sprintf('%s: %s.', $this->t('Drupal.org version'), $update),
    ];
    if ($with_issues && $summary['issues_url']) {
      $parts[] = sprintf('[%s](%s)', $this->t('Issues', [], ['context' => 'Drupal.org issues']), $summary['issues_url']);
    }
    return implode(' ', $parts);
  }

  /**
   * Converts the HTML of a message to Markdown.
   *
   * @param string|\Stringable $markup
   *   A message, which may have links and code in it.
   *
   * @return string
   *   The message as Markdown.
   */
  public function toMarkdown($markup): string {
    $text = preg_replace('!<a [^>]*href="([^"]*)"[^>]*>(.*?)</a>!s', '[$2]($1)', (string) $markup);
    $text = preg_replace('!</?code>!', '`', $text);
    return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
  }

  /**
   * Builds the line that summarizes a group of projects.
   *
   * @param string $next_step
   *   The next step of the projects.
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects, keyed by name.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The summary line. Strings are plain text.
   */
  public function getSectionSummary(string $next_step, array $projects) {
    $names = function (array $list, bool $with_type = FALSE): string {
      $labels = [];
      foreach ($list as $project) {
        $label = $project->info['name'];
        if ($with_type) {
          $label .= ' (' . mb_strtolower((string) $this->projectCollector->getProjectSummary($project)['type']) . ')';
        }
        if (!empty($project->info['upgrade_status_update_security'])) {
          $label .= ' (' . $this->t('security update') . ')';
        }
        $labels[] = $label;
      }
      return self::joinList($labels);
    };
    $count = count($projects);

    switch ($next_step) {
      case ProjectCollector::NEXT_SCAN:
        return $count > 8 ? $this->formatPlural($count, '1 project', '@count projects') : $names($projects, TRUE);

      case ProjectCollector::NEXT_REMOVE:
        $parts = [];
        foreach ($this->groupByType($projects) as [$count_label]) {
          $parts[] = $count_label;
        }
        return $this->t('@list, all uninstalled', ['@list' => self::joinList($parts)]);

      case ProjectCollector::NEXT_UPDATE:
        $compatible = array_filter($projects, fn ($project) => !empty($project->info['upgrade_status_update_compatible']));
        $other = array_diff_key($projects, $compatible);
        $sentences = [];
        if ($compatible) {
          $sentences[] = $this->formatPlural(count($compatible), '@names becomes compatible by updating.', '@names become compatible by updating.', ['@names' => $names($compatible)]);
        }
        if ($other) {
          $sentences[] = $this->formatPlural(count($other), '@names has a newer release that is not compatible yet.', '@names have newer releases that are not compatible yet.', ['@names' => $names($other)]);
        }
        // The sentences are escaped already.
        return Markup::create(implode(' ', array_map('strval', $sentences)));

      case ProjectCollector::NEXT_COLLABORATE:
        $sentences = [$count > 8 ? (string) $this->formatPlural($count, '1 project.', '@count projects.') : Html::escape($names($projects)) . '.'];
        $latest = array_filter($projects, fn ($project) => ($project->info['upgrade_status_update'] ?? NULL) === ProjectCollector::UPDATE_ALREADY_INSTALLED);
        if (count($latest) === $count) {
          $sentences[] = (string) $this->t('All are on the latest release.');
        }
        return Markup::create(implode(' ', $sentences));

      case ProjectCollector::NEXT_RECTOR:
      case ProjectCollector::NEXT_MANUAL:
        $problems = 0;
        foreach ($projects as $project) {
          $problems += (int) $this->projectCollector->getProjectSummary($project)['result_count'];
        }
        return $this->formatPlural($problems, '@names, 1 problem', '@names, @count problems in total', ['@names' => $count > 8 ? $this->formatPlural($count, '1 project', '@count projects') : $names($projects, TRUE)]);

      case ProjectCollector::NEXT_REQUIREMENTS:
        return $count > 8 ? $this->formatPlural($count, '1 project.', '@count projects.') : Markup::create(Html::escape($names($projects)) . '.');

      case ProjectCollector::NEXT_RELAX:
        $installed = array_filter($projects, fn ($project) => !empty($project->status) || $project->getType() === 'recipe');
        $sentences = [
          (string) $this->t('@installed installed and @uninstalled uninstalled.', [
            '@installed' => count($installed),
            '@uninstalled' => $count - count($installed),
          ]),
        ];
        $newer = array_filter($installed, fn ($project) => ($project->info['upgrade_status_update'] ?? NULL) === ProjectCollector::UPDATE_AVAILABLE);
        if ($newer && count($newer) <= 8) {
          $sentences[] = (string) $this->formatPlural(count($newer), '@names has a newer compatible release.', '@names have newer compatible releases.', ['@names' => $names($newer)]);
        }
        return Markup::create(implode(' ', $sentences));
    }
    return '';
  }

  /**
   * Groups projects by their type, for the compact lists.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects, keyed by name.
   *
   * @return array
   *   Arrays with the label for the number of projects, the projects and the
   *   heading of each type, keyed by the type.
   */
  public function groupByType(array $projects): array {
    $groups = [];
    foreach (['module', 'theme', 'profile', 'recipe'] as $type) {
      $list = array_filter($projects, fn ($project) => $project->getType() === $type);
      if (!$list) {
        continue;
      }
      $count = count($list);
      // The strings are written out here, so the translation tools find them.
      $groups[$type] = match ($type) {
        'module' => [$this->formatPlural($count, '1 module', '@count modules'), $list, $this->t('Modules')],
        'theme' => [$this->formatPlural($count, '1 theme', '@count themes'), $list, $this->t('Themes')],
        'profile' => [$this->formatPlural($count, '1 profile', '@count profiles'), $list, $this->t('Profiles')],
        'recipe' => [$this->formatPlural($count, '1 recipe', '@count recipes'), $list, $this->t('Recipes')],
      };
    }
    return $groups;
  }

}
