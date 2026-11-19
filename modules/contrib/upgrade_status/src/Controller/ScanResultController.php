<?php

namespace Drupal\upgrade_status\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Extension;
use Drupal\upgrade_status\DeprecationAnalyzer;
use Drupal\upgrade_status\EnvironmentChecker;
use Drupal\upgrade_status\Form\ProjectAnalyzeForm;
use Drupal\upgrade_status\ProjectCollector;
use Drupal\upgrade_status\ReportBuilder;
use Drupal\upgrade_status\ScanResultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Scan result controller.
 */
class ScanResultController extends ControllerBase {

  /**
   * The scan result formatter service.
   *
   * @var \Drupal\upgrade_status\ScanResultFormatter
   */
  protected $resultFormatter;

  /**
   * The project collector service.
   *
   * @var \Drupal\upgrade_status\ProjectCollector
   */
  protected $projectCollector;

  /**
   * The deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\DeprecationAnalyzer
   */
  protected $deprecationAnalyzer;

  /**
   * Constructs a \Drupal\upgrade_status\Controller\ScanResultController.
   *
   * @param \Drupal\upgrade_status\ScanResultFormatter $result_formatter
   *   The scan result formatter service.
   * @param \Drupal\upgrade_status\ProjectCollector $project_collector
   *   The project collector service.
   * @param \Drupal\upgrade_status\DeprecationAnalyzer $deprecation_analyzer
   *   The deprecation analyzer.
   * @param \Drupal\upgrade_status\ReportBuilder $reportBuilder
   *   The builder of the report.
   * @param \Drupal\upgrade_status\EnvironmentChecker $environmentChecker
   *   The environment checker.
   */
  public function __construct(
    ScanResultFormatter $result_formatter,
    ProjectCollector $project_collector,
    DeprecationAnalyzer $deprecation_analyzer,
    protected ReportBuilder $reportBuilder,
    protected EnvironmentChecker $environmentChecker,
  ) {
    $this->resultFormatter = $result_formatter;
    $this->projectCollector = $project_collector;
    $this->deprecationAnalyzer = $deprecation_analyzer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('upgrade_status.result_formatter'),
      $container->get('upgrade_status.project_collector'),
      $container->get('upgrade_status.deprecation_analyzer'),
      $container->get('upgrade_status.report_builder'),
      $container->get('upgrade_status.environment_checker'),
    );
  }

  /**
   * Builds content for the error list page/popup.
   *
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return array
   *   Build array.
   */
  public function resultPage(string $project_machine_name) {
    $extension = $this->loadProject($project_machine_name);
    $build = $this->resultFormatter->formatResult($extension);
    $build['#actions'] = ['analyze' => $this->formBuilder()->getForm(ProjectAnalyzeForm::class, $project_machine_name)] + $build['#actions'];
    return $build;
  }

  /**
   * Gets the title of the result page of a project.
   *
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return \Drupal\Core\Render\Markup
   *   The project name and version.
   */
  public function resultTitle(string $project_machine_name) {
    return $this->resultFormatter->getTitle($this->loadProject($project_machine_name));
  }

  /**
   * Exports the full report, or the results of all projects.
   *
   * @param string $format
   *   One of 'report-html' and 'report-markdown' for the full report, or
   *   'codeclimate', 'checkstyle' and 'csv' for the results.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file download.
   */
  public function exportAll(string $format) {
    $projects = $this->projectCollector->collectProjects();
    $analyzed = array_filter($projects, fn ($project) => $this->resultFormatter->getRawResult($project) !== NULL);
    $filename = $this->resultFormatter->getExportFilename(str_starts_with($format, 'report-') ? 'report' : 'results');

    if ($format === 'report-html' || $format === 'report-markdown') {
      $environment = $this->environmentChecker->getChecks();
      if ($format === 'report-html') {
        $response = new Response($this->reportBuilder->renderReport($projects, ReportBuilder::SECTION_ORDER, $environment, $environment['status'], $analyzed));
        $filename .= '.html';
      }
      else {
        $sections = ['environment', ...array_keys(ReportBuilder::MARKDOWN_GROUPS)];
        $markdown = $this->reportBuilder->formatMarkdownReport($projects, $projects, $environment, $sections);
        if ($analyzed) {
          $markdown .= "\n\n" . $this->resultFormatter->formatMarkdownReport($analyzed);
        }
        $response = new Response($markdown);
        $response->headers->set('Content-Type', 'text/markdown; charset=utf-8');
        $filename .= '.md';
      }
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
      return $response;
    }
    if (!in_array($format, ['codeclimate', 'checkstyle', 'csv'], TRUE)) {
      throw new NotFoundHttpException();
    }
    $grouped = ['custom' => [], 'contrib' => []];
    foreach ($analyzed as $name => $project) {
      $grouped[ProjectCollector::isContributed($project) ? 'contrib' : 'custom'][$name] = $project;
    }
    return $this->resultFormatter->buildExportResponse($grouped, $format, $filename);
  }

  /**
   * Generates single project export.
   *
   * @param string $project_machine_name
   *   The machine name of the project.
   * @param string $format
   *   The format: html, markdown, csv, checkstyle or codeclimate.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object.
   */
  public function resultExport(string $project_machine_name, string $format) {
    $extension = $this->loadProject($project_machine_name);
    $type = $extension->info['upgrade_status_type'] == ProjectCollector::TYPE_CUSTOM ? 'custom' : 'contrib';
    $filename = $this->resultFormatter->getExportFilename('results', $project_machine_name);
    return $this->resultFormatter->buildExportResponse([$type => [$project_machine_name => $extension]], $format, $filename);
  }

  /**
   * Analyze a specific project in its own HTTP request.
   *
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response object.
   */
  public function analyze(string $project_machine_name) {
    if ($project_machine_name == 'upgrade_status_request_test') {
      // Handle the special case of a request test which is testing the
      // HTTP sandboxing capability.
      return new JsonResponse(
        ['message' => 'Request test success']
      );
    }
    else {
      // Dealing with a real project.
      $extension = $this->loadProject($project_machine_name);
      $this->deprecationAnalyzer->analyze($extension);
      return new JsonResponse(
        ['message' => $this->t('Analyzed @project', ['@project' => $extension->getName()])]
      );
    }
  }

  /**
   * Loads a project for a page.
   *
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return \Drupal\Core\Extension\Extension
   *   The project.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If there is no project with the name, like for an old link to a project
   *   that was removed.
   */
  protected function loadProject(string $project_machine_name): Extension {
    try {
      return $this->projectCollector->loadProject($project_machine_name);
    }
    catch (UnknownExtensionException) {
      throw new NotFoundHttpException();
    }
  }

}
