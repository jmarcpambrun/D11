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
 * Tool: remove a specific username from a GitLab issue's assignees.
 */
#[Tool(
  id: 'gitlab_api_unset_issue_assignee',
  label: new TranslatableMarkup('GitLab: unassign user from issue'),
  description: new TranslatableMarkup("Removes the given GitLab username from the issue's assignees, leaving the rest in place."),
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
      description: new TranslatableMarkup('GitLab username to unassign.'),
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
final class UnsetIssueAssignee extends GitlabIssueToolBase {

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

      $issue = $client->issues()->show($project->getGitLabProjectId(), $iid);
      $current = [];
      foreach ($issue['assignees'] ?? [] as $a) {
        if (isset($a['id'])) {
          $current[] = (int) $a['id'];
        }
      }
      if (!in_array($userId, $current, TRUE)) {
        return ExecutableResult::success(
          $this->t('@u was not assigned to issue #@iid; nothing to do.', ['@u' => $username, '@iid' => $iid]),
          ['issue_iid' => $iid]
        );
      }
      $remaining = array_values(array_filter($current, fn (int $id) => $id !== $userId));
      // GitLab semantics: [0] is the documented "unassign all" sentinel.
      $payload = ['assignee_ids' => $remaining === [] ? [0] : $remaining];
      $client->issues()->update($project->getGitLabProjectId(), $iid, $payload);
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Unassign "@u" on issue #@iid failed: @msg', [
        '@u' => $username,
        '@iid' => $iid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Unassigned @u from issue #@iid.', ['@u' => $username, '@iid' => $iid]),
      ['issue_iid' => $iid]
    );
  }

}
