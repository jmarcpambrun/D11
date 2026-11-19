<?php

namespace Drupal\upgrade_status\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\upgrade_status\AnalysisBatch;
use Drupal\upgrade_status\ProjectCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Analyzes one project again, from its result page.
 */
class ProjectAnalyzeForm extends FormBase {

  /**
   * Constructs a new project analyze form.
   *
   * @param \Drupal\upgrade_status\ProjectCollector $projectCollector
   *   The project collector.
   * @param \Drupal\upgrade_status\AnalysisBatch $analysisBatch
   *   The batch that analyzes projects.
   */
  public function __construct(
    protected ProjectCollector $projectCollector,
    protected AnalysisBatch $analysisBatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('upgrade_status.project_collector'),
      $container->get('upgrade_status.analysis_batch'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'upgrade_status_project_analyze';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $project_machine_name = NULL) {
    $form_state->set('project', $project_machine_name);
    $form['analyze'] = [
      '#type' => 'submit',
      '#value' => $this->t('Analyze again'),
      '#attributes' => ['class' => ['button--small']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->get('project');
    $projects = $this->projectCollector->collectProjects();
    if (isset($projects[$name])) {
      $this->analysisBatch->start([$name => $projects[$name]]);
    }
    $form_state->setRedirect('upgrade_status.project', ['project_machine_name' => $name]);
  }

}
