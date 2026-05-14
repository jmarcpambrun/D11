<?php

declare(strict_types=1);

namespace Drupal\eca_gitlab_api\Plugin\ECA\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaEvent;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Entity\Objects\EcaEvent as EcaEventObject;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\gitlab_api\Entity\GitlabProject;
use Drupal\gitlab_api\Event\GitlabCommentEvent;
use Drupal\gitlab_api\Event\GitlabEventBase;
use Drupal\gitlab_api\Event\GitlabIssueClosedEvent;
use Drupal\gitlab_api\Event\GitlabIssueCreatedEvent;
use Drupal\gitlab_api\Event\GitlabIssueReopenedEvent;
use Drupal\gitlab_api\Event\GitlabIssueUpdatedEvent;
use Drupal\gitlab_api\Event\GitlabMrEvent;
use Drupal\gitlab_api\Event\GitlabPipelineEvent;
use Drupal\gitlab_api\Event\GitlabPushEvent;
use Drupal\gitlab_api\Event\GitlabTagPushEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * ECA event plugin for all GitLab API webhook events.
 *
 * Exposes scalar tokens straight from the webhook payload (no local mirror)
 * plus a [gitlab_project] entity token for the project the event belongs to.
 */
#[EcaEvent(
  id: 'eca_gitlab_api_event',
  label: new TranslatableMarkup('GitLab API webhook'),
  category: new TranslatableMarkup('GitLab'),
  deriver: 'Drupal\eca_gitlab_api\Plugin\ECA\Event\GitlabIntegrationEventDeriver',
  version_introduced: '3.0.0',
)]
class GitlabIntegrationEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'scope' => 'all',
      'projects' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * Ensures `scope` is the first key in the configuration array.
   *
   * ECA core's Eca::getEventInfo() calls current() on the configuration and
   * uses the result as an array offset — passing an array there throws a
   * TypeError on PHP 8.1+. Keeping a scalar key first avoids the crash and
   * also migrates legacy configs that only have `projects`.
   */
  public function setConfiguration(array $configuration): void {
    $scope = $configuration['scope']
      ?? (!empty($configuration['projects']) ? 'selected' : 'all');
    unset($configuration['scope']);
    parent::setConfiguration(['scope' => $scope] + $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $projects = [];
    foreach (GitlabProject::loadMultiple() as $project) {
      $projects[$project->id()] = $project->label();
    }

    $form['scope'] = [
      '#type' => 'radios',
      '#title' => $this->t('Scope'),
      '#options' => [
        'all' => $this->t('All projects'),
        'selected' => $this->t('Only selected projects'),
      ],
      '#default_value' => $this->configuration['scope'] ?? 'all',
      '#weight' => -25,
    ];
    $form['projects'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('GitLab projects'),
      '#options' => $projects,
      '#default_value' => $this->configuration['projects'],
      '#weight' => -20,
      '#description' => $this->t('Only used when scope is "Only selected projects".'),
      '#states' => [
        'visible' => [':input[name="scope"]' => ['value' => 'selected']],
      ],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $scope = (string) ($form_state->getValue('scope') ?? 'all');
    $projects = array_values(array_filter($form_state->getValue('projects') ?? []));
    if ($scope === 'selected' && $projects === []) {
      $scope = 'all';
    }
    $this->configuration = ['scope' => $scope, 'projects' => $projects] + $this->configuration;
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEventObject $ecaEvent): string {
    $config = $ecaEvent->getConfiguration();
    $scope = $config['scope'] ?? 'all';
    $projects = array_values(array_filter($config['projects'] ?? []));
    if ($scope !== 'selected' || $projects === []) {
      return '*';
    }
    sort($projects, SORT_STRING);
    return implode(',', $projects);
  }

  /**
   * Returns the per-derivative event metadata.
   *
   * @return array<string, array{
   *   label: string,
   *   event_name: class-string,
   *   event_class: class-string,
   *   tags: int,
   *   description: \Drupal\Core\StringTranslation\TranslatableMarkup
   *   }>
   */
  public static function definitions(): array {
    return [
      'push' => [
        'label' => 'GitLab push',
        'event_name' => GitlabPushEvent::class,
        'event_class' => GitlabPushEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when a Push Hook webhook is received.'),
      ],
      'tag_push' => [
        'label' => 'GitLab tag push',
        'event_name' => GitlabTagPushEvent::class,
        'event_class' => GitlabTagPushEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when a Tag Push Hook webhook is received.'),
      ],
      'issue_created' => [
        'label' => 'GitLab issue created',
        'event_name' => GitlabIssueCreatedEvent::class,
        'event_class' => GitlabIssueCreatedEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when an Issue Hook with action=open is received.'),
      ],
      'issue_updated' => [
        'label' => 'GitLab issue updated',
        'event_name' => GitlabIssueUpdatedEvent::class,
        'event_class' => GitlabIssueUpdatedEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when an Issue Hook with action=update is received.'),
      ],
      'issue_closed' => [
        'label' => 'GitLab issue closed',
        'event_name' => GitlabIssueClosedEvent::class,
        'event_class' => GitlabIssueClosedEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when an Issue Hook with action=close is received.'),
      ],
      'issue_reopened' => [
        'label' => 'GitLab issue reopened',
        'event_name' => GitlabIssueReopenedEvent::class,
        'event_class' => GitlabIssueReopenedEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when an Issue Hook with action=reopen is received.'),
      ],
      'comment' => [
        'label' => 'GitLab comment',
        'event_name' => GitlabCommentEvent::class,
        'event_class' => GitlabCommentEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when a Note Hook (comment) webhook is received.'),
      ],
      'mr' => [
        'label' => 'GitLab merge request',
        'event_name' => GitlabMrEvent::class,
        'event_class' => GitlabMrEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when a Merge Request Hook webhook is received.'),
      ],
      'pipeline' => [
        'label' => 'GitLab pipeline',
        'event_name' => GitlabPipelineEvent::class,
        'event_class' => GitlabPipelineEvent::class,
        'tags' => Tag::RUNTIME,
        'description' => new TranslatableMarkup('Fired when a Pipeline Hook webhook is received.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The GitLab event.',
    properties: [
      new Token(name: 'machine_name', description: 'The machine name of the ECA event.'),
      new Token(name: 'project_id', description: 'The local GitLab project machine name (config entity ID).'),
      new Token(name: 'gitlab_project_id', description: 'The GitLab numeric project ID.'),
      new Token(
        name: 'ref',
        description: 'The git ref (branch or tag). Push and tag-push events only.',
        classes: [GitlabPushEvent::class, GitlabTagPushEvent::class],
      ),
      new Token(
        name: 'commits_count',
        description: 'Total number of commits in the push. Push events only.',
        classes: [GitlabPushEvent::class],
      ),
      new Token(name: 'user_username', description: 'The GitLab username who triggered the event.'),
      new Token(name: 'user_id', description: 'The GitLab user ID who triggered the event.'),
      new Token(
        name: 'issue_iid',
        description: 'The visible GitLab issue IID. Issue and comment-on-issue events.',
        classes: [
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
          GitlabCommentEvent::class,
        ],
      ),
      new Token(
        name: 'issue_id',
        description: 'Global ID of the issue / work item (numeric). Issue and comment-on-issue events.',
        classes: [
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
          GitlabCommentEvent::class,
        ],
      ),
      new Token(
        name: 'issue_title',
        description: 'The issue title. Issue events only.',
        classes: [
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
        ],
      ),
      new Token(
        name: 'issue_action',
        description: 'The issue action (open/update/close/reopen). Issue events only.',
        classes: [
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
        ],
      ),
      new Token(
        name: 'issue_url',
        description: 'The GitLab issue URL.',
        classes: [
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
          GitlabCommentEvent::class,
        ],
      ),
      new Token(
        name: 'labels',
        description: 'Array of label titles currently on the issue. Use with ListContains or iterate. Issue events only.',
        classes: [
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
        ],
      ),
      new Token(
        name: 'labels_text',
        description: 'Comma-separated string of label titles. Issue events only.',
        classes: [
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
        ],
      ),
      new Token(name: 'note_id', description: 'The GitLab note ID. Comment events only.', classes: [GitlabCommentEvent::class]),
      new Token(
        name: 'notable_type',
        description: 'The comment target: Issue/MergeRequest/Snippet/Commit. Comment events only.',
        classes: [GitlabCommentEvent::class],
      ),
      new Token(
        name: 'notable_id',
        description: 'Global ID of the notable (issue / MR / etc.). Comment events only.',
        classes: [GitlabCommentEvent::class],
      ),
      new Token(
        name: 'body',
        description: 'The comment body (comment events) or issue description (issue events).',
        classes: [
          GitlabCommentEvent::class,
          GitlabIssueCreatedEvent::class,
          GitlabIssueUpdatedEvent::class,
          GitlabIssueClosedEvent::class,
          GitlabIssueReopenedEvent::class,
        ],
      ),
      new Token(name: 'comment_url', description: 'The comment URL. Comment events only.', classes: [GitlabCommentEvent::class]),
      new Token(
        name: 'mr_iid',
        description: 'The visible GitLab merge request IID. MR, pipeline, and comment-on-MR events.',
        classes: [GitlabMrEvent::class, GitlabPipelineEvent::class, GitlabCommentEvent::class],
      ),
      new Token(name: 'mr_title', description: 'The merge request title. MR events only.', classes: [GitlabMrEvent::class]),
      new Token(
        name: 'mr_action',
        description: 'The MR action (open/update/close/reopen/merge/...). MR events only.',
        classes: [GitlabMrEvent::class],
      ),
      new Token(
        name: 'mr_url',
        description: 'The MR URL. MR and pipeline events.',
        classes: [GitlabMrEvent::class, GitlabPipelineEvent::class],
      ),
      new Token(name: 'pipeline_id', description: 'The GitLab pipeline ID. Pipeline events only.', classes: [GitlabPipelineEvent::class]),
      new Token(
        name: 'pipeline_status',
        description: 'The pipeline status (success/failed/...). Pipeline events only.',
        classes: [GitlabPipelineEvent::class],
      ),
      new Token(name: 'payload_json', description: 'The full GitLab webhook payload as JSON.'),
      new Token(
        name: 'maintainer_mentions',
        description: "The project's maintainer usernames as a space-separated string of @-mentions.",
      ),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = parent::buildEventData();

    if (!$event instanceof GitlabEventBase) {
      return $data;
    }

    $payload = $event->getPayload();
    $oa = is_array($payload['object_attributes'] ?? NULL) ? $payload['object_attributes'] : [];
    $user = is_array($payload['user'] ?? NULL) ? $payload['user'] : [];
    $payloadIssue = is_array($payload['issue'] ?? NULL) ? $payload['issue'] : [];
    $payloadMr = is_array($payload['merge_request'] ?? NULL) ? $payload['merge_request'] : [];

    $data['project_id'] = $event->getProjectId();
    $data['gitlab_project_id'] = (string) ($payload['project']['id'] ?? $payload['project_id'] ?? $event->getProject()->getGitLabProjectId());
    $data['user_username'] = (string) ($user['username'] ?? $payload['user_username'] ?? '');
    $data['user_id'] = (string) ($user['id'] ?? $payload['user_id'] ?? '');
    $data['payload_json'] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';

    $maintainers = array_values(array_filter(array_map(
      static fn ($u): string => trim((string) $u),
      $event->getProject()->getMaintainers(),
    )));
    $data['maintainer_mentions'] = $maintainers === []
      ? ''
      : '@' . implode(' @', $maintainers);

    if ($event instanceof GitlabPushEvent || $event instanceof GitlabTagPushEvent) {
      $data['ref'] = (string) ($payload['ref'] ?? '');
      $data['commits_count'] = (string) ($payload['total_commits_count'] ?? count($payload['commits'] ?? []));
    }
    elseif ($event instanceof GitlabIssueCreatedEvent
      || $event instanceof GitlabIssueUpdatedEvent
      || $event instanceof GitlabIssueClosedEvent
      || $event instanceof GitlabIssueReopenedEvent) {
      $data['issue_iid'] = (string) ($oa['iid'] ?? '');
      $data['issue_id'] = (string) ($oa['id'] ?? '');
      $data['issue_title'] = (string) ($oa['title'] ?? '');
      $data['issue_action'] = (string) ($oa['action'] ?? '');
      $data['issue_url'] = (string) ($oa['url'] ?? '');
      $data['body'] = (string) ($oa['description'] ?? '');
      $labelTitles = $this->extractLabelTitles($payload['labels'] ?? $oa['labels'] ?? []);
      $data['labels'] = $labelTitles;
      $data['labels_text'] = implode(', ', $labelTitles);
    }
    elseif ($event instanceof GitlabCommentEvent) {
      $data['note_id'] = (string) ($oa['id'] ?? '');
      $data['notable_type'] = (string) ($oa['notable_type'] ?? '');
      $data['notable_id'] = (string) ($oa['notable_id'] ?? '');
      $data['body'] = (string) ($oa['note'] ?? $oa['description'] ?? '');
      $data['comment_url'] = (string) ($oa['url'] ?? '');
      $data['issue_iid'] = (string) ($payloadIssue['iid'] ?? '');
      $data['issue_id'] = strtolower((string) ($oa['notable_type'] ?? '')) === 'issue'
        ? $data['notable_id']
        : (string) ($payloadIssue['id'] ?? '');
      $data['issue_url'] = (string) ($payloadIssue['url'] ?? '');
      $data['mr_iid'] = (string) ($payloadMr['iid'] ?? '');
    }
    elseif ($event instanceof GitlabMrEvent) {
      $data['mr_iid'] = (string) ($oa['iid'] ?? '');
      $data['mr_title'] = (string) ($oa['title'] ?? '');
      $data['mr_action'] = (string) ($oa['action'] ?? '');
      $data['mr_url'] = (string) ($oa['url'] ?? '');
    }
    elseif ($event instanceof GitlabPipelineEvent) {
      $data['pipeline_id'] = (string) ($oa['id'] ?? '');
      $data['pipeline_status'] = (string) ($oa['status'] ?? '');
      $data['mr_iid'] = (string) ($payloadMr['iid'] ?? '');
      $data['mr_url'] = (string) ($payloadMr['url'] ?? '');
    }

    $this->token->addTokenData('gitlab_project', $event->getProject());

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($wildcard === '*') {
      return TRUE;
    }
    if (!$event instanceof GitlabEventBase) {
      return FALSE;
    }
    $project_id = $event->getProjectId();
    if ($project_id === '') {
      return FALSE;
    }
    $projects = array_filter(explode(',', $wildcard));
    return in_array($project_id, $projects, TRUE);
  }

  /**
   * Extracts label titles from a webhook label array.
   *
   * @return list<string>
   *   Trimmed non-empty label titles, in payload order.
   */
  private function extractLabelTitles(mixed $labels): array {
    if (!is_array($labels)) {
      return [];
    }
    $titles = [];
    foreach ($labels as $label) {
      if (is_array($label) && isset($label['title'])) {
        $title = trim((string) $label['title']);
        if ($title !== '') {
          $titles[] = $title;
        }
      }
    }
    return $titles;
  }

}
