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
 * Tool: assign a GitLab issue to a user (looked up by username).
 */
#[Tool(
  id: 'gitlab_api_set_issue_assignee',
  label: new TranslatableMarkup('GitLab: set issue assignee'),
  description: new TranslatableMarkup('Replaces the assignees of a GitLab issue with a single user identified by username.'),
  operation: ToolOperation::Write,
  destructive: FALSE,
  input_definitions: [
    'project' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Project'),
      description: new TranslatableMarkup('GitlabProject config entity ID.'),
      default_value: '[event:project_id]',
      required: TRUE,
    ),
    'issue_iid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue IID'),
      description: new TranslatableMarkup('GitLab issue IID.'),
      default_value: '[event:issue_iid]',
      required: TRUE,
    ),
    'assignee_username' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Assignee username'),
      description: new TranslatableMarkup('GitLab username of the new assignee.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'issue_iid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue IID'),
      default_value: '[output:issue_iid]',
      description: 'Output Token: IID of the updated issue.',
    ),
  ],
)]
final class SetIssueAssignee extends GitlabIssueToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $project = $this->resolveProject((string) $values['project']);
    if ($project === NULL) {
      return ExecutableResult::failure($this->t('Unknown project @id.', ['@id' => $values['project']]));
    }
    $iid = (int) $values['issue_iid'];
    $username = trim((string) $values['assignee_username']);
    if ($iid <= 0 || $username === '') {
      return ExecutableResult::failure($this->t('Invalid IID @iid or empty username.', ['@iid' => $iid]));
    }
    try {
      $client = $this->clientFactory->forProject($project);
      $matches = $client->users()->all(['username' => $username]);
      $userId = (int) ($matches[0]['id'] ?? 0);
      if ($userId === 0) {
        return ExecutableResult::failure($this->t('GitLab user "@u" not found.', ['@u' => $username]));
      }
      $client->issues()->update(
        $project->getGitLabProjectId(),
        $iid,
        ['assignee_ids' => [$userId]],
      );
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Set assignee "@u" on issue #@iid failed: @msg', [
        '@u' => $username,
        '@iid' => $iid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Assigned issue #@iid to @u.', ['@iid' => $iid, '@u' => $username]),
      ['issue_iid' => $iid]
    );
  }

}
