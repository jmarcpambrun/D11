<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool: set the work-item status of a GitLab issue via GraphQL.
 *
 * Requires the work-item-statuses lifecycle (Premium / Ultimate).
 */
#[Tool(
  id: 'gitlab_api_set_issue_state',
  label: new TranslatableMarkup('GitLab: set issue state'),
  description: new TranslatableMarkup('Sets a GitLab issue work-item status: open (To do), in_progress, closed (Done), wont_do.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'project' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Project'),
      description: new TranslatableMarkup('GitlabProject config entity ID.'),
      default_value: '[event:project_id]',
      required: TRUE,
    ),
    'issue_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue global ID'),
      description: new TranslatableMarkup('Global numeric ID of the issue / work item (not the per-project IID).'),
      default_value: '[event:issue_id]',
      required: TRUE,
    ),
    'state' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('State'),
      description: new TranslatableMarkup('One of: open, in_progress, closed, wont_do.'),
      required: TRUE,
      constraints: [
        'Choice' => ['choices' => ['open', 'in_progress', 'closed', 'wont_do']],
      ],
    ),
  ],
  output_definitions: [
    'issue_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue global ID'),
      default_value: '[output:issue_id]',
      description: 'Output Token: Global ID of the updated issue.',
    ),
  ],
)]
final class SetIssueState extends GitlabIssueToolBase {

  private const STATUS_GIDS = [
    'open' => 'gid://gitlab/WorkItems::Statuses::SystemDefined::Status/1',
    'in_progress' => 'gid://gitlab/WorkItems::Statuses::SystemDefined::Status/2',
    'closed' => 'gid://gitlab/WorkItems::Statuses::SystemDefined::Status/3',
    'wont_do' => 'gid://gitlab/WorkItems::Statuses::SystemDefined::Status/4',
  ];

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $project = $this->resolveProject((string) $values['project']);
    if ($project === NULL) {
      return ExecutableResult::failure($this->t('Unknown project @id.', ['@id' => $values['project']]));
    }
    $issueId = (int) $values['issue_id'];
    $state = (string) $values['state'];
    $statusGid = self::STATUS_GIDS[$state] ?? NULL;
    if ($issueId <= 0 || $statusGid === NULL) {
      return ExecutableResult::failure($this->t('Invalid issue id @i or state @s.', ['@i' => $issueId, '@s' => $state]));
    }

    $gid = "gid://gitlab/WorkItem/{$issueId}";
    $mutation = <<<'GQL'
mutation SetWorkItemStatus($id: WorkItemID!, $status: WorkItemsStatusesStatusID!) {
  workItemUpdate(input: { id: $id, statusWidget: { status: $status } }) {
    errors
  }
}
GQL;
    try {
      $result = $this->clientFactory
        ->graphqlForProject($project)
        ->execute($mutation, ['id' => $gid, 'status' => $statusGid]);
      $errors = array_filter(array_merge(
        array_map(
          static fn ($e) => is_array($e) ? (string) ($e['message'] ?? '') : (string) $e,
          $result['errors'] ?? [],
        ),
        array_map('strval', $result['data']['workItemUpdate']['errors'] ?? []),
      ));
      if ($errors !== []) {
        return $this->failWithLog('Set state @s on work item @gid: @e', [
          '@s' => $state,
          '@gid' => $gid,
          '@e' => implode('; ', $errors),
        ]);
      }
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Set state @s on work item @gid failed: @msg', [
        '@s' => $state,
        '@gid' => $gid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Set work item @gid state=@s.', ['@gid' => $gid, '@s' => $state]),
      ['issue_id' => $issueId]
    );
  }

}
