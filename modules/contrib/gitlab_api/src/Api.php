<?php

namespace Drupal\gitlab_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\gitlab_api\Entity\GitlabServer;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\ResultPager;
use Http\Client\Exception;

/**
 * GitLab API wrapper class.
 *
 * @package Drupal\gitlab_api
 */
class Api {

  /**
   * The module config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The GitLab client.
   *
   * @var \Gitlab\Client|null
   */
  protected ?Client $client;

  /**
   * The GitLab server entity.
   *
   * @var \Drupal\gitlab_api\Entity\GitlabServer|null
   */
  protected ?GitlabServer $server = NULL;

  /**
   * Api constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Drupal config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('gitlab_api.settings');
  }

  /**
   * Initialize client and authenticate session.
   */
  protected function init(): void {
    if ($this->server === NULL) {
      $this->switchServer();
    }
    if ($this->client === NULL) {
      $this->client = new Client();
      $this->client->setUrl($this->server->getUrl());
      $this->client->authenticate($this->server->getAuthToken(), Client::AUTH_HTTP_TOKEN);
    }
  }

  /**
   * Return the initialized client for the current server.
   *
   * @return \Gitlab\Client
   *   The initialized client.
   */
  public function getClient(): Client {
    $this->init();
    return $this->client;
  }

  /**
   * Allow to switch between gitlab server.
   *
   * @param string|null $server_id
   *   The GitLab server config entity ID.
   */
  public function switchServer(?string $server_id = NULL): void {
    if ($server_id && $server = GitlabServer::load($server_id)) {
      $this->server = $server;
    }
    else {
      $this->server = GitlabServer::loadDefaultServer();
    }
    $this->client = NULL;
  }

  /**
   * Creates a new GitLab project.
   *
   * @param string $namespace
   *   The namespace.
   * @param string $path
   *   The path.
   * @param string $name
   *   The name.
   *
   * @return array
   *   The created project.
   */
  public function createProject(string $namespace, string $path, string $name): array {
    $this->init();

    $gitlab_namespace = $this->client->namespaces()->show($namespace);
    if (!$gitlab_namespace) {
      throw new \InvalidArgumentException('Invalid namespace');
    }

    return $this->client->projects()->create($name, [
      'namespace_id' => $gitlab_namespace['id'],
      'path' => $path,
    ]);
  }

  /**
   * Creates a commit to CRUD file(s) in the repository.
   *
   * @param int $project_id
   *   The project ID.
   * @param string $branch
   *   The branch.
   * @param string $message
   *   The commit message.
   * @param \Drupal\gitlab_api\CommitAction[] $actions
   *   List of commit actions.
   *
   * @return array
   *   The commit details.
   */
  public function createCommit(int $project_id, string $branch, string $message, array $actions): array {
    $this->init();
    $actionsArray = [];
    foreach ($actions as $action) {
      $actionsArray[] = $action->toArray();
    }
    return $this->client->repositories()->createCommit($project_id, [
      'branch' => $branch,
      'commit_message' => $message,
      'actions' => $actionsArray,
    ]);
  }

  /**
   * Triggers a GitLab pipeline.
   *
   * @param int $project_id
   *   The project ID.
   * @param string $commit_ref
   *   The commit reference.
   * @param array $variables
   *   Optional extra variables.
   *
   * @return array
   *   The created pipeline.
   *
   * @deprecated in gitlab_api:2.1.2 and is removed from gitlab_api:3.0.0.
   *    Use ::getClient()->createPipeline() instead.
   * @see https://www.drupal.org/project/gitlab_api/issues/3384270
   */
  public function createPipeline(int $project_id, string $commit_ref, array $variables = []): array {
    $this->init();
    return $this->client->projects()
      ->createPipeline($project_id, $commit_ref, $variables);
  }

  /**
   * Create an issue.
   *
   * @param int $project_id
   *   The project ID.
   * @param string $title
   *   The issue title.
   * @param string $body
   *   The issue body text.
   * @param int[] $assignee_ids
   *   The list of user ids of assignees.
   * @param \DateTime|null $due_date
   *   The due date.
   * @param array $labels
   *   The issue labels.
   *
   * @return array
   *   The issue.
   */
  public function createIssue(int $project_id, string $title, string $body, array $assignee_ids = [], ?\DateTime $due_date = NULL, array $labels = []): array {
    $this->init();
    $params = [
      'title' => $title,
      'description' => $body,
      'assignee_ids' => $assignee_ids,
    ];
    if ($due_date) {
      $params['due_date'] = $due_date->format('Y-m-d');
    }
    if (count($labels) > 0) {
      $params['labels'] = implode(',', $labels);
    }

    return $this->client->issues()->create($project_id, $params);
  }

  /**
   * Gets a list of namespaces.
   *
   * @return array
   *   The list of namespaces.
   *
   * @deprecated in gitlab_api:2.1.2 and is removed from gitlab_api:3.0.0.
   *     Use ::getClient()->namespaces()->all() instead.
   * @see https://www.drupal.org/project/gitlab_api/issues/3384270
   */
  public function namespaces(): array {
    $this->init();
    return $this->client->namespaces()->all();
  }

  /**
   * Gets a list of projects.
   *
   * @param bool $simple
   *   If TRUE, only limited number of fields for each projects get returned.
   * @param bool $includeArchived
   *   If TRUE, also archived projects will be returned.
   * @param array $additionalParams
   *   Optional extra arguments.
   *
   * @return array
   *   The list of projects.
   */
  public function projects(bool $simple = TRUE, bool $includeArchived = FALSE, array $additionalParams = []): array {
    $this->init();
    $pager = new ResultPager($this->client);
    $params = [
      'simple' => $simple,
      'archived' => FALSE,
    ];
    if ($includeArchived) {
      unset($params['archived']);
    }
    $params += $additionalParams;
    try {
      return $pager->fetchAll($this->client->projects(), 'all', [$params]);
    }
    catch (Exception $e) {
      return [];
    }
  }

  /**
   * Gets a project.
   *
   * @param int $project_id
   *   The project ID.
   *
   * @return array
   *   The project.
   */
  public function project(int $project_id): array {
    $this->init();
    return $this->client->projects()->show($project_id);
  }

  /**
   * Gets a list of project pipelines.
   *
   * @param int $project_id
   *   The project ID.
   * @param array $additionalParams
   *   Optional extra arguments.
   *
   * @return array
   *   The list of project pipelines.
   */
  public function pipelines(int $project_id, array $additionalParams = []): array {
    $this->init();
    $pager = new ResultPager($this->client);

    $params = [];
    $params += $additionalParams;
    try {
      return $pager->fetchAll($this->client->projects(), 'pipelines', [
        $project_id,
        $params,
      ]);
    }
    catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get a project pipeline.
   *
   * @param int $project_id
   *   The project ID.
   * @param int $pipeline_id
   *   The pipeline ID.
   *
   * @return array
   *   The project pipeline.
   *
   * @deprecated in gitlab_api:2.1.2 and is removed from gitlab_api:3.0.0.
   *     Use ::getClient()->projects()->pipeline() instead.
   * @see https://www.drupal.org/project/gitlab_api/issues/3384270
   */
  public function pipeline(int $project_id, int $pipeline_id): array {
    $this->init();
    return $this->client->projects()->pipeline($project_id, $pipeline_id);
  }

  /**
   * Get the jobs of a pipeline.
   *
   * @param int $project_id
   *   The project ID.
   * @param int $pipeline_id
   *   The pipeline ID.
   *
   * @return array
   *   The list of pipeline jobs.
   *
   * @deprecated in gitlab_api:2.1.2 and is removed from gitlab_api:3.0.0.
   *      Use ::getClient()->pipelineJobs() instead.
   * @see https://www.drupal.org/project/gitlab_api/issues/3384270
   */
  public function jobs(int $project_id, int $pipeline_id): array {
    $this->init();
    return $this->client->jobs()->pipelineJobs($project_id, $pipeline_id);
  }

  /**
   * Get a project's pipeline job.
   *
   * @param int $project_id
   *   The project ID.
   * @param int $job_id
   *   The job ID.
   *
   * @return array
   *   The job.
   */
  public function job(int $project_id, int $job_id): array {
    $this->init();
    return $this->client->jobs()->show($project_id, $job_id);
  }

  /**
   * Gets a list of project issues.
   *
   * @param int $project_id
   *   The project ID.
   * @param string|null $state
   *   The state of issues to retrieve, can be "opened" or "closed".
   * @param int|null $assignee_id
   *   The assignee ID.
   * @param array $additionalParams
   *   Optional extra arguments.
   *
   * @return array
   *   The list of project issues.
   */
  public function issues(int $project_id, ?string $state = NULL, ?int $assignee_id = NULL, array $additionalParams = []): array {
    $this->init();
    $pager = new ResultPager($this->client);
    $params = [];
    if (in_array($state, ['opened', 'closed'])) {
      $params['state'] = $state;
    }
    if ($assignee_id) {
      $params['assignee_id'] = $assignee_id;
    }
    $params += $additionalParams;
    try {
      return $pager->fetchAll($this->client->issues(), 'all', [
        $project_id,
        $params,
      ]);
    }
    catch (Exception $e) {
      return [];
    }
  }

  /**
   * Gets a project issue.
   *
   * @param int $project_id
   *   The project ID.
   * @param int $issue_iid
   *   The issue ID.
   *
   * @return array
   *   The issue.
   */
  public function issue(int $project_id, int $issue_iid): array {
    $this->init();
    return $this->client->issues()->show($project_id, $issue_iid);
  }

  /**
   * Gets a list of issue links.
   *
   * @param int $project_id
   *   The project ID.
   * @param int $issue_iid
   *   The issue ID.
   *
   * @return array
   *   The issue links.
   */
  public function issueLinks(int $project_id, int $issue_iid): array {
    $this->init();
    $pager = new ResultPager($this->client);
    try {
      return $pager->fetchAll($this->client->issueLinks(), 'all', [
        $project_id,
        $issue_iid,
      ]);
    }
    catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get a list of project branches.
   *
   * @param int $project_id
   *   The project ID.
   *
   * @return array
   *   The list of branches.
   *
   * @deprecated in gitlab_api:2.1.2 and is removed from gitlab_api:3.0.0.
   *      Use ::getClient()->repositories()->branches() instead.
   * @see https://www.drupal.org/project/gitlab_api/issues/3384270
   */
  public function branches(int $project_id): array {
    $this->init();
    return $this->client->repositories()->branches($project_id);
  }

  /**
   * Get a project branch.
   *
   * @param int $project_id
   *   The project ID.
   * @param string $branch
   *   The branch.
   *
   * @return array
   *   The branch.
   *
   * @deprecated in gitlab_api:2.1.2 and is removed from gitlab_api:3.0.0.
   *      Use ::getClient()->repositories()->branch instead.
   * @see https://www.drupal.org/project/gitlab_api/issues/3384270
   */
  public function branch(int $project_id, string $branch): array {
    $this->init();
    return $this->client->repositories()->branch($project_id, $branch);
  }

  /**
   * Add a topic to a project.
   *
   * @param int $project_id
   *   The project ID.
   * @param string $topic
   *   The topic.
   *
   * @return array
   *   The list of topics after adding the new one.
   */
  public function addProjectTopic(int $project_id, string $topic): array {
    $this->init();
    $project = $this->project($project_id);
    $topics = $project['topics'] ?? [];
    $topics[] = $topic;
    $this->client->projects()->update($project_id, ['topics' => $topics]);
    return $topics;
  }

  /**
   * Receives a project variable.
   *
   * @param int $project_id
   *   The project ID.
   * @param string $key
   *   The variable key.
   *
   * @return array
   *   The project variable.
   */
  public function projectVariable(int $project_id, string $key): array {
    $this->init();
    try {
      return $this->client->projects()->variable($project_id, $key);
    }
    catch (RuntimeException $e) {
      return [];
    }
  }

  /**
   * Receives a project variable's value.
   *
   * @param int $project_id
   *   The project ID.
   * @param string $key
   *   The variable key.
   *
   * @return string|null
   *   The project variable's value.
   */
  public function projectVariableValue(int $project_id, string $key): ?string {
    if ($variable = $this->projectVariable($project_id, $key)) {
      return $variable['value'];
    }
    return NULL;
  }

  /**
   * Receives a group variable.
   *
   * @param int $group_id
   *   The group ID.
   * @param string $key
   *   The variable key.
   *
   * @return array
   *   The group variable.
   */
  public function groupVariable(int $group_id, string $key): array {
    $this->init();
    try {
      return $this->client->groups()->variable($group_id, $key);
    }
    catch (RuntimeException $e) {
      return [];
    }
  }

  /**
   * Receives a group variable's value.
   *
   * @param int $group_id
   *   The group ID.
   * @param string $key
   *   The variable key.
   *
   * @return string|null
   *   The group variable's value.
   */
  public function groupVariableValue(int $group_id, string $key): ?string {
    if ($variable = $this->groupVariable($group_id, $key)) {
      return $variable['value'];
    }
    return NULL;
  }

}
