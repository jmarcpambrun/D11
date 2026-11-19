<?php

namespace Drupal\upgrade_status\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\upgrade_status\AnalysisBatch;
use Drupal\upgrade_status\DeprecationAnalyzer;
use Drupal\upgrade_status\EnvironmentChecker;
use Drupal\upgrade_status\Icon;
use Drupal\upgrade_status\ProjectCollector;
use Drupal\upgrade_status\ReportBuilder;
use Drupal\upgrade_status\ScanResultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Upgrade Status Form.
 */
class UpgradeStatusForm extends FormBase {

  /**
   * The project collector service.
   *
   * @var \Drupal\upgrade_status\ProjectCollector
   */
  protected $projectCollector;

  /**
   * The scan result formatter service.
   *
   * @var \Drupal\upgrade_status\ScanResultFormatter
   */
  protected $resultFormatter;

  /**
   * The deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\DeprecationAnalyzer
   */
  protected $deprecationAnalyzer;

  /**
   * The destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destination;

  /**
   * The next Drupal core major version.
   *
   * @var int
   */
  protected $nextMajor;

  /**
   * Whether the environment of the analyzer is ready.
   *
   * @var bool
   */
  protected $analyzerReady = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('upgrade_status.project_collector'),
      $container->get('upgrade_status.result_formatter'),
      $container->get('upgrade_status.deprecation_analyzer'),
      $container->get('redirect.destination'),
      $container->get('upgrade_status.environment_checker'),
      $container->get('upgrade_status.report_builder'),
      $container->get('upgrade_status.analysis_batch'),
    );
  }

  /**
   * Constructs a Drupal\upgrade_status\Form\UpgradeStatusForm.
   *
   * @param \Drupal\upgrade_status\ProjectCollector $project_collector
   *   The project collector service.
   * @param \Drupal\upgrade_status\ScanResultFormatter $result_formatter
   *   The scan result formatter service.
   * @param \Drupal\upgrade_status\DeprecationAnalyzer $deprecation_analyzer
   *   The deprecation analyzer.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination
   *   The destination service.
   * @param \Drupal\upgrade_status\EnvironmentChecker $environmentChecker
   *   The environment checker.
   * @param \Drupal\upgrade_status\ReportBuilder $reportBuilder
   *   The builder of the parts of the report page.
   * @param \Drupal\upgrade_status\AnalysisBatch $analysisBatch
   *   The batch that analyzes projects.
   */
  public function __construct(
    ProjectCollector $project_collector,
    ScanResultFormatter $result_formatter,
    DeprecationAnalyzer $deprecation_analyzer,
    RedirectDestinationInterface $destination,
    // Not readonly, because forms are serialized with
    // DependencySerializationTrait, which can't set readonly properties of
    // subclasses on PHP 8.3.
    protected EnvironmentChecker $environmentChecker,
    protected ReportBuilder $reportBuilder,
    protected AnalysisBatch $analysisBatch,
  ) {
    $this->projectCollector = $project_collector;
    $this->resultFormatter = $result_formatter;
    $this->deprecationAnalyzer = $deprecation_analyzer;
    $this->destination = $destination;
    $this->nextMajor = ProjectCollector::getDrupalCoreMajorVersion() + 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupal_upgrade_status_summary_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'upgrade-status';
    $form['#attached']['library'][] = 'upgrade_status/upgrade_status.admin';
    $form['#attached']['library'][] = 'core/drupal.tableselect';

    $analyzer_ready = TRUE;
    try {
      $this->deprecationAnalyzer->initEnvironment();
    }
    catch (\Exception $e) {
      $analyzer_ready = $this->analyzerReady = FALSE;
      // Message and impact description is not translated as the message
      // is sourced from an exception thrown.
      $this->messenger()->addError($e->getMessage() . ' Analysis is not possible until this is resolved.');
    }

    $form['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['us-header']],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['us-header__actions']],
        'export' => ReportBuilder::buildMenu($this->t('Export'), [
          'report-html' => $this->t('Export full report as HTML'),
          'report-markdown' => $this->t('Export full report as Markdown'),
          'codeclimate' => $this->t('Export project results as Code Climate JSON'),
          'checkstyle' => $this->t('Export project results as Checkstyle XML'),
          'csv' => $this->t('Export project results as CSV'),
        ], 'upgrade_status.export_all', 'down', [], TRUE),
      ],
    ];

    $projects = $this->projectCollector->collectProjects();
    $environment = $this->environmentChecker->getChecks();
    foreach ($environment['warnings'] as $warning) {
      $this->messenger()->addWarning($warning);
    }
    $form['accuracy'] = $this->reportBuilder->buildAccuracyCard($projects, $projects, $this->destination->getAsArray());
    $form['summary'] = $this->reportBuilder->buildSummaryCard($projects, $environment['status']);
    $form['environment'] = $this->reportBuilder->buildEnvironmentSection($environment);

    $next_steps = $this->projectCollector->getNextStepInfo();
    foreach (ReportBuilder::SECTION_ORDER as $next_step) {
      $sublist = array_filter($projects, fn (Extension $project) => $project->info['upgrade_status_next'] == $next_step);
      if (empty($sublist)) {
        continue;
      }
      uasort($sublist, fn (Extension $a, Extension $b) => strcasecmp($a->info['name'], $b->info['name']));
      $data = $this->buildSectionContent($next_step, $sublist);
      // "Select all" of a compact list goes next to the description.
      $intro = [
        '#type' => 'container',
        '#attributes' => ['class' => ['us-section-intro']],
        'description' => [
          '#markup' => '<p class="us-description">' . $next_steps[$next_step][1] . '</p>',
        ],
      ];
      if (isset($data['select_all'])) {
        $intro['select_all'] = $data['select_all'];
        unset($data['select_all']);
      }
      $form[$next_step] = [
        '#type' => 'details',
        '#id' => 'edit-' . $next_step,
        '#title' => $this->reportBuilder->buildSectionTitle($next_step, $next_steps[$next_step][0], $this->formatPlural(count($sublist), '1 project', '@count projects'), $this->reportBuilder->getSectionSummary($next_step, $sublist)),
        '#open' => TRUE,
        '#attributes' => ['class' => ['us-section', 'upgrade-status-next-step']],
        '#tree' => TRUE,
        'intro' => $intro,
        'data' => $data,
      ];
    }

    $form['bulk'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['us-bulk-bar'],
        'data-us-bulk-bar' => '',
      ],
      'status' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['us-bulk-bar__status']],
        'count' => [
          '#markup' => '<span class="us-bulk-bar__count" aria-live="polite" data-us-count>' . $this->t('Select projects to analyze or export.') . '</span><span class="us-bulk-bar__warning" data-us-warning hidden></span>',
        ],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['us-bulk-bar__actions']],
        'clear' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Clear selection'),
          '#attributes' => [
            'type' => 'button',
            'class' => ['us-text-button', 'js-show'],
            'data-us-clear' => '',
          ],
        ],
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Analyze selected'),
          '#button_type' => 'primary',
          '#disabled' => !$analyzer_ready,
        ],
        'export' => ReportBuilder::buildMenu($this->t('Export selected'), [], NULL, 'up') + $this->buildExportButtons(),
      ],
    ];

    return $form;
  }

  /**
   * Builds the export buttons of the bulk actions bar.
   *
   * @return array
   *   Submit buttons keyed by the export format.
   */
  protected function buildExportButtons(): array {
    $buttons = [];
    foreach ([
      'html' => $this->t('Export selected as HTML'),
      'markdown' => $this->t('Export selected as Markdown'),
      'codeclimate' => $this->t('Export selected as Code Climate JSON'),
      'checkstyle' => $this->t('Export selected as Checkstyle XML'),
      'csv' => $this->t('Export selected as CSV'),
    ] as $format => $label) {
      $buttons['export_' . $format] = [
        '#type' => 'submit',
        '#value' => $label,
        '#name' => 'export_' . $format,
        '#export_format' => $format,
        '#submit' => ['::exportReport'],
        '#attributes' => ['role' => 'menuitem'],
      ];
    }
    return $buttons;
  }

  /**
   * Builds the content of a group of projects.
   *
   * @param string $next_step
   *   The next step of the projects.
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects, keyed by name.
   *
   * @return array
   *   A table or compact lists, with a checkbox for each project.
   */
  protected function buildSectionContent(string $next_step, array $projects): array {
    $next_major = $this->nextMajor;
    switch ($next_step) {
      case ProjectCollector::NEXT_REMOVE:
        return $this->buildCompactLists($next_step, $projects);

      case ProjectCollector::NEXT_SCAN:
        return $this->buildTable($next_step, $projects, [
          'project' => $this->t('Project'),
          'type' => $this->t('Type'),
          'status' => $this->t('Status'),
          'action' => ReportBuilder::hiddenHeader($this->t('Action'), 'us-col-action'),
        ]);

      case ProjectCollector::NEXT_UPDATE:
        // Projects that become compatible by updating come first.
        uasort($projects, fn ($a, $b) => (int) empty($a->info['upgrade_status_update_compatible']) <=> (int) empty($b->info['upgrade_status_update_compatible']));
        return $this->buildTable($next_step, $projects, [
          'project' => $this->t('Project'),
          'version' => $this->t('Installed'),
          'arrow' => ReportBuilder::hiddenHeader($this->t('Updates to'), 'us-col-arrow'),
          'update' => $this->t('Available'),
          'update_ready' => $this->t('Available release on Drupal @version', ['@version' => $next_major]),
          'result' => $this->t('Local analysis'),
          'issues' => $this->t('Drupal.org'),
        ]);

      case ProjectCollector::NEXT_COLLABORATE:
        return $this->buildTable($next_step, $projects, [
          'project' => $this->t('Project'),
          'version' => $this->t('Installed'),
          'result' => $this->t('Local analysis'),
          'review' => $this->t('Drupal.org'),
        ]);

      case ProjectCollector::NEXT_RECTOR:
      case ProjectCollector::NEXT_MANUAL:
      case ProjectCollector::NEXT_REQUIREMENTS:
        return $this->buildTable($next_step, $projects, [
          'project' => $this->t('Project'),
          'type' => $this->t('Type'),
          'result' => $this->t('Local analysis'),
        ]);

      case ProjectCollector::NEXT_RELAX:
        $installed = array_filter($projects, fn ($project) => !empty($project->status) || $project->getType() === 'recipe');
        $build = [];
        if ($installed) {
          $build['installed_heading'] = ['#markup' => '<h3 class="us-compact__heading">' . $this->t('Installed · @count', ['@count' => count($installed)]) . '</h3>'];
          $build['list'] = $this->buildTable($next_step, $installed, [
            'project' => $this->t('Project'),
            'version' => $this->t('Installed'),
            'arrow' => ReportBuilder::hiddenHeader($this->t('Updates to'), 'us-col-arrow'),
            'update' => $this->t('Newer compatible release'),
            'result' => $this->t('Local analysis'),
          ]);
        }
        if ($uninstalled = array_diff_key($projects, $installed)) {
          $build['uninstalled_heading'] = ['#markup' => '<h3 class="us-compact__heading">' . $this->t('Uninstalled · @count', ['@count' => count($uninstalled)]) . '</h3>'];
          $build['uninstalled'] = $this->buildCompactLists($next_step, $uninstalled, FALSE);
        }
        return $build;
    }
    return [];
  }

  /**
   * Builds a table of projects with a checkbox for each.
   *
   * @param string $next_step
   *   The next step of the projects.
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects, keyed by name.
   * @param array $header
   *   The header cells, keyed by the column.
   *
   * @return array
   *   The table element.
   */
  protected function buildTable(string $next_step, array $projects, array $header): array {
    $table = [
      '#type' => 'table',
      '#header' => ['select' => ['class' => ['select-all']]] + $header,
      '#attributes' => ['class' => ['us-table']],
      '#prefix' => '<div class="us-table-wrap">',
      '#suffix' => '</div>',
    ];
    foreach ($projects as $name => $project) {
      $summary = $this->projectCollector->getProjectSummary($project);
      $row = [
        '#attributes' => ['class' => ['us-cat--' . $next_step, 'project-' . $name]],
        'select' => $this->buildCheckbox($next_step, $name, $project),
      ];
      foreach (array_keys($header) as $column) {
        $row[$column] = $this->buildCell($column, $name, $project, $summary);
      }
      $table[$name] = $row;
    }
    return $table;
  }

  /**
   * Builds a cell of a project table.
   *
   * @param string $column
   *   The column.
   * @param string $name
   *   The project name.
   * @param \Drupal\Core\Extension\Extension $project
   *   The project.
   * @param array $summary
   *   The summary from ProjectCollector::getProjectSummary().
   *
   * @return array
   *   The render array of the cell.
   */
  protected function buildCell(string $column, string $name, Extension $project, array $summary): array {
    switch ($column) {
      case 'project':
        $markup = '<span class="us-project-line"><span class="us-project-name">' . Html::escape($project->info['name']) . '</span>';
        if (!empty($project->info['upgrade_status_update_security'])) {
          $markup .= '<span class="us-pill us-pill--error">' . Icon::render('shield-warning', 14) . $this->t('Security update') . '</span>';
        }
        $markup .= '</span><span class="us-machine-name us-mono">' . Html::escape($name) . '</span>';
        return ['#markup' => Markup::create($markup)];

      case 'type':
      case 'status':
        return ['#markup' => $summary[$column]];

      case 'version':
        return ['#markup' => '<span class="us-mono">' . Html::escape((string) $summary['version']) . '</span>'];

      case 'arrow':
        return ['#markup' => !empty($summary['update_url']) ? Icon::render('arrow-right', 14) : ''];

      case 'update':
        if (!empty($summary['update_url'])) {
          return [
            '#type' => 'link',
            '#title' => $summary['update_version'],
            '#url' => Url::fromUri($summary['update_url']),
            '#attributes' => ['class' => ['us-mono']],
          ];
        }
        return ['#markup' => '<span class="us-machine-name">' . $this->t('None') . '</span>'];

      case 'update_ready':
        $compatible = !empty($project->info['upgrade_status_update_compatible']);
        return [
          '#markup' => Markup::create('<span class="us-ready us-ready--' . ($compatible ? 'ok' : 'warning') . '">' . Icon::render($compatible ? 'check-circle' : 'warning', 16, NULL, $compatible ? 'us-status--ok' : 'us-status--warning') . ($compatible ? $this->t('Compatible') : $this->t('Not yet')) . '</span>'),
        ];

      case 'result':
        return $this->buildAnalysisCell($name, $summary);

      case 'action':
        return $this->buildAnalyzeButton($name, $project) + ['#wrapper_attributes' => ['class' => ['us-col-action']]];

      case 'issues':
      case 'review':
        if (empty($summary['issues_url'])) {
          return ['#markup' => $this->t('Not applicable')];
        }
        return [
          '#type' => 'link',
          '#title' => $column === 'review' ? $this->t('Review issues') : $this->t('Issues', [], ['context' => 'Drupal.org issues']),
          '#url' => Url::fromUri($summary['issues_url']),
        ];
    }
    return [];
  }

  /**
   * Builds the local analysis cell of a project.
   *
   * @param string $name
   *   The project name.
   * @param array $summary
   *   The summary from ProjectCollector::getProjectSummary().
   *
   * @return array
   *   A link to the results, or a "Not analyzed" pill with an analyze button.
   */
  protected function buildAnalysisCell(string $name, array $summary): array {
    if ($summary['result_count'] === NULL) {
      return [
        'pill' => ['#markup' => '<span class="us-pill us-pill--neutral">' . $this->t('Not analyzed') . '</span> '],
        'analyze' => $this->buildAnalyzeButton($name),
      ];
    }
    return [
      'link' => [
        '#type' => 'link',
        '#title' => $summary['result_problems'],
        '#url' => Url::fromRoute('upgrade_status.project', ['project_machine_name' => $name]),
      ],
      // Messages for later versions are not problems for the next major
      // version, so the number is a quiet second line.
      'later' => $summary['result_later'] ? ['#markup' => '<span class="us-qualifier">' . $summary['result_later'] . '</span>'] : [],
    ];
  }

  /**
   * Builds a button that analyzes one project.
   *
   * @param string $name
   *   The project name.
   * @param \Drupal\Core\Extension\Extension|null $project
   *   The project, for the accessible name.
   *
   * @return array
   *   The submit button.
   */
  protected function buildAnalyzeButton(string $name, ?Extension $project = NULL): array {
    return [
      '#type' => 'submit',
      '#value' => $this->t('Analyze'),
      '#name' => 'analyze_' . $name,
      // Not in the list of selected projects, where the button is shown.
      '#parents' => ['analyze', $name],
      '#project' => $name,
      '#submit' => ['::analyzeOne'],
      '#limit_validation_errors' => [],
      '#disabled' => !$this->analyzerReady,
      '#attributes' => [
        'aria-label' => $this->t('Analyze @name', ['@name' => $project ? $project->info['name'] : $name]),
        'class' => ['button--small'],
      ],
    ];
  }

  /**
   * Builds the checkbox that selects a project.
   *
   * @param string $next_step
   *   The next step of the project.
   * @param string $name
   *   The project name.
   * @param \Drupal\Core\Extension\Extension $project
   *   The project.
   *
   * @return array
   *   The checkbox, with the value in [next step][data][list][name].
   */
  protected function buildCheckbox(string $next_step, string $name, Extension $project): array {
    return [
      '#type' => 'checkbox',
      '#title' => $this->t('Select @name', ['@name' => $project->info['name']]),
      '#title_display' => 'invisible',
      '#return_value' => $name,
      '#default_value' => FALSE,
      '#parents' => [$next_step, 'data', 'list', $name],
      '#attributes' => [
        'class' => ['us-select'],
        'data-us-analyzed' => $this->projectCollector->getResultTotals($name) ? '1' : '0',
      ],
    ];
  }

  /**
   * Builds compact lists of projects, grouped by type.
   *
   * The checkboxes are rendered without their form item wrappers, next to
   * labels with the project names. Admin themes would otherwise show single
   * checkboxes as toggles, or place them outside of the list.
   *
   * @param string $next_step
   *   The next step of the projects.
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   The projects, keyed by name.
   * @param bool $select_all
   *   Whether to add a checkbox that selects all of them.
   *
   * @return array
   *   The lists.
   */
  protected function buildCompactLists(string $next_step, array $projects, bool $select_all = TRUE): array {
    $group = 'us-compact-' . $next_step;
    $build = [];
    if ($select_all) {
      $id = Html::getId('edit-' . $group . '-select-all');
      $build['select_all'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['us-compact__select-all', 'js-show']],
        'checkbox' => [
          '#type' => 'checkbox',
          '#id' => $id,
          '#parents' => [$next_step, 'select_all'],
          '#attributes' => ['data-us-select-all' => $group],
          '#theme_wrappers' => [],
        ],
        'label' => ['#markup' => '<label for="' . $id . '">' . $this->t('Select all @count', ['@count' => count($projects)]) . '</label>'],
      ];
    }
    foreach ($this->reportBuilder->groupByType($projects) as $type => [, $list, $heading]) {
      $build[$type . '_heading'] = ['#markup' => '<h3 class="us-compact__heading">' . $heading . ' · ' . count($list) . '</h3>'];
      $build[$type] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['us-compact__list'], 'data-us-group' => $group],
      ];
      foreach ($list as $name => $project) {
        $summary = $this->projectCollector->getProjectSummary($project);
        $id = Html::getId('edit-' . $next_step . '-' . $name);
        $checkbox = $this->buildCheckbox($next_step, $name, $project);
        $checkbox['#id'] = $id;
        $checkbox['#theme_wrappers'] = [];
        $build[$type][$name] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['us-compact__item', 'project-' . $name]],
          'select' => $checkbox,
          'label' => [
            '#markup' => Markup::create('<label class="us-compact__label" for="' . $id . '"><span class="us-project-name">' . Html::escape($project->info['name']) . '</span><span class="us-machine-name us-mono">' . Html::escape($name) . '</span></label>'),
          ],
          'problems' => $summary['result_count'] === NULL ?
            ['#markup' => '<span class="us-compact__problems us-machine-name">' . $this->t('Not analyzed') . '</span>'] :
            [
              '#type' => 'link',
              '#title' => $summary['result_problems'],
              '#url' => Url::fromRoute('upgrade_status.project', ['project_machine_name' => $name]),
              '#attributes' => ['class' => ['us-compact__problems']],
            ],
        ];
      }
    }
    return $build;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->analysisBatch->start($this->getSelectedProjects($form_state));
  }

  /**
   * Form submission handler of the analyze button of one project.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function analyzeOne(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getTriggeringElement()['#project'];
    $projects = $this->projectCollector->collectProjects();
    $this->analysisBatch->start(isset($projects[$name]) ? [$projects[$name]] : []);
  }

  /**
   * Gets the selected projects.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The selected projects, keyed by name.
   */
  protected function getSelectedProjects(FormStateInterface $form_state): array {
    $list = [];
    $projects = $this->projectCollector->collectProjects();
    $submitted = $form_state->getValues();
    foreach (array_keys($this->projectCollector->getNextStepInfo()) as $next_step) {
      foreach ($submitted[$next_step]['data']['list'] ?? [] as $item) {
        if (isset($projects[$item])) {
          $list[$item] = $projects[$item];
        }
      }
    }
    return $list;
  }

  /**
   * Form submission handler of the export buttons.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function exportReport(array &$form, FormStateInterface $form_state) {
    $format = $form_state->getTriggeringElement()['#export_format'] ?? 'html';
    $extensions = ['custom' => [], 'contrib' => []];
    foreach ($this->getSelectedProjects($form_state) as $name => $project) {
      $extensions[ProjectCollector::isContributed($project) ? 'contrib' : 'custom'][$name] = $project;
    }

    if (empty($extensions['custom']) && empty($extensions['contrib'])) {
      $this->messenger()->addError($this->t('No projects selected to export.'));
      return;
    }

    $filename = $this->resultFormatter->getExportFilename('results');
    $form_state->setResponse($this->resultFormatter->buildExportResponse($extensions, $format, $filename));
  }

  /**
   * Dynamic page title for the form to make the status target clear.
   */
  public function getTitle() {
    return $this->t('Drupal @version upgrade status', ['@version' => $this->nextMajor]);
  }

}
