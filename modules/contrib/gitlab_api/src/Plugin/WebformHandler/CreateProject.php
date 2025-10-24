<?php

namespace Drupal\gitlab_api\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gitlab_api\Api;
use Drupal\gitlab_api\Event\CreateProject as CreateProjectEvent;
use Drupal\gitlab_api\GitLabEvents;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Webform submission handler to create a new GitLab project.
 *
 * @WebformHandler(
 *   id = "gitlab_api_create_project",
 *   label = @Translation("Create Project"),
 *   category = @Translation("GitLab"),
 *   description = @Translation("Create a new GitLab project."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 *   tokens = TRUE,
 * )
 */
class CreateProject extends WebformHandlerBase {

  /**
   * The GitLab API service.
   *
   * @var \Drupal\gitlab_api\Api
   */
  protected Api $api;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): CreateProject {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->api = $container->get('gitlab_api.api');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'namespace' => '',
      'path_pattern' => '',
      'name_pattern' => '',
      'field_gitlab_project' => '',
      'field_gitlab_url' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $webform = $this->getWebform();
    $fields = [];
    foreach ($webform->getElementsInitializedAndFlattened() as $item) {
      $fields[$item['#webform_key']] = $item['#title'];
    }

    $form['namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Namespace'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['namespace'],
    ];
    $form['path_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project path pattern'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['path_pattern'],
    ];
    $form['name_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project name pattern'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['name_pattern'],
    ];
    $form['field_gitlab_project'] = [
      '#type' => 'select',
      '#title' => $this->t('Field to store GitLab project'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_gitlab_project'],
    ];
    $form['field_gitlab_url'] = [
      '#type' => 'select',
      '#title' => $this->t('Field to store GitLab URL'),
      '#options' => $fields,
      '#required' => TRUE,
      '#default_value' => $this->configuration['field_gitlab_url'],
    ];

    $this->elementTokenValidate($form);

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
    if (!$update && $webform_submission->getState() === WebformSubmissionInterface::STATE_COMPLETED) {

      $data = $webform_submission->getData();

      $path = $this->replaceTokens($this->configuration['path_pattern'], $webform_submission);
      $name = $this->replaceTokens($this->configuration['name_pattern'], $webform_submission);
      if (is_array($path) || is_array($name)) {
        return;
      }
      $project = $this->api->createProject($this->configuration['namespace'], $path, $name);
      $data[$this->configuration['field_gitlab_project']] = json_encode($project);
      $data[$this->configuration['field_gitlab_url']] = $project['ssh_url_to_repo'];
      $webform_submission->setData($data);
      $webform_submission->resave();
      $event = new CreateProjectEvent($webform_submission, $project);
      $this->eventDispatcher->dispatch($event, GitLabEvents::CREATEPROJECT);
    }
  }

}
